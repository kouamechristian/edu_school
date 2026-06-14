<?php

namespace App\Controller;

use App\Controller\Concern\HandlesEntityDeletion;
use App\Entity\Payment;
use App\Form\PaymentType;
use App\Repository\CashRegisterRepository;
use App\Repository\PaymentRepository;
use App\Repository\StudentFeeRepository;
use App\Repository\StudentRepository;
use App\Service\SchoolContextService;
use App\Service\FeeAssignmentService;
use App\Service\PaymentReceiptService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/payments', name: 'admin_payment_')]
#[IsGranted('ROLE_CAISSE')]
class PaymentController extends AbstractController
{
    use HandlesEntityDeletion;

    private function generatePaymentReference(string $method): string
    {
        $prefix = match ($method) {
            'mobile_money' => 'MM',
            'chèque' => 'CHQ',
            'virement' => 'VIR',
            'carte' => 'CB',
            default => 'ESP',
        };

        return sprintf('%s-%s-%04d', $prefix, date('YmdHis'), random_int(1, 9999));
    }

    /**
     * @param float $priorImputedAmount Montant déjà imputé pour cette ligne (édition d'un paiement encaissé)
     */
    private function validatePaymentAmountWithinRemaining(
        Payment $payment,
        FormInterface $form,
        StudentFeeRepository $studentFeeRepository,
        float $priorImputedAmount = 0.0
    ): void {
        $student = $payment->getStudent();
        $fee = $payment->getFee();
        if (!$student || !$fee) {
            return;
        }

        $studentFee = $studentFeeRepository->findOneForStudentAndFee($student->getId(), $fee->getId());
        $remaining = $studentFee !== null
            ? $studentFee->getRemainingAmount()
            : (float) $fee->getFinalAmount();

        $max = $remaining + $priorImputedAmount;
        $requested = (float) $payment->getAmount();

        if ($requested > $max + 0.009) {
            $form->get('amount')->addError(new FormError(sprintf(
                'Le montant ne peut pas dépasser le reste dû pour ce frais (%s F CFA).',
                number_format($max, 0, ',', ' ')
            )));
        }
    }

    #[Route('/', name: 'index', methods: ['GET'])]
    public function index(Request $request, PaymentRepository $paymentRepository, SchoolContextService $contextService, CashRegisterRepository $cashRegisterRepository): Response
    {
        // Récupérer l'établissement courant
        $currentSchool = $contextService->getCurrentSchool();

        // Si pas d'établissement sélectionné, afficher un message
        if (!$currentSchool) {
            $this->addFlash('warning', 'Veuillez sélectionner un établissement pour voir les paiements.');
            return $this->render('payment/index.html.twig', [
                'payments' => [],
                'stats' => [],
                'current_status' => null,
                'current_method' => null,
                'search_term' => null,
                'current_school' => null,
                'cash_register_open' => false,
                'cash_register_validated' => false,
            ]);
        }

        // État de la caisse du caissier courant (pour gérer le bouton "Nouveau paiement").
        $cashRegisterOpen = false;
        $cashRegisterValidated = false;
        $cashier = $this->getUser();
        if ($cashier instanceof \App\Entity\User) {
            $cashRegister = $cashRegisterRepository->findOpenForCashier($currentSchool, $cashier);
            $cashRegisterOpen = (bool) $cashRegister;
            $cashRegisterValidated = $cashRegister && $cashRegister->isValidated();
        }

        // Filtres
        $status = $request->query->get('status');
        $method = $request->query->get('method');
        $search = $request->query->get('search');

        // Filtrer les paiements
        if ($search) {
            $payments = $paymentRepository->searchByNumberOrReference($search);
        } elseif ($status) {
            $payments = $paymentRepository->findByStatus($status);
        } elseif ($method) {
            $payments = $paymentRepository->findByPaymentMethod($method);
        } else {
            $payments = $paymentRepository->findRecent(50);
        }

        // Statistiques
        $stats = [
            'total' => count($payments),
            'by_status' => $paymentRepository->countByStatus(),
            'by_method' => $paymentRepository->countByPaymentMethod(),
            'total_amount' => $paymentRepository->getTotalAmountByDateRange(
                new \DateTime('-30 days'),
                new \DateTime()
            )
        ];

        return $this->render('payment/index.html.twig', [
            'payments' => $payments,
            'stats' => $stats,
            'current_status' => $status,
            'current_method' => $method,
            'search_term' => $search,
            'current_school' => $currentSchool,
            'cash_register_open' => $cashRegisterOpen,
            'cash_register_validated' => $cashRegisterValidated,
        ]);
    }

    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        EntityManagerInterface $entityManager,
        SchoolContextService $contextService,
        CashRegisterRepository $cashRegisterRepository,
        StudentFeeRepository $studentFeeRepository,
        FeeAssignmentService $feeAssignmentService,
        StudentRepository $studentRepository,
        PaymentReceiptService $paymentReceiptService
    ): Response
    {
        $currentSchool = $contextService->getCurrentSchool();
        $cashier = $this->getUser();

        if (!$currentSchool) {
            $this->addFlash('warning', 'Veuillez sélectionner un établissement avant d\'enregistrer un paiement.');
            return $this->redirectToRoute('admin_payment_index');
        }

        if (!$cashier instanceof \App\Entity\User) {
            $this->addFlash('error', 'Utilisateur invalide.');
            return $this->redirectToRoute('admin_payment_index');
        }

        $cashRegister = $cashRegisterRepository->findOpenForCashier($currentSchool, $cashier);
        if (!$cashRegister) {
            $this->addFlash('warning', 'Votre caisse n’est pas ouverte. Veuillez l’ouvrir avant d’enregistrer un paiement.');
            return $this->redirectToRoute('admin_cash_register_open');
        }

        if (!$cashRegister->isValidated()) {
            $this->addFlash('warning', 'Votre caisse n’a pas encore été validée par le fondateur. Aucune opération n’est possible tant qu’elle n’est pas validée.');
            return $this->redirectToRoute('admin_cash_register_index');
        }

        $payment = new Payment();
        $studentChoices = $studentRepository->findWithRemainingBalanceBySchool($currentSchool->getId());
        $form = $this->createForm(PaymentType::class, $payment, [
            'student_choices' => $studentChoices,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            $this->validatePaymentAmountWithinRemaining($payment, $form, $studentFeeRepository, 0.0);
        }

        if ($form->isSubmitted() && $form->isValid()) {
            // Générer un numéro de paiement unique
            $paymentNumber = 'PAY-' . date('Ymd') . '-' . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
            $payment->setPaymentNumber($paymentNumber);
            $payment->setRecordedBy($this->getUser());
            $payment->setReference($this->generatePaymentReference((string) $payment->getPaymentMethod()));
            $payment->setCashRegister($cashRegister);
            // Un enregistrement manuel est un encaissement immédiat
            $payment->setStatus('payé');

            // Mise à jour automatique de la scolarité de l'élève
            // (incrementer StudentFee.paidAmount pour que le total payé de l'élève se mette à jour)
            $student = $payment->getStudent();
            $fee = $payment->getFee();

            if ($student && $fee) {
                $studentFee = $studentFeeRepository->findOneForStudentAndFee($student->getId(), $fee->getId());

                if (!$studentFee) {
                    $studentFee = $feeAssignmentService->assignFeeToStudent($fee, $student);
                }

                if ($studentFee) {
                    $amount = (float) $payment->getAmount();
                    $payment->setAmount((string) number_format($amount, 2, '.', ''));
                    $studentFee->setPaidAmount((string) number_format(((float) $studentFee->getPaidAmount()) + $amount, 2, '.', ''));
                    $payment->setStudentFee($studentFee);
                }
            }

            $entityManager->persist($payment);
            $entityManager->flush();

            // Générer automatiquement le reçu PDF (si encaissement)
            if ($payment->getStatus() === 'payé' && !$payment->getReceiptPath()) {
                $paths = $paymentReceiptService->generateAndStore($payment);
                $payment->setReceiptPath($paths['relative_path']);
                $entityManager->flush();
            }

            $this->addFlash('success', 'Le paiement a été enregistré avec succès.');

            return $this->redirectToRoute('admin_payment_index', [], Response::HTTP_SEE_OTHER);
        }

        if ($form->isSubmitted() && !$form->isValid()) {
            $messages = [];
            foreach ($form->getErrors(true) as $error) {
                $messages[] = $error->getMessage();
            }

            $this->addFlash('error', 'Paiement non enregistré: ' . implode(' | ', array_unique($messages)));
        }

        return $this->render('payment/new.html.twig', [
            'payment' => $payment,
            'form' => $form,
        ]);
    }

    #[Route('/students/{id}/summary', name: 'student_summary', methods: ['GET'])]
    public function studentSummary(\App\Entity\Student $student, SchoolContextService $contextService): JsonResponse
    {
        $currentSchool = $contextService->getCurrentSchool();
        if (!$currentSchool || $student->getSchool()?->getId() !== $currentSchool->getId()) {
            return new JsonResponse(['error' => 'Élève introuvable.'], Response::HTTP_NOT_FOUND);
        }

        $classroom = $student->getClassroom();

        return new JsonResponse([
            'id' => $student->getId(),
            'nom' => $student->getFirstName(),
            'prenom' => $student->getLastName(),
            'classe' => $classroom?->getFullName() ?: ($classroom?->getName() ?: null),
            'montantScolarite' => $student->getTotalTuition(),
            'montantPaye' => $student->getTotalPaid(),
            'montantRestant' => $student->getRemainingTuition(),
        ]);
    }

    /**
     * Frais affectés à un élève (JSON) — alimente le sélecteur de frais en cascade
     * sur le formulaire de paiement : seuls les frais de l'élève choisi sont proposés.
     */
    #[Route('/students/{id}/fees', name: 'student_fees', methods: ['GET'])]
    public function studentFees(\App\Entity\Student $student, SchoolContextService $contextService): JsonResponse
    {
        $currentSchool = $contextService->getCurrentSchool();
        if (!$currentSchool || $student->getSchool()?->getId() !== $currentSchool->getId()) {
            return new JsonResponse(['error' => 'Élève introuvable.'], Response::HTTP_NOT_FOUND);
        }

        $fees = [];
        foreach ($student->getStudentFees() as $studentFee) {
            $fee = $studentFee->getFee();
            // Exclure les frais inactifs et ceux déjà entièrement soldés.
            if (!$fee || !$fee->isActive() || $studentFee->getRemainingAmount() <= 0) {
                continue;
            }

            $fees[] = [
                'id' => $fee->getId(),
                'name' => $fee->getName(),
                'amount' => (float) $studentFee->getAmount(),
                'paid' => (float) $studentFee->getPaidAmount(),
                'remaining' => $studentFee->getRemainingAmount(),
            ];
        }

        return new JsonResponse(['fees' => $fees]);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(Payment $payment): Response
    {
        return $this->render('payment/show.html.twig', [
            'payment' => $payment,
        ]);
    }

    #[Route('/{id}/receipt', name: 'receipt', methods: ['GET'])]
    public function receipt(
        Payment $payment,
        PaymentReceiptService $paymentReceiptService,
        EntityManagerInterface $entityManager
    ): Response {
        if ($payment->getStatus() !== 'payé') {
            $this->addFlash('warning', 'Le reçu est disponible uniquement pour les paiements encaissés.');
            return $this->redirectToRoute('admin_payment_show', ['id' => $payment->getId()], Response::HTTP_SEE_OTHER);
        }

        // Si le reçu n'existe pas encore, le générer
        if (!$payment->getReceiptPath()) {
            $paths = $paymentReceiptService->generateAndStore($payment);
            $payment->setReceiptPath($paths['relative_path']);
            $entityManager->flush();
        }

        $relative = (string) $payment->getReceiptPath();
        $absolute = rtrim($this->getParameter('kernel.project_dir'), DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR
            . str_replace('/', DIRECTORY_SEPARATOR, $relative);

        if (!is_file($absolute)) {
            // régénérer si le fichier a été supprimé
            $paths = $paymentReceiptService->generateAndStore($payment);
            $payment->setReceiptPath($paths['relative_path']);
            $entityManager->flush();
            $absolute = $paths['absolute_path'];
        }

        $response = new BinaryFileResponse($absolute);
        $response->setContentDisposition(
            ResponseHeaderBag::DISPOSITION_INLINE,
            basename($absolute)
        );
        $response->headers->set('Content-Type', 'application/pdf');

        return $response;
    }

    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'])]
    public function edit(
        Request $request,
        Payment $payment,
        EntityManagerInterface $entityManager,
        SchoolContextService $contextService,
        StudentRepository $studentRepository,
        StudentFeeRepository $studentFeeRepository
    ): Response {
        $currentSchool = $contextService->getCurrentSchool();
        $snapshotAmount = (float) $payment->getAmount();
        $snapshotStudentId = $payment->getStudent()?->getId();
        $snapshotFeeId = $payment->getFee()?->getId();
        $snapshotStatus = $payment->getStatus();

        $studentChoices = [];
        if ($currentSchool) {
            $studentChoices = $studentRepository->findWithRemainingBalanceBySchool($currentSchool->getId());
        }
        $boundStudent = $payment->getStudent();
        if ($boundStudent) {
            $ids = array_map(static fn ($s) => $s->getId(), $studentChoices);
            if (!\in_array($boundStudent->getId(), $ids, true)) {
                $studentChoices[] = $boundStudent;
            }
        }

        $form = $this->createForm(PaymentType::class, $payment, [
            'student_choices' => $studentChoices,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            $inPaidStates = \in_array($snapshotStatus, ['payé', 'partiellement_payé'], true);
            $sameLine = $payment->getStudent()?->getId() === $snapshotStudentId
                && $payment->getFee()?->getId() === $snapshotFeeId;
            $prior = ($inPaidStates && $sameLine) ? $snapshotAmount : 0.0;
            $this->validatePaymentAmountWithinRemaining($payment, $form, $studentFeeRepository, $prior);
        }

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            $this->addFlash('success', 'Le paiement a été modifié avec succès.');

            return $this->redirectToRoute('admin_payment_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('payment/edit.html.twig', [
            'payment' => $payment,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'delete', methods: ['POST'])]
    public function delete(
        Request $request,
        Payment $payment,
        EntityManagerInterface $entityManager,
        StudentFeeRepository $studentFeeRepository
    ): Response
    {
        if ($this->isCsrfTokenValid('delete'.$payment->getId(), $request->request->get('_token'))) {
            // Recalculer la scolarité si ce paiement a déjà été imputé à un StudentFee
            if (in_array($payment->getStatus(), ['payé', 'partiellement_payé'], true)) {
                $student = $payment->getStudent();
                $fee = $payment->getFee();
                $studentFee = $payment->getStudentFee();

                if (!$studentFee && $student && $fee) {
                    $studentFee = $studentFeeRepository->findOneForStudentAndFee($student->getId(), $fee->getId());
                }

                if ($studentFee) {
                    $currentPaid = (float) $studentFee->getPaidAmount();
                    $toRollback = (float) $payment->getAmount();
                    $studentFee->setPaidAmount((string) number_format(max(0, $currentPaid - $toRollback), 2, '.', ''));
                }
            }

            $this->deleteEntity(
                $entityManager,
                $payment,
                'Le paiement a été supprimé avec succès.',
                'Suppression impossible : ce paiement est encore lié à d\'autres données (reçu, transaction...).'
            );
        }

        return $this->redirectToRoute('admin_payment_index', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/{id}/confirm', name: 'confirm', methods: ['POST'])]
    public function confirm(
        Request $request,
        Payment $payment,
        EntityManagerInterface $entityManager,
        StudentFeeRepository $studentFeeRepository,
        FeeAssignmentService $feeAssignmentService
    ): Response
    {
        if ($this->isCsrfTokenValid('confirm'.$payment->getId(), $request->request->get('_token'))) {
            $payment->setStatus('payé');

            // Appliquer l'encaissement à la scolarité de l'élève si nécessaire
            $student = $payment->getStudent();
            $fee = $payment->getFee();
            if ($student && $fee) {
                $studentFee = $payment->getStudentFee()
                    ?? $studentFeeRepository->findOneForStudentAndFee($student->getId(), $fee->getId());

                if (!$studentFee) {
                    $studentFee = $feeAssignmentService->assignFeeToStudent($fee, $student);
                }

                if ($studentFee) {
                    $remaining = $studentFee->getRemainingAmount();
                    $requested = (float) $payment->getAmount();
                    if ($requested > $remaining + 0.009) {
                        $this->addFlash('error', sprintf(
                            'Le montant ne peut pas dépasser le reste dû pour ce frais (%s F CFA).',
                            number_format($remaining, 0, ',', ' ')
                        ));

                        return $this->redirectToRoute('admin_payment_show', ['id' => $payment->getId()], Response::HTTP_SEE_OTHER);
                    }
                    $payment->setAmount((string) number_format($requested, 2, '.', ''));
                    $studentFee->setPaidAmount((string) number_format(((float) $studentFee->getPaidAmount()) + $requested, 2, '.', ''));
                    $payment->setStudentFee($studentFee);
                }
            }

            $entityManager->flush();

            $this->addFlash('success', 'Le paiement a été confirmé avec succès.');
        }

        return $this->redirectToRoute('admin_payment_show', ['id' => $payment->getId()], Response::HTTP_SEE_OTHER);
    }

    #[Route('/{id}/cancel', name: 'cancel', methods: ['POST'])]
    public function cancel(Request $request, Payment $payment, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('cancel'.$payment->getId(), $request->request->get('_token'))) {
            $payment->setStatus('annulé');
            $entityManager->flush();

            $this->addFlash('success', 'Le paiement a été annulé avec succès.');
        }

        return $this->redirectToRoute('admin_payment_show', ['id' => $payment->getId()], Response::HTTP_SEE_OTHER);
    }

    #[Route('/pending', name: 'pending', methods: ['GET'])]
    public function pending(PaymentRepository $paymentRepository): Response
    {
        $pendingPayments = $paymentRepository->findPending();

        return $this->render('payment/pending.html.twig', [
            'payments' => $pendingPayments,
        ]);
    }

    #[Route('/recent', name: 'recent', methods: ['GET'])]
    public function recent(PaymentRepository $paymentRepository): Response
    {
        $recentPayments = $paymentRepository->findRecent(20);

        return $this->render('payment/recent.html.twig', [
            'payments' => $recentPayments,
        ]);
    }
}

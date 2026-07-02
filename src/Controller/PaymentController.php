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
    public function index(Request $request, PaymentRepository $paymentRepository, SchoolContextService $contextService, CashRegisterRepository $cashRegisterRepository, \Knp\Component\Pager\PaginatorInterface $paginator): Response
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

        // Liste complète conservée pour les cartes de statistiques (qui parcourent la
        // collection) ; la table est paginée à 50/page.
        $allPayments = $payments;
        $payments = $paginator->paginate($payments, $request->query->getInt('page', 1), 50);

        return $this->render('payment/index.html.twig', [
            'payments' => $payments,
            'all_payments' => $allPayments,
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
        StudentRepository $studentRepository
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

            // Le reçu n'est plus stocké : il est généré à la volée à l'ouverture.

            $this->addFlash('success', 'Le paiement a été enregistré avec succès. Le reçu s\'ouvre dans un nouvel onglet.');

            // On revient sur la fiche du paiement ; le reçu (PDF) s'ouvre dans un nouvel onglet (JS).
            return $this->redirectToRoute('admin_payment_show', ['id' => $payment->getId(), 'receipt' => 1], Response::HTTP_SEE_OTHER);
        }

        if ($form->isSubmitted() && !$form->isValid()) {
            $messages = [];
            foreach ($form->getErrors(true) as $error) {
                $messages[] = $error->getMessage();
            }

            $this->addFlash('error', 'Paiement non enregistré: ' . implode(' | ', array_unique($messages)));
        }

        // Frais de chaque élève embarqués dans la page : la cascade « Frais selon élève »
        // fonctionne ainsi sans appel AJAX ni dépendance externe (déterministe).
        $schoolYearId = $contextService->getCurrentSchoolYear()?->getId();
        $feesByStudent = [];
        foreach ($studentChoices as $choiceStudent) {
            $inscription = $choiceStudent->getScolariteRegistration($schoolYearId);
            $studentFees = $inscription ? $inscription->getStudentFees() : $choiceStudent->getStudentFees();
            $list = [];
            foreach ($studentFees as $studentFee) {
                $fee = $studentFee->getFee();
                if (!$fee || !$fee->isActive() || $studentFee->getRemainingAmount() <= 0) {
                    continue;
                }

                // Échéances du frais (rangées par échéancier) avec le reste par échéance
                // (imputation en cascade du déjà-payé, les plus anciennes d'abord).
                $schedules = $fee->getSchedules()->toArray();
                usort($schedules, static fn ($a, $b) => ($a->getOrderNumber() ?? 0) <=> ($b->getOrderNumber() ?? 0));
                $paidLeft = (float) $studentFee->getPaidAmount();
                $scheduleList = [];
                foreach ($schedules as $i => $schedule) {
                    $amt = (float) $schedule->getAmount();
                    $imp = min($paidLeft, $amt);
                    $paidLeft -= $imp;
                    $scheduleList[] = [
                        'order' => $schedule->getOrderNumber() ?? ($i + 1),
                        'due' => $schedule->getDueDate()?->format('d/m/Y'),
                        'amount' => $amt,
                        'remaining' => round($amt - $imp, 2),
                    ];
                }

                $list[] = [
                    'id' => $fee->getId(),
                    'name' => $fee->getName(),
                    'remaining' => $studentFee->getRemainingAmount(),
                    'schedules' => $scheduleList,
                ];
            }
            $feesByStudent[$choiceStudent->getId()] = $list;
        }

        return $this->render('payment/new.html.twig', [
            'payment' => $payment,
            'form' => $form,
            'fees_by_student' => $feesByStudent,
        ]);
    }

    #[Route('/students/{id}/summary', name: 'student_summary', methods: ['GET'])]
    public function studentSummary(\App\Entity\Student $student, SchoolContextService $contextService): JsonResponse
    {
        $currentSchool = $contextService->getCurrentSchool();
        if (!$currentSchool || $student->getSchool()?->getId() !== $currentSchool->getId()) {
            return new JsonResponse(['error' => 'Élève introuvable.'], Response::HTTP_NOT_FOUND);
        }

        // Situation de l'année courante (frais rattachés à l'inscription).
        $inscription = $student->getScolariteRegistration($contextService->getCurrentSchoolYear()?->getId());
        $classroom = $inscription?->getClassroom() ?? $student->getClassroom();

        return new JsonResponse([
            'id' => $student->getId(),
            'nom' => $student->getFirstName(),
            'prenom' => $student->getLastName(),
            'classe' => $classroom?->getFullName() ?: ($classroom?->getName() ?: null),
            'montantScolarite' => $inscription ? $inscription->getTotalTuition() : $student->getTotalTuition(),
            'montantPaye' => $inscription ? $inscription->getTotalPaid() : $student->getTotalPaid(),
            'montantRestant' => $inscription ? $inscription->getRemainingTuition() : $student->getRemainingTuition(),
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

        // Frais de l'année courante (rattachés à l'inscription) ; repli sur l'élève.
        $inscription = $student->getScolariteRegistration($contextService->getCurrentSchoolYear()?->getId());
        $studentFees = $inscription ? $inscription->getStudentFees() : $student->getStudentFees();

        $fees = [];
        foreach ($studentFees as $studentFee) {
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

    #[Route('/{id}', name: 'show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(Payment $payment): Response
    {
        return $this->render('payment/show.html.twig', [
            'payment' => $payment,
        ]);
    }

    #[Route('/{id}/receipt', name: 'receipt', methods: ['GET'])]
    public function receipt(
        Payment $payment,
        PaymentReceiptService $paymentReceiptService
    ): Response {
        if ($payment->getStatus() !== 'payé') {
            $this->addFlash('warning', 'Le reçu est disponible uniquement pour les paiements encaissés.');
            return $this->redirectToRoute('admin_payment_show', ['id' => $payment->getId()], Response::HTTP_SEE_OTHER);
        }

        // Reçu généré à la volée et affiché dans le navigateur (aucune sauvegarde disque).
        $filename = sprintf('recu_%s.pdf', $payment->getPaymentNumber() ?: ('payment_' . $payment->getId()));

        return new Response($paymentReceiptService->render($payment), Response::HTTP_OK, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => sprintf('inline; filename="%s"', $filename),
        ]);
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
    #[IsGranted('ROLE_ADMIN')]
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
            if ($payment->getStatus() === 'annulé') {
                $this->addFlash('info', 'Ce paiement est déjà annulé.');

                return $this->redirectToRoute('admin_payment_show', ['id' => $payment->getId()], Response::HTTP_SEE_OTHER);
            }

            // Si le paiement avait été encaissé, on retire le montant imputé au frais de l'élève.
            if ($payment->getStatus() === 'payé' && ($studentFee = $payment->getStudentFee()) !== null) {
                $newPaid = max(0.0, ((float) $studentFee->getPaidAmount()) - (float) $payment->getAmount());
                $studentFee->setPaidAmount((string) number_format($newPaid, 2, '.', ''));
            }

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

    #[Route('/cancelled', name: 'cancelled', methods: ['GET'])]
    public function cancelled(Request $request, PaymentRepository $paymentRepository, \Knp\Component\Pager\PaginatorInterface $paginator): Response
    {
        $cancelledPayments = $paymentRepository->findByStatus('annulé');
        $payments = $paginator->paginate($cancelledPayments, $request->query->getInt('page', 1), 50);

        return $this->render('payment/cancelled.html.twig', [
            'payments' => $payments,
            'total' => count($cancelledPayments),
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

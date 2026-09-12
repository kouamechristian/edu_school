<?php

namespace App\Controller;

use App\Controller\Concern\HandlesEntityDeletion;
use App\Entity\CashRegister;
use App\Entity\Payment;
use App\Entity\Student;
use App\Entity\User;
use App\Form\PaymentStartType;
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

    /** Saisies en cours (étape 1 validée, imputation pas encore enregistrée), par jeton. */
    private const DRAFT_SESSION_KEY = 'payment_drafts';

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

    /**
     * Caisse ouverte et validée du caissier courant, ou redirection expliquant
     * pourquoi aucun encaissement n'est possible.
     */
    private function requireOpenCashRegister(
        SchoolContextService $contextService,
        CashRegisterRepository $cashRegisterRepository
    ): CashRegister|Response {
        $currentSchool = $contextService->getCurrentSchool();
        if (!$currentSchool) {
            $this->addFlash('warning', 'Veuillez sélectionner un établissement avant d\'enregistrer un paiement.');
            return $this->redirectToRoute('admin_payment_index');
        }

        $cashier = $this->getUser();
        if (!$cashier instanceof User) {
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

        return $cashRegister;
    }

    /**
     * Frais restant dus par l'élève pour l'année courante, prêts pour l'imputation.
     * Ordre : arriérés antérieurs d'abord (à solder en priorité), puis par prochaine
     * échéance impayée — c'est aussi l'ordre de la répartition automatique.
     *
     * @return list<array{
     *     student_fee: \App\Entity\StudentFee, id: int, name: string, amount: float, paid: float,
     *     remaining: float, is_arriere: bool, annee_origine: ?string, next_due: string,
     *     schedules: list<array{order: int, due: ?string, amount: float, remaining: float}>
     * }>
     */
    private function buildOutstandingFees(Student $student, ?int $schoolYearId): array
    {
        $inscription = $student->getScolariteRegistration($schoolYearId);
        $studentFees = $inscription ? $inscription->getStudentFees() : $student->getStudentFees();

        $list = [];
        foreach ($studentFees as $studentFee) {
            $fee = $studentFee->getFee();
            if (!$fee || !$fee->isActive() || $studentFee->getRemainingAmount() <= 0) {
                continue;
            }

            // Échéances du frais avec le reste par échéance (imputation en cascade du
            // déjà-payé, les plus anciennes d'abord).
            $schedules = $fee->getSchedules()->toArray();
            usort($schedules, static fn ($a, $b) => ($a->getOrderNumber() ?? 0) <=> ($b->getOrderNumber() ?? 0));
            $paidLeft = (float) $studentFee->getPaidAmount();
            $scheduleList = [];
            // Un frais sans échéancier (ou une échéance sans date) est dû immédiatement.
            $nextDue = null;
            foreach ($schedules as $i => $schedule) {
                $amt = (float) $schedule->getAmount();
                $imp = min($paidLeft, $amt);
                $paidLeft -= $imp;
                $remaining = round($amt - $imp, 2);
                if ($remaining > 0 && $nextDue === null) {
                    $nextDue = $schedule->getDueDate()?->format('Y-m-d') ?? '0000-00-00';
                }
                $scheduleList[] = [
                    'order' => $schedule->getOrderNumber() ?? ($i + 1),
                    'due' => $schedule->getDueDate()?->format('d/m/Y'),
                    'amount' => $amt,
                    'remaining' => $remaining,
                ];
            }

            $list[] = [
                'student_fee' => $studentFee,
                'id' => $studentFee->getId(),
                'name' => (string) $fee->getName(),
                'amount' => (float) $studentFee->getAmount(),
                'paid' => (float) $studentFee->getPaidAmount(),
                'remaining' => $studentFee->getRemainingAmount(),
                'is_arriere' => $studentFee->isArriereAnterieur(),
                'annee_origine' => $studentFee->getAnneeOrigine(),
                'next_due' => $nextDue ?? '0000-00-00',
                'schedules' => $scheduleList,
            ];
        }

        usort($list, static fn (array $a, array $b): int => ($b['is_arriere'] <=> $a['is_arriere'])
            ?: ($a['next_due'] <=> $b['next_due'])
            ?: strcmp($a['name'], $b['name']));

        return $list;
    }

    private function loadDraft(Request $request, string $token): ?array
    {
        return $request->getSession()->get(self::DRAFT_SESSION_KEY, [])[$token] ?? null;
    }

    private function saveDraft(Request $request, string $token, ?array $draft): void
    {
        $session = $request->getSession();
        $drafts = $session->get(self::DRAFT_SESSION_KEY, []);
        if ($draft === null) {
            unset($drafts[$token]);
        } else {
            $drafts[$token] = $draft;
        }
        $session->set(self::DRAFT_SESSION_KEY, $drafts);
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

    /**
     * Étape 1 : l'élève et le montant versé par le parent. Le montant est gardé en
     * session puis réparti sur les frais à l'étape d'imputation.
     */
    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        SchoolContextService $contextService,
        CashRegisterRepository $cashRegisterRepository,
        StudentRepository $studentRepository
    ): Response {
        $cashRegister = $this->requireOpenCashRegister($contextService, $cashRegisterRepository);
        if ($cashRegister instanceof Response) {
            return $cashRegister;
        }

        $schoolYearId = $contextService->getCurrentSchoolYear()?->getId();
        $studentChoices = $studentRepository->findWithRemainingBalanceBySchool(
            $contextService->getCurrentSchool()->getId(),
            $schoolYearId
        );

        // Retour depuis l'imputation (« Modifier le montant ») : la saisie est reprise.
        $draftToken = (string) $request->query->get('draft', '');
        $draft = $draftToken !== '' ? $this->loadDraft($request, $draftToken) : null;
        if ($draft === null) {
            $draftToken = '';
        }

        $data = ['paymentDate' => new \DateTime(), 'paymentMethod' => 'espèces'];
        if ($draft !== null) {
            foreach ($studentChoices as $choice) {
                if ($choice->getId() === $draft['student_id']) {
                    $data['student'] = $choice;
                }
            }
            $data['amount'] = $draft['amount'];
            $data['paymentDate'] = new \DateTime($draft['payment_date']);
            $data['paymentMethod'] = $draft['payment_method'];
            $data['notes'] = $draft['notes'];
        }

        $form = $this->createForm(PaymentStartType::class, $data, [
            'student_choices' => $studentChoices,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var Student $student */
            $student = $form->get('student')->getData();
            $amount = round((float) $form->get('amount')->getData(), 2);
            $maxPayable = array_sum(array_column($this->buildOutstandingFees($student, $schoolYearId), 'remaining'));

            if ($maxPayable <= 0) {
                $form->get('student')->addError(new FormError('Cet élève n\'a aucun frais restant à payer pour l\'année en cours.'));
            } elseif ($amount > $maxPayable + 0.009) {
                $form->get('amount')->addError(new FormError(sprintf(
                    'Le montant versé ne peut pas dépasser le reste à payer de l\'élève (%s F CFA).',
                    number_format($maxPayable, 0, ',', ' ')
                )));
            } else {
                $token = $draftToken !== '' ? $draftToken : bin2hex(random_bytes(8));
                $this->saveDraft($request, $token, [
                    'student_id' => $student->getId(),
                    'amount' => $amount,
                    'payment_date' => $form->get('paymentDate')->getData()->format('Y-m-d'),
                    'payment_method' => (string) $form->get('paymentMethod')->getData(),
                    'notes' => $form->get('notes')->getData(),
                ]);

                return $this->redirectToRoute('admin_payment_imputation', ['token' => $token], Response::HTTP_SEE_OTHER);
            }
        }

        return $this->render('payment/new.html.twig', [
            'form' => $form,
        ]);
    }

    /**
     * Étape 2 : répartition du montant versé sur les frais choisis. L'enregistrement
     * n'est accepté que si la somme des imputations est égale au montant versé ; le
     * reçu s'affiche alors directement.
     */
    #[Route('/new/{token}/imputation', name: 'imputation', methods: ['GET', 'POST'], requirements: ['token' => '[a-f0-9]{16}'])]
    public function imputation(
        string $token,
        Request $request,
        EntityManagerInterface $entityManager,
        SchoolContextService $contextService,
        CashRegisterRepository $cashRegisterRepository,
        StudentRepository $studentRepository
    ): Response {
        $cashRegister = $this->requireOpenCashRegister($contextService, $cashRegisterRepository);
        if ($cashRegister instanceof Response) {
            return $cashRegister;
        }

        $draft = $this->loadDraft($request, $token);
        if ($draft === null) {
            $this->addFlash('warning', 'Cette saisie a expiré ou a déjà été enregistrée. Veuillez recommencer.');
            return $this->redirectToRoute('admin_payment_new');
        }

        $student = $studentRepository->find($draft['student_id']);
        if (!$student || $student->getSchool()?->getId() !== $contextService->getCurrentSchool()->getId()) {
            $this->saveDraft($request, $token, null);
            $this->addFlash('error', 'Élève introuvable dans l\'établissement courant.');
            return $this->redirectToRoute('admin_payment_new');
        }

        $schoolYearId = $contextService->getCurrentSchoolYear()?->getId();
        $amount = (float) $draft['amount'];
        $fees = $this->buildOutstandingFees($student, $schoolYearId);
        $feesById = array_column($fees, null, 'id');
        $submitted = [];

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('payment_imputation_' . $token, $request->request->get('_token'))) {
                $this->addFlash('error', 'Session expirée, veuillez réessayer.');
                return $this->redirectToRoute('admin_payment_imputation', ['token' => $token]);
            }

            $errors = [];
            $allocations = [];
            $total = 0.0;

            foreach ($request->request->all('allocations') as $id => $raw) {
                $raw = str_replace([' ', "\u{00A0}", "\u{202F}", ','], ['', '', '', '.'], trim((string) $raw));
                if ($raw === '') {
                    continue;
                }
                if (!is_numeric($raw)) {
                    $errors[] = 'Un montant d\'imputation est invalide.';
                    continue;
                }

                $value = round((float) $raw, 2);
                $submitted[(int) $id] = $value;
                if ($value == 0.0) {
                    continue;
                }
                if ($value < 0) {
                    $errors[] = 'Une imputation ne peut pas être négative.';
                    continue;
                }
                if (!isset($feesById[(int) $id])) {
                    $errors[] = 'Un des frais imputés n\'est plus dû par cet élève.';
                    continue;
                }

                $fee = $feesById[(int) $id];
                if ($value > $fee['remaining'] + 0.009) {
                    $errors[] = sprintf(
                        '%s : l\'imputation (%s F CFA) dépasse le reste dû (%s F CFA).',
                        $fee['name'],
                        number_format($value, 0, ',', ' '),
                        number_format($fee['remaining'], 0, ',', ' ')
                    );
                    continue;
                }

                $allocations[(int) $id] = $value;
                $total += $value;
            }

            if ($errors === [] && $allocations === []) {
                $errors[] = 'Imputez le montant versé sur au moins un frais.';
            } elseif ($errors === [] && abs($total - $amount) > 0.009) {
                $errors[] = sprintf(
                    'La somme des imputations (%s F CFA) doit être égale au montant versé (%s F CFA).',
                    number_format($total, 0, ',', ' '),
                    number_format($amount, 0, ',', ' ')
                );
            }

            if ($errors === []) {
                $payments = $this->recordImputations($entityManager, $student, $cashRegister, $draft, $allocations, $feesById);
                $this->saveDraft($request, $token, null);

                $this->addFlash('success', sprintf(
                    'Paiement de %s F CFA enregistré et imputé sur %d frais.',
                    number_format($amount, 0, ',', ' '),
                    \count($payments)
                ));

                return $this->redirectToRoute('admin_payment_receipt_view', ['id' => $payments[0]->getId(), 'new' => 1], Response::HTTP_SEE_OTHER);
            }

            foreach (array_unique($errors) as $error) {
                $this->addFlash('error', $error);
            }
        }

        $inscription = $student->getScolariteRegistration($schoolYearId);

        return $this->render('payment/imputation.html.twig', [
            'token' => $token,
            'student' => $student,
            'classroom' => $inscription?->getClassroom(),
            'draft' => $draft,
            'amount' => $amount,
            'fees' => $fees,
            'submitted' => $submitted,
            'payment_method_label' => (new Payment())->setPaymentMethod($draft['payment_method'])->getPaymentMethodLabel(),
        ]);
    }

    /**
     * Enregistre un encaissement : une ligne Payment par frais imputé, toutes sous le
     * même numéro de reçu et la même référence.
     *
     * @param array<int, float> $allocations Montant imputé par id de StudentFee
     * @param array<int, array> $feesById    Frais dus (cf. buildOutstandingFees), par id de StudentFee
     *
     * @return list<Payment>
     */
    private function recordImputations(
        EntityManagerInterface $entityManager,
        Student $student,
        CashRegister $cashRegister,
        array $draft,
        array $allocations,
        array $feesById
    ): array {
        $receiptNumber = 'REC-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
        $reference = $this->generatePaymentReference($draft['payment_method']);
        $paymentDate = new \DateTime($draft['payment_date']);
        $usedNumbers = [];
        $payments = [];

        foreach ($allocations as $studentFeeId => $value) {
            $studentFee = $feesById[$studentFeeId]['student_fee'];

            do {
                $paymentNumber = 'PAY-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
            } while (\in_array($paymentNumber, $usedNumbers, true));
            $usedNumbers[] = $paymentNumber;

            $payment = (new Payment())
                ->setPaymentNumber($paymentNumber)
                ->setReceiptNumber($receiptNumber)
                ->setReference($reference)
                ->setStudent($student)
                ->setFee($studentFee->getFee())
                ->setStudentFee($studentFee)
                ->setAmount(number_format($value, 2, '.', ''))
                ->setPaymentDate($paymentDate)
                ->setPaymentMethod($draft['payment_method'])
                // Un enregistrement au guichet est un encaissement immédiat.
                ->setStatus('payé')
                ->setCashRegister($cashRegister)
                ->setRecordedBy($this->getUser())
                ->setNotes($draft['notes']);

            $studentFee->setPaidAmount(number_format((float) $studentFee->getPaidAmount() + $value, 2, '.', ''));

            $entityManager->persist($payment);
            $payments[] = $payment;
        }

        // Un seul flush : toutes les imputations sont enregistrées ensemble, ou aucune.
        $entityManager->flush();

        return $payments;
    }

    #[Route('/students/{id}/summary', name: 'student_summary', methods: ['GET'])]
    public function studentSummary(\App\Entity\Student $student, SchoolContextService $contextService): JsonResponse
    {
        $currentSchool = $contextService->getCurrentSchool();
        if (!$currentSchool || $student->getSchool()?->getId() !== $currentSchool->getId()) {
            return new JsonResponse(['error' => 'Élève introuvable.'], Response::HTTP_NOT_FOUND);
        }

        // Situation de l'année courante (frais rattachés à l'inscription).
        $schoolYearId = $contextService->getCurrentSchoolYear()?->getId();
        $inscription = $student->getScolariteRegistration($schoolYearId);
        $classroom = $inscription?->getClassroom() ?? $student->getClassroom();

        // Plafond du montant versé = somme des restes dus, et part d'arriérés à solder en priorité.
        $outstanding = $this->buildOutstandingFees($student, $schoolYearId);
        $arriereRemaining = 0.0;
        foreach ($outstanding as $fee) {
            if ($fee['is_arriere']) {
                $arriereRemaining += $fee['remaining'];
            }
        }

        return new JsonResponse([
            'id' => $student->getId(),
            'nom' => $student->getFirstName(),
            'prenom' => $student->getLastName(),
            'classe' => $classroom?->getFullName() ?: ($classroom?->getName() ?: null),
            'montantScolarite' => $inscription ? $inscription->getTotalTuition() : $student->getTotalTuition(),
            'montantPaye' => $inscription ? $inscription->getTotalPaid() : $student->getTotalPaid(),
            'montantRestant' => $inscription ? $inscription->getRemainingTuition() : $student->getRemainingTuition(),
            'maxPayable' => array_sum(array_column($outstanding, 'remaining')),
            'arriereRemaining' => $arriereRemaining,
            'nbFrais' => \count($outstanding),
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
    public function show(Payment $payment, PaymentRepository $paymentRepository): Response
    {
        return $this->render('payment/show.html.twig', [
            'payment' => $payment,
            'receipt_lines' => $payment->getStatus() === 'payé' ? $paymentRepository->findReceiptLines($payment) : [$payment],
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
        $filename = sprintf('recu_%s.pdf', $payment->getReceiptNumber() ?: ($payment->getPaymentNumber() ?: ('payment_' . $payment->getId())));

        return new Response($paymentReceiptService->render($payment), Response::HTTP_OK, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => sprintf('inline; filename="%s"', $filename),
        ]);
    }

    /**
     * Étape 3 : le reçu (PDF intégré à la page, sans fenêtre pop-up à autoriser) avec
     * le récapitulatif des imputations de l'encaissement.
     */
    #[Route('/{id}/receipt/view', name: 'receipt_view', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function receiptView(Payment $payment, PaymentRepository $paymentRepository): Response
    {
        if ($payment->getStatus() !== 'payé') {
            $this->addFlash('warning', 'Le reçu est disponible uniquement pour les paiements encaissés.');
            return $this->redirectToRoute('admin_payment_show', ['id' => $payment->getId()], Response::HTTP_SEE_OTHER);
        }

        $lines = $paymentRepository->findReceiptLines($payment);
        $total = 0.0;
        foreach ($lines as $line) {
            $total += (float) $line->getAmount();
        }

        return $this->render('payment/receipt_view.html.twig', [
            'payment' => $payment,
            'lines' => $lines,
            'total' => $total,
            'receipt_number' => $payment->getReceiptNumber() ?? $payment->getPaymentNumber(),
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
            $studentChoices = $studentRepository->findWithRemainingBalanceBySchool(
                $currentSchool->getId(),
                $contextService->getCurrentSchoolYear()?->getId()
            );
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

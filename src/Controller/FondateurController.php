<?php

namespace App\Controller;

use App\Controller\Concern\RendersDocuments;
use App\Entity\CashDeposit;
use App\Entity\CashRegister;
use App\Entity\SchoolGroup;
use App\Entity\User;
use App\Repository\CashDepositRepository;
use App\Repository\CashRegisterRepository;
use App\Repository\PaymentRepository;
use App\Repository\RegistrationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Espace réservé au fondateur : validation des caisses, autorisation des dépenses
 * et approbation des versements.
 */
#[Route('/fondateur', name: 'fondateur_')]
#[IsGranted('ROLE_FONDATEUR')]
class FondateurController extends AbstractController
{
    use RendersDocuments;


    #[Route('/', name: 'index', methods: ['GET'])]
    public function index(
        CashRegisterRepository $cashRegisterRepository,
        CashDepositRepository $cashDepositRepository,
        PaymentRepository $paymentRepository,
        \App\Repository\DepenseRepository $depenseRepository,
        \App\Repository\ClassroomRepository $classroomRepository,
        \App\Repository\StudentRepository $studentRepository,
        \App\Service\SchoolContextService $contextService
    ): Response {
        /** @var User $user */
        $user = $this->getUser();
        $group = $user->getSchoolGroup();

        // Statistiques de l'établissement actuellement sélectionné (contexte).
        $currentSchool = $contextService->getCurrentSchool();
        $currentYear = $contextService->getCurrentSchoolYear();
        $schoolStatsScoped = $this->buildCurrentSchoolStats(
            $currentSchool,
            $currentYear,
            $classroomRepository,
            $studentRepository
        );

        // Sans groupe rattaché, on ne peut pas agréger : on retombe sur les
        // compteurs globaux et un tableau de statistiques vide.
        if ($group === null) {
            return $this->render('fondateur/index.html.twig', array_merge($schoolStatsScoped, [
                'group' => null,
                'caisses_a_valider' => $cashRegisterRepository->count(['isValidated' => false]),
                'depenses_en_attente' => $depenseRepository->countPending(),
                'versements_en_attente' => $cashDepositRepository->countByStatus('en_attente'),
                'school_stats' => [],
                'totaux' => ['revenue' => 0, 'online' => 0, 'deposits' => 0, 'expenses' => 0, 'income' => 0, 'monthly_revenue' => 0],
            ]));
        }

        // Chiffre d'affaires + paiements en ligne par établissement.
        $revenueRows = $paymentRepository->getRevenueBySchoolForGroup($group);
        $revenueBySchool = [];
        foreach ($revenueRows as $row) {
            $revenueBySchool[$row['schoolId']] = $row;
        }

        // Versements approuvés et dépenses confirmées par établissement.
        $depositsBySchool = $cashDepositRepository->getApprovedTotalsBySchoolForGroup($group);
        $expensesBySchool = $depenseRepository->getConfirmedTotalsBySchoolForGroup($group);

        // Une ligne par établissement du groupe (y compris ceux sans activité).
        $schoolStats = [];
        $totalRevenue = $totalOnline = $totalDeposits = 0.0;
        foreach ($group->getSchools() as $school) {
            $sid = $school->getId();
            $revenue = $revenueBySchool[$sid]['revenue'] ?? 0.0;
            $online = $revenueBySchool[$sid]['online'] ?? 0.0;
            $deposits = $depositsBySchool[$sid] ?? 0.0;
            $expenses = $expensesBySchool[$sid] ?? 0.0;

            $schoolStats[] = [
                'name' => $school->getName(),
                'revenue' => $revenue,
                'online' => $online,
                'deposits' => $deposits,
                'expenses' => $expenses,
            ];

            $totalRevenue += $revenue;
            $totalOnline += $online;
            $totalDeposits += $deposits;
        }

        // Classement par chiffre d'affaires décroissant.
        usort($schoolStats, static fn (array $a, array $b): int => $b['revenue'] <=> $a['revenue']);

        // Plus de transactions manuelles : « entrées » = 0, « dépenses » = total des dépenses confirmées.
        $txTotals = ['income' => 0.0, 'expense' => $depenseRepository->getConfirmedTotalForGroup($group)];

        return $this->render('fondateur/index.html.twig', array_merge($schoolStatsScoped, [
            'group' => $group,
            'caisses_a_valider' => $cashRegisterRepository->countByBooleanForGroup('isValidated', false, $group),
            'depenses_en_attente' => $depenseRepository->countPendingForGroup($group),
            'versements_en_attente' => $cashDepositRepository->countByStatusForGroup('en_attente', $group),
            'school_stats' => $schoolStats,
            'totaux' => [
                'revenue' => $totalRevenue,
                'online' => $totalOnline,
                'deposits' => $totalDeposits,
                'expenses' => $txTotals['expense'],
                'income' => $txTotals['income'],
                'monthly_revenue' => $paymentRepository->getMonthlyRevenueForGroup($group),
            ],
        ]));
    }

    /**
     * Statistiques élèves/classes de l'établissement actuellement sélectionné.
     *
     * @return array<string, mixed>
     */
    private function buildCurrentSchoolStats(
        ?\App\Entity\School $school,
        ?\App\Entity\SchoolYear $year,
        \App\Repository\ClassroomRepository $classroomRepository,
        \App\Repository\StudentRepository $studentRepository
    ): array {
        if ($school === null) {
            return [
                'current_school_name' => null,
                'classes_count' => 0,
                'students_status' => ['affecte' => 0, 'non_affecte' => 0],
                'students_gender' => ['M' => 0, 'F' => 0],
            ];
        }

        $schoolId = $school->getId();
        $yearId = $year?->getId();

        return [
            'current_school_name' => $school->getName(),
            'classes_count' => $classroomRepository->countBySchoolAndYear($schoolId, $yearId),
            'students_status' => $studentRepository->countByStatusForSchool($schoolId, $yearId),
            'students_gender' => $studentRepository->countByGenderForSchool($schoolId, $yearId),
        ];
    }

    #[Route('/validations', name: 'validations', methods: ['GET'])]
    public function validations(CashRegisterRepository $cashRegisterRepository): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $group = $user->getSchoolGroup();

        // Le fondateur ne voit que les caisses des établissements de son groupe.
        $cashRegisters = $group !== null
            ? $cashRegisterRepository->findByGroup($group)
            : $cashRegisterRepository->findBy([], ['createdAt' => 'DESC']);

        return $this->render('fondateur/validations.html.twig', [
            'cash_registers' => $cashRegisters,
        ]);
    }

    #[Route('/caisse/{id}/valider', name: 'valider_caisse', methods: ['POST'])]
    public function validerCaisse(
        Request $request,
        CashRegister $cashRegister,
        EntityManagerInterface $entityManager,
        \App\Service\NotificationService $notificationService
    ): Response {
        if ($this->isCsrfTokenValid('valider'.$cashRegister->getId(), $request->request->get('_token'))) {
            $cashRegister->setIsValidated(true)
                ->setValidatedBy($this->getUser())
                ->setValidatedAt(new \DateTime());

            // Notifier le caissier que sa caisse est validée.
            if ($cashRegister->getCashier()) {
                $notificationService->notify(
                    $cashRegister->getCashier(),
                    'Caisse validée',
                    'Votre caisse a été validée par le fondateur. Vous pouvez désormais enregistrer des paiements et effectuer des versements.',
                    $this->generateUrl('admin_cash_register_index'),
                    'fa-circle-check'
                );
            }

            $entityManager->flush();
            $this->addFlash('success', 'La caisse a été validée avec succès. Le caissier a été notifié.');
        }

        return $this->redirectToRoute('fondateur_validations');
    }

    #[Route('/caisse/{id}/annuler-validation', name: 'annuler_validation_caisse', methods: ['POST'])]
    public function annulerValidationCaisse(
        Request $request,
        CashRegister $cashRegister,
        EntityManagerInterface $entityManager,
        \App\Service\NotificationService $notificationService
    ): Response {
        if ($this->isCsrfTokenValid('annuler_validation'.$cashRegister->getId(), $request->request->get('_token'))) {
            $cashRegister->setIsValidated(false)
                ->setValidatedBy(null)
                ->setValidatedAt(null);

            // Notifier le caissier que la validation de sa caisse a été annulée.
            if ($cashRegister->getCashier()) {
                $notificationService->notify(
                    $cashRegister->getCashier(),
                    'Validation de caisse annulée',
                    'La validation de votre caisse a été annulée par le fondateur. Vous ne pouvez plus enregistrer de paiements ni effectuer de versements tant qu\'elle n\'est pas revalidée.',
                    $this->generateUrl('admin_cash_register_index'),
                    'fa-circle-exclamation'
                );
            }

            $entityManager->flush();
            $this->addFlash('success', 'La validation de la caisse a été annulée. Le caissier a été notifié.');
        }

        return $this->redirectToRoute('fondateur_validations');
    }

    /**
     * Approbation des dépenses : liste des dépenses créées par les caissiers, en
     * attente de la décision du fondateur. Une dépense n'est prise en compte (solde +
     * comptabilité) qu'une fois approuvée ici.
     */
    #[Route('/autorisations', name: 'autorisations', methods: ['GET'])]
    public function autorisations(\App\Repository\DepenseRepository $depenseRepository): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $group = $user->getSchoolGroup();

        $pending = $group !== null
            ? $depenseRepository->findPendingForGroup($group)
            : $depenseRepository->findPending();

        return $this->render('fondateur/autorisations.html.twig', [
            'depenses' => $pending,
        ]);
    }

    /**
     * Décision du fondateur sur une dépense en attente : « approuver » la confirme
     * (elle est alors déduite du solde et portée au journal comptable) ; « rejeter »
     * l'écarte définitivement (motif facultatif). Le caissier est notifié.
     */
    #[Route('/depense/{id}/{decision}', name: 'decision_depense', methods: ['POST'], requirements: ['decision' => 'approuver|rejeter'])]
    public function decisionDepense(
        Request $request,
        \App\Entity\Depense $depense,
        string $decision,
        EntityManagerInterface $entityManager,
        \App\Service\NotificationService $notificationService
    ): Response {
        if (!$this->isCsrfTokenValid('depense'.$depense->getId(), $request->request->get('_token'))) {
            return $this->redirectToRoute('fondateur_autorisations');
        }

        if (!$depense->isPending()) {
            $this->addFlash('warning', 'Cette dépense a déjà été traitée.');
            return $this->redirectToRoute('fondateur_autorisations');
        }

        $approved = $decision === 'approuver';
        $depense->setStatus($approved ? 'confirmée' : 'rejetée')
            ->setApprovedBy($this->getUser())
            ->setApprovedAt(new \DateTime())
            ->setRejectionReason($approved ? null : (trim((string) $request->request->get('reason')) ?: null));

        // Le flush déclenche l'AccountingSubscriber : l'écriture comptable n'est créée
        // que pour une dépense « confirmée » (rien pour une dépense rejetée).
        $entityManager->flush();

        // Notifier le caissier à l'origine de la dépense.
        if ($depense->getRecordedBy()) {
            $notificationService->notify(
                $depense->getRecordedBy(),
                $approved ? 'Dépense approuvée' : 'Dépense rejetée',
                sprintf(
                    'Votre dépense « %s » de %s F (%s) a été %s par le fondateur.%s',
                    $depense->getLibelle(),
                    number_format((float) $depense->getAmount(), 0, ',', ' '),
                    $depense->getNumero(),
                    $approved ? 'approuvée' : 'rejetée',
                    $approved ? ' Elle est désormais déduite du solde de la caisse.' : ($depense->getRejectionReason() ? ' Motif : '.$depense->getRejectionReason() : '')
                ),
                $this->generateUrl('admin_depense_index'),
                $approved ? 'fa-circle-check' : 'fa-circle-xmark'
            );
        }

        $this->addFlash('success', $approved
            ? 'La dépense a été approuvée. Elle est prise en compte (solde + comptabilité). Le caissier a été notifié.'
            : 'La dépense a été rejetée. Le caissier a été notifié.');

        return $this->redirectToRoute('fondateur_autorisations');
    }

    #[Route('/versements', name: 'versements', methods: ['GET'])]
    public function versements(CashDepositRepository $cashDepositRepository): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $group = $user->getSchoolGroup();

        // Le fondateur ne voit que les versements des établissements de son groupe.
        $deposits = $group !== null
            ? $cashDepositRepository->findByStatusForGroup($group)
            : $cashDepositRepository->findByStatus();

        return $this->render('fondateur/versements.html.twig', [
            'deposits' => $deposits,
        ]);
    }

    #[Route('/versement/{id}/{decision}', name: 'decision_versement', methods: ['POST'], requirements: ['decision' => 'approuver|rejeter'])]
    public function decisionVersement(
        Request $request,
        CashDeposit $deposit,
        string $decision,
        EntityManagerInterface $entityManager,
        \App\Service\NotificationService $notificationService
    ): Response {
        // Cloisonnement : un fondateur ne décide que des versements de son groupe.
        // (CashDeposit n'étant pas couvert par le garde-fou anti-IDOR global, on
        // vérifie ici son rattachement via la caisse → établissement → groupe.)
        /** @var User $user */
        $user = $this->getUser();
        $group = $user->getSchoolGroup();
        $depositGroup = $deposit->getCashRegister()?->getSchool()?->getSchoolGroup();
        if ($group !== null && $depositGroup?->getId() !== $group->getId()) {
            throw $this->createNotFoundException();
        }

        if ($this->isCsrfTokenValid('versement'.$deposit->getId(), $request->request->get('_token'))) {
            $approved = $decision === 'approuver';
            $deposit->setStatus($approved ? 'approuvé' : 'rejeté')
                ->setApprovedBy($this->getUser())
                ->setApprovedAt(new \DateTime());

            // Notifier le caissier de la décision.
            $cashier = $deposit->getCashRegister()?->getCashier();
            if ($cashier) {
                $notificationService->notify(
                    $cashier,
                    $approved ? 'Versement approuvé' : 'Versement rejeté',
                    sprintf(
                        'Votre versement de %s F (bordereau %s) a été %s par le fondateur.%s',
                        number_format((float) $deposit->getAmount(), 0, ',', ' '),
                        $deposit->getReference(),
                        $approved ? 'approuvé' : 'rejeté',
                        $approved ? '' : ' Le montant a été restitué au solde de votre caisse.'
                    ),
                    $this->generateUrl('admin_cash_register_index'),
                    $approved ? 'fa-circle-check' : 'fa-circle-xmark'
                );
            }

            $entityManager->flush();
            $this->addFlash('success', $approved
                ? 'Le versement a été approuvé. Le caissier a été notifié.'
                : 'Le versement a été rejeté (le montant est restitué au solde de la caisse). Le caissier a été notifié.');
        }

        return $this->redirectToRoute('fondateur_versements');
    }

    /**
     * Rapports du groupe : élèves inscrits par jour et paiements effectués par jour,
     * sur une période choisie (par défaut le mois en cours).
     */
    #[Route('/rapports', name: 'rapports', methods: ['GET'])]
    public function rapports(
        Request $request,
        RegistrationRepository $registrationRepository,
        PaymentRepository $paymentRepository
    ): Response {
        /** @var User $user */
        $user = $this->getUser();
        $group = $user->getSchoolGroup();

        return $this->render('fondateur/rapports.html.twig', array_merge(
            ['group' => $group],
            $this->buildRapportsData($request, $group, $registrationRepository, $paymentRepository)
        ));
    }

    /**
     * Export PDF du rapport d'inscriptions/paiements du fondateur, sur la même
     * période (et le même groupe) que la vue {@see self::rapports()}.
     */
    #[Route('/rapports/export/pdf', name: 'rapports_pdf', methods: ['GET'])]
    public function rapportsPdf(
        Request $request,
        RegistrationRepository $registrationRepository,
        PaymentRepository $paymentRepository
    ): Response {
        /** @var User $user */
        $user = $this->getUser();
        $group = $user->getSchoolGroup();

        return $this->renderPdf('fondateur/pdf/rapports_pdf.html.twig', array_merge([
            'group' => $group,
            'generated_at' => new \DateTime(),
        ], $this->buildRapportsData($request, $group, $registrationRepository, $paymentRepository)), 'rapport_fondateur_' . date('Ymd_His') . '.pdf', 'landscape');
    }

    /**
     * Données du rapport d'inscriptions/paiements du fondateur (vue et export PDF),
     * sur une période choisie (par défaut le mois en cours).
     *
     * @return array<string, mixed>
     */
    private function buildRapportsData(
        Request $request,
        ?SchoolGroup $group,
        RegistrationRepository $registrationRepository,
        PaymentRepository $paymentRepository
    ): array {
        $debut = $request->query->get('debut');
        $fin = $request->query->get('fin');

        try {
            $startDate = $debut ? new \DateTime($debut.' 00:00:00') : new \DateTime('first day of this month 00:00:00');
        } catch (\Exception) {
            $startDate = new \DateTime('first day of this month 00:00:00');
        }
        try {
            $endDate = $fin ? new \DateTime($fin.' 23:59:59') : new \DateTime('today 23:59:59');
        } catch (\Exception) {
            $endDate = new \DateTime('today 23:59:59');
        }

        // Bornes cohérentes même si l'utilisateur inverse les deux dates.
        if ($startDate > $endDate) {
            [$startDate, $endDate] = [$endDate, $startDate];
        }

        $registrations = $group !== null
            ? $registrationRepository->findByGroupAndDateRange($group, $startDate, $endDate)
            : [];
        $payments = $group !== null
            ? $paymentRepository->findByGroupAndDateRange($group, $startDate, $endDate)
            : [];

        // Regroupement par jour (clé Y-m-d), le plus récent en premier.
        $registrationsByDay = [];
        foreach ($registrations as $registration) {
            $day = $registration->getEnrolledAt()?->format('Y-m-d') ?? 'inconnu';
            $registrationsByDay[$day][] = $registration;
        }
        krsort($registrationsByDay);

        // Un versement imputé sur plusieurs frais génère plusieurs lignes Payment
        // partageant le même numéro de reçu (cf. PaymentController::recordImputations).
        // On les regroupe ici par reçu pour ne compter/afficher qu'un seul encaissement :
        // sinon un même paiement apparaît plusieurs fois dans le rapport et gonfle le total.
        $receipts = $paymentRepository->groupByReceipt($payments);

        $paymentsByDay = [];
        foreach ($receipts as $receipt) {
            $day = $receipt['date']?->format('Y-m-d') ?? 'inconnu';
            $paymentsByDay[$day]['items'][] = $receipt;
            $paymentsByDay[$day]['total'] = ($paymentsByDay[$day]['total'] ?? 0.0) + $receipt['amount'];
        }
        krsort($paymentsByDay);

        return [
            'start_date' => $startDate,
            'end_date' => $endDate,
            'registrations' => $registrations,
            'registrations_by_day' => $registrationsByDay,
            'payments' => $receipts,
            'payments_by_day' => $paymentsByDay,
            'payments_total' => array_sum(array_map(static fn (array $d): float => $d['total'], $paymentsByDay)),
        ];
    }
}

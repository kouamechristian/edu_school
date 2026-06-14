<?php

namespace App\Controller;

use App\Entity\CashDeposit;
use App\Entity\CashRegister;
use App\Entity\User;
use App\Repository\CashDepositRepository;
use App\Repository\CashRegisterRepository;
use App\Repository\FinancialTransactionRepository;
use App\Repository\PaymentRepository;
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
    #[Route('/', name: 'index', methods: ['GET'])]
    public function index(
        CashRegisterRepository $cashRegisterRepository,
        CashDepositRepository $cashDepositRepository,
        PaymentRepository $paymentRepository,
        FinancialTransactionRepository $financialTransactionRepository,
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
                'caisses_a_autoriser' => $cashRegisterRepository->count(['expenseAuthorized' => false]),
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
        $expensesBySchool = $financialTransactionRepository->getExpenseBySchoolForGroup($group);

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

        $txTotals = $financialTransactionRepository->getConfirmedTotalsForGroup($group);

        return $this->render('fondateur/index.html.twig', array_merge($schoolStatsScoped, [
            'group' => $group,
            'caisses_a_valider' => $cashRegisterRepository->countByBooleanForGroup('isValidated', false, $group),
            'caisses_a_autoriser' => $cashRegisterRepository->countByBooleanForGroup('expenseAuthorized', false, $group),
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
        return $this->render('fondateur/validations.html.twig', [
            'cash_registers' => $cashRegisterRepository->findBy([], ['createdAt' => 'DESC']),
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

    #[Route('/autorisations', name: 'autorisations', methods: ['GET'])]
    public function autorisations(CashRegisterRepository $cashRegisterRepository): Response
    {
        return $this->render('fondateur/autorisations.html.twig', [
            'cash_registers' => $cashRegisterRepository->findBy([], ['createdAt' => 'DESC']),
        ]);
    }

    #[Route('/caisse/{id}/autoriser', name: 'autoriser_depense', methods: ['POST'])]
    public function autoriserDepense(
        Request $request,
        CashRegister $cashRegister,
        EntityManagerInterface $entityManager,
        \App\Service\NotificationService $notificationService
    ): Response {
        if ($this->isCsrfTokenValid('autoriser'.$cashRegister->getId(), $request->request->get('_token'))) {
            $authorize = !$cashRegister->isExpenseAuthorized();
            $cashRegister->setExpenseAuthorized($authorize)
                ->setAuthorizedBy($authorize ? $this->getUser() : null)
                ->setAuthorizedAt($authorize ? new \DateTime() : null);

            // Notifier le caissier de la décision d'autorisation.
            if ($cashRegister->getCashier()) {
                $notificationService->notify(
                    $cashRegister->getCashier(),
                    $authorize ? 'Autorisation de dépense accordée' : 'Autorisation de dépense retirée',
                    $authorize
                        ? 'Le fondateur vous autorise désormais à effectuer des dépenses depuis votre caisse.'
                        : 'Le fondateur a retiré l\'autorisation d\'effectuer des dépenses depuis votre caisse.',
                    $this->generateUrl('admin_cash_register_index'),
                    $authorize ? 'fa-key' : 'fa-ban'
                );
            }

            $entityManager->flush();
            $this->addFlash('success', $authorize
                ? 'La caisse est désormais autorisée à effectuer des dépenses. Le caissier a été notifié.'
                : 'L\'autorisation de dépense a été retirée à la caisse. Le caissier a été notifié.');
        }

        return $this->redirectToRoute('fondateur_autorisations');
    }

    #[Route('/versements', name: 'versements', methods: ['GET'])]
    public function versements(CashDepositRepository $cashDepositRepository): Response
    {
        return $this->render('fondateur/versements.html.twig', [
            'deposits' => $cashDepositRepository->findByStatus(),
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
}

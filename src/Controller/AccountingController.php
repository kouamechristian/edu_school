<?php

namespace App\Controller;

use App\Controller\Concern\RendersDocuments;
use App\Entity\AccountingAccount;
use App\Entity\AccountingEntry;
use App\Form\AccountingAccountType;
use App\Form\AccountingEntryType;
use App\Repository\AccountingAccountRepository;
use App\Repository\AccountingEntryRepository;
use App\Service\AccountingService;
use App\Service\SchoolContextService;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Module de comptabilité (livre de caisse enrichi) : tableau de bord, journal
 * comptable, plan comptable et rapports financiers.
 */
#[Route('/admin/accounting', name: 'admin_accounting_')]
#[IsGranted('ROLE_COMPTABLE')]
class AccountingController extends AbstractController
{
    use RendersDocuments;

    public function __construct(
        private SchoolContextService $context,
        private AccountingAccountRepository $accountRepository,
        private AccountingEntryRepository $entryRepository,
        private \App\Repository\AccountingPeriodClosureRepository $closureRepository,
        private \App\Repository\BankReconciliationRepository $reconciliationRepository,
        private AccountingService $accountingService,
        private EntityManagerInterface $em,
    ) {
    }

    // ───────────────────────────── Tableau de bord ─────────────────────────────

    #[Route('/', name: 'dashboard', methods: ['GET'])]
    public function dashboard(): Response
    {
        $school = $this->context->getCurrentSchool();
        if (!$school) {
            $this->addFlash('warning', 'Veuillez sélectionner un établissement.');
            return $this->redirectToRoute('admin_student_index');
        }
        $schoolId = $school->getId();

        // Amorce silencieuse du plan comptable si l'établissement n'en a aucun.
        if ($this->accountRepository->countBySchool($schoolId) === 0) {
            $this->accountingService->ensureDefaultAccounts($school);
            $this->em->flush();
        }

        $year = $this->context->getCurrentSchoolYear();
        $from = $year?->getStartDate();
        $to = $year?->getEndDate();
        $civilYear = (int) ($to?->format('Y') ?? date('Y'));

        $totals = $this->entryRepository->totalsByType($schoolId, $from, $to);
        $result = $totals['recette'] - $totals['depense'];
        $monthly = $this->entryRepository->monthlyTotals($schoolId, $civilYear);

        return $this->render('accounting/dashboard.html.twig', [
            'current_school' => $school,
            'school_year' => $year,
            'totals' => $totals,
            'net_result' => $result,
            'cash_balance' => $totals['recette'] - $totals['depense'] - $totals['versement'],
            'top_recettes' => $this->entryRepository->totalsByAccount($schoolId, AccountingEntry::TYPE_RECETTE, $from, $to),
            'top_depenses' => $this->entryRepository->totalsByAccount($schoolId, AccountingEntry::TYPE_DEPENSE, $from, $to),
            'monthly' => $monthly,
            'civil_year' => $civilYear,
            'recent_entries' => \array_slice($this->entryRepository->findJournal($schoolId), 0, 8),
        ]);
    }

    // ───────────────────────────── Journal ─────────────────────────────

    #[Route('/journal', name: 'journal', methods: ['GET'])]
    public function journal(Request $request, PaginatorInterface $paginator): Response
    {
        $school = $this->context->getCurrentSchool();
        if (!$school) {
            $this->addFlash('warning', 'Veuillez sélectionner un établissement.');
            return $this->redirectToRoute('admin_student_index');
        }

        $entries = $this->entryRepository->findJournal($school->getId(), $this->journalFilters($request));
        $sums = $this->sumEntriesByType($entries);
        $pagination = $paginator->paginate($entries, $request->query->getInt('page', 1), 50);

        return $this->render('accounting/journal.html.twig', [
            'current_school' => $school,
            'entries' => $pagination,
            'accounts' => $this->accountRepository->findBySchool($school->getId()),
            'filters' => [
                'from' => $request->query->get('from'),
                'to' => $request->query->get('to'),
                'type' => $request->query->get('type'),
                'account' => $request->query->getInt('account'),
            ],
            'sum_recette' => $sums['recette'],
            'sum_depense' => $sums['depense'],
            'sum_versement' => $sums['versement'],
        ]);
    }

    #[Route('/journal/export/pdf', name: 'journal_pdf', methods: ['GET'])]
    public function journalPdf(Request $request): Response
    {
        $school = $this->context->getCurrentSchool();
        if (!$school) {
            return $this->redirectToRoute('admin_accounting_journal');
        }

        $entries = $this->entryRepository->findJournal($school->getId(), $this->journalFilters($request));

        return $this->renderPdf('accounting/pdf/journal_pdf.html.twig', [
            'school' => $school,
            'entries' => $entries,
            'sums' => $this->sumEntriesByType($entries),
            'from' => $this->parseDate($request->query->get('from')),
            'to' => $this->parseDate($request->query->get('to')),
            'logo_data' => $this->logoData($school),
            'generated_at' => new \DateTime(),
        ], 'journal_comptable_' . date('Ymd_His') . '.pdf', 'landscape');
    }

    #[Route('/journal/export/xlsx', name: 'journal_xlsx', methods: ['GET'])]
    public function journalXlsx(Request $request): Response
    {
        $school = $this->context->getCurrentSchool();
        if (!$school) {
            return $this->redirectToRoute('admin_accounting_journal');
        }

        $entries = $this->entryRepository->findJournal($school->getId(), $this->journalFilters($request));
        $sums = $this->sumEntriesByType($entries);

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Journal');
        $sheet->fromArray(['Référence', 'Date', 'Libellé', 'Compte', 'Type', 'Recette', 'Dépense', 'Versement', 'Méthode'], null, 'A1');
        $sheet->getStyle('A1:I1')->getFont()->setBold(true);

        $row = 2;
        foreach ($entries as $e) {
            $amount = (float) $e->getAmount();
            $sheet->fromArray([
                $e->getReference(),
                $e->getEntryDate()?->format('d/m/Y'),
                $e->getLabel(),
                $e->getAccount() ? $e->getAccount()->getCode() . ' — ' . $e->getAccount()->getName() : '',
                $e->getTypeLabel(),
                $e->getType() === AccountingEntry::TYPE_RECETTE ? $amount : null,
                $e->getType() === AccountingEntry::TYPE_DEPENSE ? $amount : null,
                $e->getType() === AccountingEntry::TYPE_VERSEMENT ? $amount : null,
                $e->getPaymentMethod(),
            ], null, 'A' . $row);
            $row++;
        }

        // Ligne de totaux.
        $sheet->setCellValue('E' . $row, 'TOTAUX');
        $sheet->setCellValue('F' . $row, $sums['recette']);
        $sheet->setCellValue('G' . $row, $sums['depense']);
        $sheet->setCellValue('H' . $row, $sums['versement']);
        $sheet->getStyle('E' . $row . ':H' . $row)->getFont()->setBold(true);

        foreach (range('A', 'I') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        return $this->streamSpreadsheet($spreadsheet, 'journal_comptable_' . date('Ymd_His') . '.xlsx');
    }

    #[Route('/entry/new', name: 'entry_new', methods: ['GET', 'POST'])]
    public function entryNew(Request $request): Response
    {
        $school = $this->context->getCurrentSchool();
        if (!$school) {
            $this->addFlash('warning', 'Veuillez sélectionner un établissement.');
            return $this->redirectToRoute('admin_accounting_dashboard');
        }

        $entry = new AccountingEntry();
        $form = $this->createForm(AccountingEntryType::class, $entry, ['school' => $school]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($entry->getEntryDate() && $this->accountingService->isDateLocked($school, $entry->getEntryDate())) {
                $this->addFlash('error', 'Cette date appartient à une période déjà clôturée : aucune écriture ne peut y être ajoutée.');
            } else {
                $entry->setSchool($school)
                    ->setSourceType(AccountingEntry::SOURCE_MANUAL)
                    ->setReference($this->accountingService->generateReference($school))
                    ->setRecordedBy($this->getUser() instanceof \App\Entity\User ? $this->getUser() : null);

                $this->em->persist($entry);
                $this->em->flush();

                $this->addFlash('success', 'Écriture ajoutée au journal.');
                return $this->redirectToRoute('admin_accounting_journal');
            }
        }

        return $this->render('accounting/entry_new.html.twig', [
            'current_school' => $school,
            'form' => $form,
            'latest_closure' => $this->accountingService->latestClosure($school),
        ]);
    }

    #[Route('/entry/{id}/delete', name: 'entry_delete', methods: ['POST'])]
    public function entryDelete(Request $request, AccountingEntry $entry): Response
    {
        $school = $this->context->getCurrentSchool();
        if (!$school || $entry->getSchool()?->getId() !== $school->getId()) {
            $this->addFlash('warning', 'Écriture introuvable pour cet établissement.');
            return $this->redirectToRoute('admin_accounting_journal');
        }

        // Seules les écritures manuelles se suppriment ici ; les autres sont pilotées
        // par leur source (paiement, dépense, versement).
        if (!$entry->isManual()) {
            $this->addFlash('warning', "Cette écriture est générée automatiquement : modifiez sa source (paiement, dépense ou versement).");
            return $this->redirectToRoute('admin_accounting_journal');
        }

        if ($entry->getEntryDate() && $this->accountingService->isDateLocked($school, $entry->getEntryDate())) {
            $this->addFlash('error', 'Écriture verrouillée : elle appartient à une période clôturée.');
            return $this->redirectToRoute('admin_accounting_journal');
        }

        if ($this->isCsrfTokenValid('delete_entry' . $entry->getId(), $request->request->get('_token'))) {
            $this->em->remove($entry);
            $this->em->flush();
            $this->addFlash('success', 'Écriture supprimée.');
        }

        return $this->redirectToRoute('admin_accounting_journal');
    }

    // ───────────────────────────── Plan comptable ─────────────────────────────

    #[Route('/accounts', name: 'accounts', methods: ['GET'])]
    public function accounts(): Response
    {
        $school = $this->context->getCurrentSchool();
        if (!$school) {
            $this->addFlash('warning', 'Veuillez sélectionner un établissement.');
            return $this->redirectToRoute('admin_student_index');
        }

        return $this->render('accounting/accounts.html.twig', [
            'current_school' => $school,
            'recettes' => $this->accountRepository->findBySchool($school->getId(), AccountingAccount::TYPE_RECETTE),
            'depenses' => $this->accountRepository->findBySchool($school->getId(), AccountingAccount::TYPE_DEPENSE),
        ]);
    }

    #[Route('/accounts/new', name: 'account_new', methods: ['GET', 'POST'])]
    #[Route('/accounts/{id}/edit', name: 'account_edit', methods: ['GET', 'POST'])]
    public function accountForm(Request $request, ?AccountingAccount $account = null): Response
    {
        $school = $this->context->getCurrentSchool();
        if (!$school) {
            $this->addFlash('warning', 'Veuillez sélectionner un établissement.');
            return $this->redirectToRoute('admin_student_index');
        }

        $isNew = $account === null;
        if ($isNew) {
            $account = (new AccountingAccount())->setSchool($school);
        } elseif ($account->getSchool()?->getId() !== $school->getId()) {
            $this->addFlash('warning', 'Compte introuvable pour cet établissement.');
            return $this->redirectToRoute('admin_accounting_accounts');
        }

        $form = $this->createForm(AccountingAccountType::class, $account);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->em->persist($account);
            $this->em->flush();
            $this->addFlash('success', $isNew ? 'Compte créé.' : 'Compte mis à jour.');
            return $this->redirectToRoute('admin_accounting_accounts');
        }

        return $this->render('accounting/account_form.html.twig', [
            'current_school' => $school,
            'form' => $form,
            'is_new' => $isNew,
            'account' => $account,
        ]);
    }

    #[Route('/accounts/{id}/delete', name: 'account_delete', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function accountDelete(Request $request, AccountingAccount $account): Response
    {
        $school = $this->context->getCurrentSchool();
        if (!$school || $account->getSchool()?->getId() !== $school->getId()) {
            $this->addFlash('warning', 'Compte introuvable pour cet établissement.');
            return $this->redirectToRoute('admin_accounting_accounts');
        }

        if ($account->isSystem()) {
            $this->addFlash('warning', 'Un compte du système ne peut pas être supprimé (désactivez-le au besoin).');
            return $this->redirectToRoute('admin_accounting_accounts');
        }

        $used = (int) $this->em->createQueryBuilder()
            ->select('COUNT(e.id)')
            ->from(AccountingEntry::class, 'e')
            ->where('e.account = :a')->setParameter('a', $account)
            ->getQuery()->getSingleScalarResult();

        if ($used > 0) {
            $this->addFlash('warning', 'Ce compte est utilisé par des écritures : désactivez-le plutôt que de le supprimer.');
            return $this->redirectToRoute('admin_accounting_accounts');
        }

        if ($this->isCsrfTokenValid('delete_account' . $account->getId(), $request->request->get('_token'))) {
            $this->em->remove($account);
            $this->em->flush();
            $this->addFlash('success', 'Compte supprimé.');
        }

        return $this->redirectToRoute('admin_accounting_accounts');
    }

    // ───────────────────────────── Rapports ─────────────────────────────

    #[Route('/reports', name: 'reports', methods: ['GET'])]
    public function reports(Request $request): Response
    {
        $school = $this->context->getCurrentSchool();
        if (!$school) {
            $this->addFlash('warning', 'Veuillez sélectionner un établissement.');
            return $this->redirectToRoute('admin_student_index');
        }
        $data = $this->buildReportData($school->getId(), $request);

        return $this->render('accounting/reports.html.twig', array_merge(['current_school' => $school], $data));
    }

    #[Route('/reports/export/pdf', name: 'reports_pdf', methods: ['GET'])]
    public function reportsPdf(Request $request): Response
    {
        $school = $this->context->getCurrentSchool();
        if (!$school) {
            return $this->redirectToRoute('admin_accounting_reports');
        }

        return $this->renderPdf('accounting/pdf/reports_pdf.html.twig', array_merge([
            'school' => $school,
            'logo_data' => $this->logoData($school),
            'generated_at' => new \DateTime(),
        ], $this->buildReportData($school->getId(), $request)), 'rapports_financiers_' . date('Ymd_His') . '.pdf');
    }

    #[Route('/reports/export/xlsx', name: 'reports_xlsx', methods: ['GET'])]
    public function reportsXlsx(Request $request): Response
    {
        $school = $this->context->getCurrentSchool();
        if (!$school) {
            return $this->redirectToRoute('admin_accounting_reports');
        }

        $data = $this->buildReportData($school->getId(), $request);

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Compte de résultat');

        $sheet->setCellValue('A1', 'COMPTE DE RÉSULTAT');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        if ($data['from'] && $data['to']) {
            $sheet->setCellValue('A2', 'Période : ' . $data['from']->format('d/m/Y') . ' → ' . $data['to']->format('d/m/Y'));
        }

        $row = 4;
        $sheet->setCellValue('A' . $row, 'RECETTES');
        $sheet->getStyle('A' . $row)->getFont()->setBold(true);
        $row++;
        foreach ($data['recettes'] as $r) {
            $sheet->setCellValue('A' . $row, trim(($r['code'] ? $r['code'] . ' — ' : '') . $r['name']));
            $sheet->setCellValue('B' . $row, $r['total']);
            $row++;
        }
        $sheet->setCellValue('A' . $row, 'Total recettes');
        $sheet->setCellValue('B' . $row, $data['total_recette']);
        $sheet->getStyle('A' . $row . ':B' . $row)->getFont()->setBold(true);
        $row += 2;

        $sheet->setCellValue('A' . $row, 'DÉPENSES');
        $sheet->getStyle('A' . $row)->getFont()->setBold(true);
        $row++;
        foreach ($data['depenses'] as $d) {
            $sheet->setCellValue('A' . $row, trim(($d['code'] ? $d['code'] . ' — ' : '') . $d['name']));
            $sheet->setCellValue('B' . $row, $d['total']);
            $row++;
        }
        $sheet->setCellValue('A' . $row, 'Total dépenses');
        $sheet->setCellValue('B' . $row, $data['total_depense']);
        $sheet->getStyle('A' . $row . ':B' . $row)->getFont()->setBold(true);
        $row += 2;

        $sheet->setCellValue('A' . $row, 'RÉSULTAT NET');
        $sheet->setCellValue('B' . $row, $data['net_result']);
        $sheet->getStyle('A' . $row . ':B' . $row)->getFont()->setBold(true);
        $row++;
        $sheet->setCellValue('A' . $row, 'Versements en banque');
        $sheet->setCellValue('B' . $row, $data['total_versement']);
        $row++;
        $sheet->setCellValue('A' . $row, 'Solde de trésorerie (caisse)');
        $sheet->setCellValue('B' . $row, $data['cash_balance']);
        $sheet->getStyle('A' . $row . ':B' . $row)->getFont()->setBold(true);

        $sheet->getStyle('B4:B' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $sheet->getColumnDimension('A')->setAutoSize(true);
        $sheet->getColumnDimension('B')->setWidth(18);

        return $this->streamSpreadsheet($spreadsheet, 'rapports_financiers_' . date('Ymd_His') . '.xlsx');
    }

    // ───────────────────────────── Grand livre ─────────────────────────────

    #[Route('/ledger', name: 'ledger', methods: ['GET'])]
    public function ledger(Request $request): Response
    {
        $school = $this->context->getCurrentSchool();
        if (!$school) {
            $this->addFlash('warning', 'Veuillez sélectionner un établissement.');
            return $this->redirectToRoute('admin_student_index');
        }

        return $this->render('accounting/ledger.html.twig', array_merge(
            [
                'current_school' => $school,
                'accounts' => $this->accountRepository->findBySchool($school->getId()),
                'filters' => [
                    'from' => $request->query->get('from'),
                    'to' => $request->query->get('to'),
                    'account' => $request->query->getInt('account'),
                ],
            ],
            $this->buildLedger($school->getId(), $request)
        ));
    }

    #[Route('/ledger/export/pdf', name: 'ledger_pdf', methods: ['GET'])]
    public function ledgerPdf(Request $request): Response
    {
        $school = $this->context->getCurrentSchool();
        if (!$school) {
            return $this->redirectToRoute('admin_accounting_ledger');
        }

        return $this->renderPdf('accounting/pdf/ledger_pdf.html.twig', array_merge([
            'school' => $school,
            'logo_data' => $this->logoData($school),
            'generated_at' => new \DateTime(),
        ], $this->buildLedger($school->getId(), $request)), 'grand_livre_' . date('Ymd_His') . '.pdf', 'landscape');
    }

    #[Route('/ledger/export/xlsx', name: 'ledger_xlsx', methods: ['GET'])]
    public function ledgerXlsx(Request $request): Response
    {
        $school = $this->context->getCurrentSchool();
        if (!$school) {
            return $this->redirectToRoute('admin_accounting_ledger');
        }

        $ledger = $this->buildLedger($school->getId(), $request);

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Grand livre');
        $sheet->fromArray(['Compte', 'Référence', 'Date', 'Libellé', 'Débit (dépense)', 'Crédit (recette)', 'Solde progressif'], null, 'A1');
        $sheet->getStyle('A1:G1')->getFont()->setBold(true);

        $row = 2;
        foreach ($ledger['groups'] as $group) {
            $accountLabel = $group['account']
                ? $group['account']->getCode() . ' — ' . $group['account']->getName()
                : 'Non ventilé (versements)';
            $sheet->setCellValue('A' . $row, $accountLabel);
            $sheet->getStyle('A' . $row)->getFont()->setBold(true);
            $row++;

            foreach ($group['rows'] as $r) {
                $entry = $r['entry'];
                $isRecette = $entry->getType() === AccountingEntry::TYPE_RECETTE;
                $sheet->fromArray([
                    '',
                    $entry->getReference(),
                    $entry->getEntryDate()?->format('d/m/Y'),
                    $entry->getLabel(),
                    $isRecette ? null : (float) $entry->getAmount(),
                    $isRecette ? (float) $entry->getAmount() : null,
                    $r['cumul'],
                ], null, 'A' . $row);
                $row++;
            }

            $sheet->setCellValue('D' . $row, 'Total ' . $accountLabel);
            $sheet->setCellValue('G' . $row, $group['total']);
            $sheet->getStyle('D' . $row . ':G' . $row)->getFont()->setBold(true);
            $row += 2;
        }

        foreach (range('A', 'G') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        return $this->streamSpreadsheet($spreadsheet, 'grand_livre_' . date('Ymd_His') . '.xlsx');
    }

    // ───────────────────────────── Clôtures de période ─────────────────────────────

    #[Route('/closures', name: 'closures', methods: ['GET'])]
    public function closures(): Response
    {
        $school = $this->context->getCurrentSchool();
        if (!$school) {
            $this->addFlash('warning', 'Veuillez sélectionner un établissement.');
            return $this->redirectToRoute('admin_student_index');
        }

        $latest = $this->accountingService->latestClosure($school);
        // Période ouverte en cours : depuis le lendemain de la dernière clôture.
        $openFrom = $latest?->getEndDate() ? (clone $latest->getEndDate())->modify('+1 day') : null;
        $openTotals = $this->entryRepository->totalsByType($school->getId(), $openFrom, new \DateTime('today'));

        return $this->render('accounting/closures.html.twig', [
            'current_school' => $school,
            'closures' => $this->closureRepository->findBySchool($school->getId()),
            'latest' => $latest,
            'open_from' => $openFrom,
            'open_recette' => $openTotals['recette'],
            'open_depense' => $openTotals['depense'],
            'open_net' => $openTotals['recette'] - $openTotals['depense'],
            'suggest_end' => (new \DateTime('today'))->format('Y-m-d'),
        ]);
    }

    #[Route('/closures/new', name: 'closure_new', methods: ['POST'])]
    public function closureNew(Request $request): Response
    {
        $school = $this->context->getCurrentSchool();
        if (!$school) {
            $this->addFlash('warning', 'Veuillez sélectionner un établissement.');
            return $this->redirectToRoute('admin_student_index');
        }

        if (!$this->isCsrfTokenValid('close_period', $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton de sécurité invalide.');
            return $this->redirectToRoute('admin_accounting_closures');
        }

        $endDate = $this->parseDate($request->request->get('end_date'));
        if ($endDate === null) {
            $this->addFlash('error', 'Veuillez indiquer une date de clôture valide.');
            return $this->redirectToRoute('admin_accounting_closures');
        }
        $endDate->setTime(0, 0, 0);

        if ($endDate > new \DateTime('today')) {
            $this->addFlash('error', 'La date de clôture ne peut pas être dans le futur.');
            return $this->redirectToRoute('admin_accounting_closures');
        }

        $latest = $this->accountingService->latestClosure($school);
        if ($latest !== null && $endDate <= $latest->getEndDate()) {
            $this->addFlash('error', sprintf(
                'La dernière clôture couvre déjà jusqu\'au %s. Choisissez une date postérieure.',
                $latest->getEndDate()->format('d/m/Y')
            ));
            return $this->redirectToRoute('admin_accounting_closures');
        }

        $actor = $this->getUser() instanceof \App\Entity\User ? $this->getUser() : null;
        $closure = $this->accountingService->closePeriod($school, $endDate, $actor, trim((string) $request->request->get('notes', '')) ?: null);
        $this->em->flush();

        $this->addFlash('success', sprintf('Période clôturée jusqu\'au %s (résultat net : %s F).',
            $endDate->format('d/m/Y'),
            number_format((float) $closure->getNetResult(), 0, ',', ' ')
        ));

        return $this->redirectToRoute('admin_accounting_closures');
    }

    #[Route('/closures/{id}/delete', name: 'closure_delete', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function closureDelete(Request $request, \App\Entity\AccountingPeriodClosure $closure): Response
    {
        $school = $this->context->getCurrentSchool();
        if (!$school || $closure->getSchool()?->getId() !== $school->getId()) {
            $this->addFlash('warning', 'Clôture introuvable pour cet établissement.');
            return $this->redirectToRoute('admin_accounting_closures');
        }

        // On ne peut rouvrir que la dernière clôture, pour préserver la chronologie.
        $latest = $this->accountingService->latestClosure($school);
        if ($latest === null || $latest->getId() !== $closure->getId()) {
            $this->addFlash('warning', 'Seule la dernière clôture peut être rouverte.');
            return $this->redirectToRoute('admin_accounting_closures');
        }

        if ($this->isCsrfTokenValid('delete_closure' . $closure->getId(), $request->request->get('_token'))) {
            $this->em->remove($closure);
            $this->em->flush();
            $this->addFlash('success', 'Dernière période rouverte (clôture annulée).');
        }

        return $this->redirectToRoute('admin_accounting_closures');
    }

    // ───────────────────────────── Rapprochement caisse / banque ─────────────────────────────

    #[Route('/reconciliation', name: 'reconciliation', methods: ['GET'])]
    public function reconciliation(Request $request): Response
    {
        $school = $this->context->getCurrentSchool();
        if (!$school) {
            $this->addFlash('warning', 'Veuillez sélectionner un établissement.');
            return $this->redirectToRoute('admin_student_index');
        }

        return $this->render('accounting/reconciliation.html.twig', array_merge(
            ['current_school' => $school, 'history' => $this->reconciliationRepository->findBySchool($school->getId())],
            $this->buildReconciliation($school, $request)
        ));
    }

    #[Route('/reconciliation/export/pdf', name: 'reconciliation_pdf', methods: ['GET'])]
    public function reconciliationPdf(Request $request): Response
    {
        $school = $this->context->getCurrentSchool();
        if (!$school) {
            return $this->redirectToRoute('admin_accounting_reconciliation');
        }

        return $this->renderPdf('accounting/pdf/reconciliation_pdf.html.twig', array_merge([
            'school' => $school,
            'logo_data' => $this->logoData($school),
            'generated_at' => new \DateTime(),
        ], $this->buildReconciliation($school, $request)), 'rapprochement_' . date('Ymd_His') . '.pdf');
    }

    /**
     * Données du rapprochement caisse/banque (vue et export PDF).
     *
     * @return array<string, mixed>
     */
    private function buildReconciliation(\App\Entity\School $school, Request $request): array
    {
        // Période des mouvements : depuis la dernière clôture (ou l'origine) jusqu'à la date de relevé.
        $latestClosure = $this->accountingService->latestClosure($school);
        $from = $latestClosure?->getEndDate() ? (clone $latestClosure->getEndDate())->modify('+1 day') : null;
        $statementDate = $this->parseDate($request->query->get('statement_date')) ?? new \DateTime('today');

        $summary = $this->entryRepository->reconciliationSummary($school->getId(), $from, $statementDate);

        // Saisies éventuelles (aperçu d'écart en direct, sans persistance).
        $statementBalance = $request->query->has('statement_balance') && $request->query->get('statement_balance') !== ''
            ? (float) str_replace(',', '.', (string) $request->query->get('statement_balance')) : null;
        $cashCounted = $request->query->has('cash_counted') && $request->query->get('cash_counted') !== ''
            ? (float) str_replace(',', '.', (string) $request->query->get('cash_counted')) : null;

        return [
            'summary' => $summary,
            'period_from' => $from,
            'statement_date' => $statementDate,
            'statement_date_str' => $statementDate->format('Y-m-d'),
            'statement_balance' => $statementBalance,
            'cash_counted' => $cashCounted,
            'bank_difference' => $statementBalance !== null ? $statementBalance - $summary['bank_theoretical'] : null,
            'cash_difference' => $cashCounted !== null ? $cashCounted - $summary['cash_theoretical'] : null,
        ];
    }

    #[Route('/reconciliation/new', name: 'reconciliation_new', methods: ['POST'])]
    public function reconciliationNew(Request $request): Response
    {
        $school = $this->context->getCurrentSchool();
        if (!$school) {
            $this->addFlash('warning', 'Veuillez sélectionner un établissement.');
            return $this->redirectToRoute('admin_student_index');
        }

        if (!$this->isCsrfTokenValid('reconcile', $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton de sécurité invalide.');
            return $this->redirectToRoute('admin_accounting_reconciliation');
        }

        $statementDate = $this->parseDate($request->request->get('statement_date'));
        if ($statementDate === null) {
            $this->addFlash('error', 'Veuillez indiquer la date du relevé bancaire.');
            return $this->redirectToRoute('admin_accounting_reconciliation');
        }
        $statementDate->setTime(0, 0, 0);

        if ($request->request->get('statement_balance', '') === '') {
            $this->addFlash('error', 'Veuillez saisir le solde du relevé bancaire.');
            return $this->redirectToRoute('admin_accounting_reconciliation');
        }
        $statementBalance = (float) str_replace(',', '.', (string) $request->request->get('statement_balance'));

        $latestClosure = $this->accountingService->latestClosure($school);
        $from = $latestClosure?->getEndDate() ? (clone $latestClosure->getEndDate())->modify('+1 day') : null;
        $summary = $this->entryRepository->reconciliationSummary($school->getId(), $from, $statementDate);

        $cashCountedRaw = $request->request->get('cash_counted', '');
        $cashCounted = $cashCountedRaw !== '' ? (float) str_replace(',', '.', (string) $cashCountedRaw) : null;

        $reconciliation = (new \App\Entity\BankReconciliation())
            ->setSchool($school)
            ->setPeriodFrom($from)
            ->setStatementDate($statementDate)
            ->setBankTheoretical($this->money($summary['bank_theoretical']))
            ->setStatementBalance($this->money($statementBalance))
            ->setBankDifference($this->money($statementBalance - $summary['bank_theoretical']))
            ->setCashTheoretical($this->money($summary['cash_theoretical']))
            ->setCashCounted($cashCounted !== null ? $this->money($cashCounted) : null)
            ->setCashDifference($cashCounted !== null ? $this->money($cashCounted - $summary['cash_theoretical']) : null)
            ->setReconciledBy($this->getUser() instanceof \App\Entity\User ? $this->getUser() : null)
            ->setNotes(trim((string) $request->request->get('notes', '')) ?: null);

        $this->em->persist($reconciliation);
        $this->em->flush();

        $this->addFlash('success', sprintf('Rapprochement enregistré au %s (écart banque : %s F).',
            $statementDate->format('d/m/Y'),
            number_format($statementBalance - $summary['bank_theoretical'], 0, ',', ' ')
        ));

        return $this->redirectToRoute('admin_accounting_reconciliation');
    }

    #[Route('/reconciliation/{id}/delete', name: 'reconciliation_delete', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function reconciliationDelete(Request $request, \App\Entity\BankReconciliation $reconciliation): Response
    {
        $school = $this->context->getCurrentSchool();
        if (!$school || $reconciliation->getSchool()?->getId() !== $school->getId()) {
            $this->addFlash('warning', 'Rapprochement introuvable pour cet établissement.');
            return $this->redirectToRoute('admin_accounting_reconciliation');
        }

        if ($this->isCsrfTokenValid('delete_reconcile' . $reconciliation->getId(), $request->request->get('_token'))) {
            $this->em->remove($reconciliation);
            $this->em->flush();
            $this->addFlash('success', 'Rapprochement supprimé.');
        }

        return $this->redirectToRoute('admin_accounting_reconciliation');
    }

    private function money(float $value): string
    {
        return number_format($value, 2, '.', '');
    }

    /**
     * Construit le grand livre : écritures regroupées par compte avec un solde
     * progressif et un total par compte.
     *
     * @return array{groups:list<array{account:?AccountingAccount,rows:list<array{entry:AccountingEntry,cumul:float}>,total:float}>,from:?\DateTimeInterface,to:?\DateTimeInterface,grand_total:float}
     */
    private function buildLedger(int $schoolId, Request $request): array
    {
        $from = $this->parseDate($request->query->get('from'));
        $to = $this->parseDate($request->query->get('to'));
        $accountId = $request->query->getInt('account') ?: null;

        $entries = $this->entryRepository->findForLedger($schoolId, [
            'from' => $from,
            'to' => $to,
            'account' => $accountId,
        ]);

        $groups = [];
        $currentKey = null;
        $running = 0.0;
        $grandTotal = 0.0;

        foreach ($entries as $entry) {
            $account = $entry->getAccount();
            $key = $account?->getId() ?? 'none';

            if ($key !== $currentKey) {
                $groups[] = ['account' => $account, 'rows' => [], 'total' => 0.0];
                $currentKey = $key;
                $running = 0.0;
            }

            $running += (float) $entry->getAmount();
            $idx = \count($groups) - 1;
            $groups[$idx]['rows'][] = ['entry' => $entry, 'cumul' => $running];
            $groups[$idx]['total'] = $running;
            $grandTotal += (float) $entry->getAmount();
        }

        return ['groups' => $groups, 'from' => $from, 'to' => $to, 'grand_total' => $grandTotal];
    }

    /**
     * Filtres du journal exploités par la vue et les exports.
     *
     * @return array{from:?\DateTimeInterface,to:?\DateTimeInterface,type:?string,account:?int}
     */
    private function journalFilters(Request $request): array
    {
        return [
            'from' => $this->parseDate($request->query->get('from')),
            'to' => $this->parseDate($request->query->get('to')),
            'type' => $request->query->get('type') ?: null,
            'account' => $request->query->getInt('account') ?: null,
        ];
    }

    /**
     * @param AccountingEntry[] $entries
     * @return array{recette:float,depense:float,versement:float}
     */
    private function sumEntriesByType(array $entries): array
    {
        $sums = ['recette' => 0.0, 'depense' => 0.0, 'versement' => 0.0];
        foreach ($entries as $e) {
            $sums[$e->getType()] = ($sums[$e->getType()] ?? 0.0) + (float) $e->getAmount();
        }

        return $sums;
    }

    /**
     * Données du compte de résultat / trésorerie pour la vue et les exports.
     *
     * @return array<string, mixed>
     */
    private function buildReportData(int $schoolId, Request $request): array
    {
        $year = $this->context->getCurrentSchoolYear();
        $from = $this->parseDate($request->query->get('from')) ?? $year?->getStartDate();
        $to = $this->parseDate($request->query->get('to')) ?? $year?->getEndDate();

        $totals = $this->entryRepository->totalsByType($schoolId, $from, $to);

        return [
            'from' => $from,
            'to' => $to,
            'recettes' => $this->entryRepository->totalsByAccount($schoolId, AccountingEntry::TYPE_RECETTE, $from, $to),
            'depenses' => $this->entryRepository->totalsByAccount($schoolId, AccountingEntry::TYPE_DEPENSE, $from, $to),
            'total_recette' => $totals['recette'],
            'total_depense' => $totals['depense'],
            'total_versement' => $totals['versement'],
            'net_result' => $totals['recette'] - $totals['depense'],
            'cash_balance' => $totals['recette'] - $totals['depense'] - $totals['versement'],
        ];
    }

    private function parseDate(?string $value): ?\DateTimeInterface
    {
        if (!$value) {
            return null;
        }
        try {
            return new \DateTime($value);
        } catch (\Exception) {
            return null;
        }
    }
}

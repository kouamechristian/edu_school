<?php

namespace App\Controller;

use App\Controller\Concern\RendersDocuments;
use App\Entity\PayrollPeriod;
use App\Entity\PayrollSettings;
use App\Entity\Payslip;
use App\Entity\SalaryComponent;
use App\Form\PayrollSettingsType;
use App\Form\SalaryComponentType;
use App\Repository\PayrollPeriodRepository;
use App\Repository\SalaryComponentRepository;
use App\Service\PayrollService;
use App\Service\SchoolContextService;
use Doctrine\ORM\EntityManagerInterface;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Module de paie (Tranche 1) : périodes, bulletins, rubriques et paramètres.
 */
#[Route('/admin/hr/payroll', name: 'admin_payroll_')]
#[IsGranted('ROLE_RH')]
class PayrollController extends AbstractController
{
    use RendersDocuments;

    public function __construct(
        private SchoolContextService $context,
        private PayrollPeriodRepository $periodRepository,
        private SalaryComponentRepository $componentRepository,
        private PayrollService $payroll,
        private EntityManagerInterface $em,
    ) {
    }

    // ───────────────────────────── Périodes ─────────────────────────────

    #[Route('/', name: 'index', methods: ['GET'])]
    public function index(): Response
    {
        $school = $this->context->getCurrentSchool();
        if (!$school) {
            $this->addFlash('warning', 'Veuillez sélectionner un établissement.');
            return $this->redirectToRoute('admin_employee_index');
        }

        return $this->render('payroll/index.html.twig', [
            'current_school' => $school,
            'periods' => $this->periodRepository->findBySchool($school->getId()),
            'months' => PayrollPeriod::MONTHS,
            'current_year' => (int) date('Y'),
            'current_month' => (int) date('n'),
        ]);
    }

    #[Route('/period/new', name: 'period_new', methods: ['POST'])]
    public function periodNew(Request $request): Response
    {
        $school = $this->context->getCurrentSchool();
        if (!$school) {
            return $this->redirectToRoute('admin_payroll_index');
        }
        if (!$this->isCsrfTokenValid('new_period', $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton de sécurité invalide.');
            return $this->redirectToRoute('admin_payroll_index');
        }

        $month = (int) $request->request->get('month');
        $year = (int) $request->request->get('year');
        if ($month < 1 || $month > 12 || $year < 2000 || $year > 2100) {
            $this->addFlash('error', 'Mois ou année invalide.');
            return $this->redirectToRoute('admin_payroll_index');
        }

        if ($this->periodRepository->findOneForMonth($school->getId(), $year, $month) !== null) {
            $this->addFlash('warning', 'Cette période de paie existe déjà.');
            return $this->redirectToRoute('admin_payroll_index');
        }

        $period = (new PayrollPeriod())->setSchool($school)->setMonth($month)->setYear($year);
        $this->em->persist($period);
        $this->payroll->generateForPeriod($period);
        $this->em->flush();

        $this->addFlash('success', 'Période créée et bulletins générés.');
        return $this->redirectToRoute('admin_payroll_period_show', ['id' => $period->getId()]);
    }

    #[Route('/period/{id}', name: 'period_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function periodShow(PayrollPeriod $period): Response
    {
        $school = $this->context->getCurrentSchool();
        if (!$school || $period->getSchool()?->getId() !== $school->getId()) {
            $this->addFlash('warning', 'Période introuvable pour cet établissement.');
            return $this->redirectToRoute('admin_payroll_index');
        }

        return $this->render('payroll/period_show.html.twig', [
            'current_school' => $school,
            'period' => $period,
            'payslips' => $period->getPayslips(),
        ]);
    }

    #[Route('/period/{id}/generate', name: 'period_generate', methods: ['POST'])]
    public function periodGenerate(Request $request, PayrollPeriod $period): Response
    {
        if (!$this->ownedDraft($period, 'generate' . $period->getId(), $request)) {
            return $this->redirectToRoute('admin_payroll_period_show', ['id' => $period->getId()]);
        }

        $count = $this->payroll->generateForPeriod($period);
        $this->em->flush();

        $this->addFlash('success', sprintf('%d bulletin(s) (re)généré(s).', $count));
        return $this->redirectToRoute('admin_payroll_period_show', ['id' => $period->getId()]);
    }

    #[Route('/period/{id}/validate', name: 'period_validate', methods: ['POST'])]
    public function periodValidate(Request $request, PayrollPeriod $period): Response
    {
        if (!$this->ownedDraft($period, 'validate' . $period->getId(), $request)) {
            return $this->redirectToRoute('admin_payroll_period_show', ['id' => $period->getId()]);
        }
        if ($period->getPayslips()->isEmpty()) {
            $this->addFlash('warning', 'Aucun bulletin à valider : générez d\'abord les bulletins.');
            return $this->redirectToRoute('admin_payroll_period_show', ['id' => $period->getId()]);
        }

        $period->setStatus(PayrollPeriod::STATUS_VALIDATED)
            ->setValidatedBy($this->getUser() instanceof \App\Entity\User ? $this->getUser() : null)
            ->setValidatedAt(new \DateTime());
        $this->em->flush();

        $this->addFlash('success', 'Période validée. Les bulletins sont figés.');
        return $this->redirectToRoute('admin_payroll_period_show', ['id' => $period->getId()]);
    }

    /**
     * Paie une période validée : génère une dépense de caisse (catégorie salaire)
     * du montant net total, ce qui alimente automatiquement le journal comptable
     * (compte DEP-SALAIRE via le souscripteur comptable).
     */
    #[Route('/period/{id}/pay', name: 'period_pay', methods: ['POST'])]
    #[IsGranted('ROLE_CAISSE')]
    public function periodPay(
        Request $request,
        PayrollPeriod $period,
        \App\Repository\CashRegisterRepository $cashRegisterRepository,
        \App\Repository\PaymentRepository $paymentRepository,
        \App\Repository\CashDepositRepository $cashDepositRepository,
        \App\Repository\DepenseRepository $depenseRepository,
    ): Response {
        $school = $this->context->getCurrentSchool();
        $cashier = $this->getUser();
        if (!$school || $period->getSchool()?->getId() !== $school->getId() || !$cashier instanceof \App\Entity\User) {
            $this->addFlash('warning', 'Période introuvable pour cet établissement.');
            return $this->redirectToRoute('admin_payroll_index');
        }
        if (!$this->isCsrfTokenValid('pay' . $period->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton de sécurité invalide.');
            return $this->redirectToRoute('admin_payroll_period_show', ['id' => $period->getId()]);
        }
        if (!$period->isValidated()) {
            $this->addFlash('warning', 'Seule une période validée (non encore payée) peut être payée.');
            return $this->redirectToRoute('admin_payroll_period_show', ['id' => $period->getId()]);
        }

        $net = (float) $period->getTotalNet();
        if ($net <= 0) {
            $this->addFlash('warning', 'Le net à payer de la période est nul.');
            return $this->redirectToRoute('admin_payroll_period_show', ['id' => $period->getId()]);
        }

        // La dépense passe par la caisse ouverte et autorisée du caissier courant.
        $cashRegister = $cashRegisterRepository->findOpenForCashier($school, $cashier);
        if (!$cashRegister) {
            $this->addFlash('warning', 'Vous devez ouvrir votre caisse avant de payer les salaires.');
            return $this->redirectToRoute('admin_cash_register_index');
        }
        if (!$cashRegister->isExpenseAuthorized()) {
            $this->addFlash('warning', 'Le fondateur ne vous a pas autorisé à effectuer des dépenses depuis votre caisse.');
            return $this->redirectToRoute('admin_cash_register_index');
        }

        $method = (string) $request->request->get('payment_method', 'virement');
        if (!\in_array($method, ['espèces', 'chèque', 'virement', 'carte', 'mobile_money'], true)) {
            $method = 'virement';
        }

        $depense = (new \App\Entity\Depense())
            ->setCashRegister($cashRegister)
            ->setSchool($school)
            ->setRecordedBy($cashier)
            ->setCategory('salaire')
            ->setLibelle('Salaires ' . $period->getLabel())
            ->setAmount(number_format($net, 2, '.', ''))
            ->setPaymentMethod($method)
            ->setDepenseDate(new \DateTime())
            ->setNumero($this->nextDepenseNumero())
            ->setStatus('confirmée')
            ->setDescription(sprintf('Paiement des salaires — période %s (%d bulletin(s)).', $period->getLabel(), $period->getPayslips()->count()));

        $this->em->persist($depense);

        $period->setStatus(PayrollPeriod::STATUS_PAID)
            ->setPaidAt(new \DateTime())
            ->setPaymentMethod($method)
            ->setPaymentDepense($depense);

        // Répercute le mode de paiement sur les bulletins (traçabilité).
        foreach ($period->getPayslips() as $payslip) {
            $payslip->setPaymentMethod($method);
        }

        $this->em->flush();

        $this->addFlash('success', sprintf(
            'Salaires payés : dépense de %s F enregistrée en caisse (catégorie salaire) et portée au journal comptable.',
            number_format($net, 0, ',', ' ')
        ));

        return $this->redirectToRoute('admin_payroll_period_show', ['id' => $period->getId()]);
    }

    /**
     * Annule le paiement d'une période : la dépense de caisse est annulée (ce qui
     * retire l'écriture comptable) et la période repasse à « validée ».
     */
    #[Route('/period/{id}/cancel-payment', name: 'period_cancel_payment', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function periodCancelPayment(Request $request, PayrollPeriod $period): Response
    {
        $school = $this->context->getCurrentSchool();
        if (!$school || $period->getSchool()?->getId() !== $school->getId()) {
            $this->addFlash('warning', 'Période introuvable pour cet établissement.');
            return $this->redirectToRoute('admin_payroll_index');
        }
        if (!$this->isCsrfTokenValid('cancelpay' . $period->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton de sécurité invalide.');
            return $this->redirectToRoute('admin_payroll_period_show', ['id' => $period->getId()]);
        }
        if (!$period->isPaid()) {
            $this->addFlash('warning', 'Cette période n\'est pas au statut « payée ».');
            return $this->redirectToRoute('admin_payroll_period_show', ['id' => $period->getId()]);
        }

        $depense = $period->getPaymentDepense();
        if ($depense !== null) {
            $depense->setStatus('annulée'); // le souscripteur comptable retire l'écriture DEP-SALAIRE
        }

        $period->setStatus(PayrollPeriod::STATUS_VALIDATED)
            ->setPaidAt(null)
            ->setPaymentDepense(null);

        $this->em->flush();

        $this->addFlash('success', 'Paiement annulé : la dépense de caisse a été annulée et la période repasse en « validée ».');
        return $this->redirectToRoute('admin_payroll_period_show', ['id' => $period->getId()]);
    }

    /**
     * Numéro de dépense unique : DEP-AAAAMMJJ-0001 (même convention que le module Dépenses).
     */
    private function nextDepenseNumero(): string
    {
        $prefix = 'DEP-' . date('Ymd') . '-';
        $count = (int) $this->em->createQueryBuilder()
            ->select('COUNT(d.id)')
            ->from(\App\Entity\Depense::class, 'd')
            ->where('d.numero LIKE :prefix')
            ->setParameter('prefix', $prefix . '%')
            ->getQuery()
            ->getSingleScalarResult();

        return $prefix . str_pad((string) ($count + 1), 4, '0', STR_PAD_LEFT);
    }

    #[Route('/period/{id}/delete', name: 'period_delete', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function periodDelete(Request $request, PayrollPeriod $period): Response
    {
        $school = $this->context->getCurrentSchool();
        if (!$school || $period->getSchool()?->getId() !== $school->getId()) {
            return $this->redirectToRoute('admin_payroll_index');
        }
        if (!$period->isDraft()) {
            $this->addFlash('warning', 'Seule une période en brouillon peut être supprimée.');
            return $this->redirectToRoute('admin_payroll_period_show', ['id' => $period->getId()]);
        }
        if ($this->isCsrfTokenValid('delete_period' . $period->getId(), $request->request->get('_token'))) {
            $this->em->remove($period);
            $this->em->flush();
            $this->addFlash('success', 'Période supprimée.');
        }

        return $this->redirectToRoute('admin_payroll_index');
    }

    // ───────────────────────────── Bulletins ─────────────────────────────

    #[Route('/payslip/{id}', name: 'payslip_show', methods: ['GET'])]
    public function payslipShow(Payslip $payslip): Response
    {
        if (!$this->ownsPayslip($payslip)) {
            $this->addFlash('warning', 'Bulletin introuvable pour cet établissement.');
            return $this->redirectToRoute('admin_payroll_index');
        }

        return $this->render('payroll/payslip_show.html.twig', [
            'current_school' => $this->context->getCurrentSchool(),
            'payslip' => $payslip,
        ]);
    }

    #[Route('/payslip/{id}/pdf', name: 'payslip_pdf', methods: ['GET'])]
    public function payslipPdf(Payslip $payslip): Response
    {
        if (!$this->ownsPayslip($payslip)) {
            return $this->redirectToRoute('admin_payroll_index');
        }
        $school = $this->context->getCurrentSchool();

        return $this->renderPdf('payroll/pdf/payslip_pdf.html.twig', [
            'school' => $school,
            'payslip' => $payslip,
            'logo_data' => $this->logoData($school),
            'generated_at' => new \DateTime(),
        ], 'bulletin_' . $payslip->getReference() . '.pdf');
    }

    /**
     * Ajoute une ligne manuelle (prime ou retenue ponctuelle) sur un bulletin, tant
     * que la période est en brouillon. Les totaux sont recalculés à partir des lignes.
     */
    #[Route('/payslip/{id}/line/add', name: 'payslip_line_add', methods: ['POST'])]
    public function payslipLineAdd(Request $request, Payslip $payslip): Response
    {
        if (!$this->ownsPayslip($payslip)) {
            return $this->redirectToRoute('admin_payroll_index');
        }
        if (!$payslip->getPeriod()?->isDraft()) {
            $this->addFlash('warning', 'Le bulletin n\'est modifiable que tant que la période est en brouillon.');
            return $this->redirectToRoute('admin_payroll_payslip_show', ['id' => $payslip->getId()]);
        }
        if (!$this->isCsrfTokenValid('addline' . $payslip->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton de sécurité invalide.');
            return $this->redirectToRoute('admin_payroll_payslip_show', ['id' => $payslip->getId()]);
        }

        $kind = $request->request->get('kind') === 'deduction' ? \App\Entity\PayslipLine::KIND_DEDUCTION : \App\Entity\PayslipLine::KIND_GAIN;
        $label = trim((string) $request->request->get('label', ''));
        $amount = (float) str_replace(',', '.', (string) $request->request->get('amount', '0'));

        if ($label === '' || $amount <= 0) {
            $this->addFlash('error', 'Libellé et montant (positif) sont obligatoires.');
            return $this->redirectToRoute('admin_payroll_payslip_show', ['id' => $payslip->getId()]);
        }

        $line = (new \App\Entity\PayslipLine())
            ->setKind($kind)
            ->setCode('MANUAL')
            ->setLabel($label)
            ->setAmount(number_format($amount, 2, '.', ''))
            ->setSortOrder($kind === \App\Entity\PayslipLine::KIND_GAIN ? 190 : 290);
        $payslip->addLine($line);
        $this->em->persist($line);

        $this->recomputePayslipFromLines($payslip);
        $this->recomputePeriodTotals($payslip->getPeriod());
        $this->em->flush();

        $this->addFlash('success', 'Ligne ajoutée au bulletin.');
        return $this->redirectToRoute('admin_payroll_payslip_show', ['id' => $payslip->getId()]);
    }

    #[Route('/payslip/{payslipId}/line/{lineId}/delete', name: 'payslip_line_delete', methods: ['POST'])]
    public function payslipLineDelete(Request $request, int $payslipId, int $lineId): Response
    {
        $payslip = $this->em->getRepository(Payslip::class)->find($payslipId);
        $line = $this->em->getRepository(\App\Entity\PayslipLine::class)->find($lineId);
        if (!$payslip || !$line || $line->getPayslip()?->getId() !== $payslip->getId() || !$this->ownsPayslip($payslip)) {
            $this->addFlash('warning', 'Ligne introuvable.');
            return $this->redirectToRoute('admin_payroll_index');
        }
        if (!$payslip->getPeriod()?->isDraft()) {
            $this->addFlash('warning', 'Le bulletin n\'est modifiable que tant que la période est en brouillon.');
            return $this->redirectToRoute('admin_payroll_payslip_show', ['id' => $payslip->getId()]);
        }
        if ($line->getCode() !== 'MANUAL') {
            $this->addFlash('warning', 'Seules les lignes ajoutées manuellement peuvent être supprimées.');
            return $this->redirectToRoute('admin_payroll_payslip_show', ['id' => $payslip->getId()]);
        }
        if (!$this->isCsrfTokenValid('delline' . $line->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton de sécurité invalide.');
            return $this->redirectToRoute('admin_payroll_payslip_show', ['id' => $payslip->getId()]);
        }

        $payslip->removeLine($line);
        $this->em->remove($line);
        $this->recomputePayslipFromLines($payslip);
        $this->recomputePeriodTotals($payslip->getPeriod());
        $this->em->flush();

        $this->addFlash('success', 'Ligne supprimée.');
        return $this->redirectToRoute('admin_payroll_payslip_show', ['id' => $payslip->getId()]);
    }

    /** Recalcule brut / retenues / net d'un bulletin à partir de ses lignes. */
    private function recomputePayslipFromLines(Payslip $payslip): void
    {
        $gross = 0.0;
        $deductions = 0.0;
        foreach ($payslip->getLines() as $l) {
            if ($l->isGain()) {
                $gross += (float) $l->getAmount();
            } else {
                $deductions += (float) $l->getAmount();
            }
        }
        $payslip->setGrossTotal(number_format($gross, 2, '.', ''))
            ->setTotalDeductions(number_format($deductions, 2, '.', ''))
            ->setNetPay(number_format($gross - $deductions, 2, '.', ''));
    }

    /** Recalcule les cumuls d'une période à partir de ses bulletins. */
    private function recomputePeriodTotals(PayrollPeriod $period): void
    {
        $g = $n = $d = $e = 0.0;
        foreach ($period->getPayslips() as $ps) {
            $g += (float) $ps->getGrossTotal();
            $n += (float) $ps->getNetPay();
            $d += (float) $ps->getTotalDeductions();
            $e += (float) $ps->getEmployerTotal();
        }
        $period->setTotalGross(number_format($g, 2, '.', ''))
            ->setTotalNet(number_format($n, 2, '.', ''))
            ->setTotalDeductions(number_format($d, 2, '.', ''))
            ->setTotalEmployer(number_format($e, 2, '.', ''));
    }

    #[Route('/period/{id}/book/pdf', name: 'book_pdf', methods: ['GET'])]
    public function bookPdf(PayrollPeriod $period): Response
    {
        $school = $this->context->getCurrentSchool();
        if (!$school || $period->getSchool()?->getId() !== $school->getId()) {
            return $this->redirectToRoute('admin_payroll_index');
        }

        return $this->renderPdf('payroll/pdf/book_pdf.html.twig', [
            'school' => $school,
            'period' => $period,
            'payslips' => $period->getPayslips(),
            'logo_data' => $this->logoData($school),
            'generated_at' => new \DateTime(),
        ], 'livre_paie_' . $period->getYear() . sprintf('%02d', $period->getMonth()) . '.pdf', 'landscape');
    }

    #[Route('/period/{id}/book/xlsx', name: 'book_xlsx', methods: ['GET'])]
    public function bookXlsx(PayrollPeriod $period): Response
    {
        $school = $this->context->getCurrentSchool();
        if (!$school || $period->getSchool()?->getId() !== $school->getId()) {
            return $this->redirectToRoute('admin_payroll_index');
        }

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Livre de paie');
        $sheet->fromArray(['N°', 'Employé', 'Type', 'Salaire base', 'Brut', 'CNPS', 'ITS', 'Autres retenues', 'Total retenues', 'Net à payer', 'Charges patronales'], null, 'A1');
        $sheet->getStyle('A1:K1')->getFont()->setBold(true);

        $row = 2;
        $i = 0;
        foreach ($period->getPayslips() as $ps) {
            $i++;
            $sheet->fromArray([
                $i,
                $ps->getEmployee()?->getFullName(),
                $ps->getEmployee()?->getEmployeeTypeLabel(),
                (float) $ps->getBaseSalary(),
                (float) $ps->getGrossTotal(),
                (float) $ps->getCnpsEmployee(),
                (float) $ps->getIts(),
                (float) $ps->getOtherDeductions(),
                (float) $ps->getTotalDeductions(),
                (float) $ps->getNetPay(),
                (float) $ps->getEmployerTotal(),
            ], null, 'A' . $row);
            $row++;
        }

        $sheet->setCellValue('C' . $row, 'TOTAUX');
        $sheet->setCellValue('E' . $row, (float) $period->getTotalGross());
        $sheet->setCellValue('I' . $row, (float) $period->getTotalDeductions());
        $sheet->setCellValue('J' . $row, (float) $period->getTotalNet());
        $sheet->setCellValue('K' . $row, (float) $period->getTotalEmployer());
        $sheet->getStyle('A' . $row . ':K' . $row)->getFont()->setBold(true);

        foreach (range('A', 'K') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        return $this->streamSpreadsheet($spreadsheet, 'livre_paie_' . $period->getYear() . sprintf('%02d', $period->getMonth()) . '.xlsx');
    }

    // ───────────────────────────── État des cotisations ─────────────────────────────

    #[Route('/period/{id}/cotisations', name: 'cotisations', methods: ['GET'])]
    public function cotisations(PayrollPeriod $period): Response
    {
        $school = $this->context->getCurrentSchool();
        if (!$school || $period->getSchool()?->getId() !== $school->getId()) {
            $this->addFlash('warning', 'Période introuvable pour cet établissement.');
            return $this->redirectToRoute('admin_payroll_index');
        }

        return $this->render('payroll/cotisations.html.twig', array_merge(
            ['current_school' => $school, 'period' => $period],
            $this->buildCotisations($period)
        ));
    }

    #[Route('/period/{id}/cotisations/pdf', name: 'cotisations_pdf', methods: ['GET'])]
    public function cotisationsPdf(PayrollPeriod $period): Response
    {
        $school = $this->context->getCurrentSchool();
        if (!$school || $period->getSchool()?->getId() !== $school->getId()) {
            return $this->redirectToRoute('admin_payroll_index');
        }

        return $this->renderPdf('payroll/pdf/cotisations_pdf.html.twig', array_merge([
            'school' => $school,
            'period' => $period,
            'logo_data' => $this->logoData($school),
            'generated_at' => new \DateTime(),
        ], $this->buildCotisations($period)), 'cotisations_' . $period->getYear() . sprintf('%02d', $period->getMonth()) . '.pdf');
    }

    #[Route('/period/{id}/cotisations/xlsx', name: 'cotisations_xlsx', methods: ['GET'])]
    public function cotisationsXlsx(PayrollPeriod $period): Response
    {
        $school = $this->context->getCurrentSchool();
        if (!$school || $period->getSchool()?->getId() !== $school->getId()) {
            return $this->redirectToRoute('admin_payroll_index');
        }
        $data = $this->buildCotisations($period);
        $sum = $data['sum'];

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Cotisations');

        $sheet->setCellValue('A1', 'ÉTAT DES COTISATIONS À REVERSER — ' . $period->getLabel());
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(13);

        // Récapitulatif par organisme.
        $sheet->fromArray(['Organisme', 'Montant'], null, 'A3');
        $sheet->getStyle('A3:B3')->getFont()->setBold(true);
        $sheet->fromArray([
            ['CNPS (salarial + patronal + prestations + accident)', $data['cnps_total']],
            ['CMU (salarial + employeur)', $data['cmu_total']],
            ['ITS (DGI)', $data['its_total']],
            ['TOTAL À REVERSER', $data['grand_total']],
        ], null, 'A4');
        $sheet->getStyle('A7:B7')->getFont()->setBold(true);

        // Détail par employé.
        $headRow = 10;
        $sheet->fromArray(['Employé', 'CNPS salarial', 'CNPS patronal', 'Prestations fam.', 'Accident travail', 'CMU salarial', 'CMU employeur', 'ITS'], null, 'A' . $headRow);
        $sheet->getStyle('A' . $headRow . ':H' . $headRow)->getFont()->setBold(true);

        $row = $headRow + 1;
        foreach ($data['payslips'] as $ps) {
            $sheet->fromArray([
                $ps->getEmployee()?->getFullName(),
                (float) $ps->getCnpsEmployee(),
                (float) $ps->getCnpsEmployer(),
                (float) $ps->getFamilyBenefit(),
                (float) $ps->getWorkAccident(),
                (float) $ps->getCmuEmployee(),
                (float) $ps->getCmuEmployer(),
                (float) $ps->getIts(),
            ], null, 'A' . $row);
            $row++;
        }

        $sheet->setCellValue('A' . $row, 'TOTAUX');
        $sheet->setCellValue('B' . $row, $sum['cnps_employee']);
        $sheet->setCellValue('C' . $row, $sum['cnps_employer']);
        $sheet->setCellValue('D' . $row, $sum['family_benefit']);
        $sheet->setCellValue('E' . $row, $sum['work_accident']);
        $sheet->setCellValue('F' . $row, $sum['cmu_employee']);
        $sheet->setCellValue('G' . $row, $sum['cmu_employer']);
        $sheet->setCellValue('H' . $row, $sum['its']);
        $sheet->getStyle('A' . $row . ':H' . $row)->getFont()->setBold(true);

        $sheet->getStyle('B4:B7')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        foreach (range('A', 'H') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        return $this->streamSpreadsheet($spreadsheet, 'cotisations_' . $period->getYear() . sprintf('%02d', $period->getMonth()) . '.xlsx');
    }

    /**
     * Agrège les cotisations d'une période pour l'état à reverser (CNPS, CMU, ITS).
     *
     * @return array<string, mixed>
     */
    private function buildCotisations(PayrollPeriod $period): array
    {
        $sum = [
            'cnps_employee' => 0.0, 'cnps_employer' => 0.0, 'family_benefit' => 0.0,
            'work_accident' => 0.0, 'cmu_employee' => 0.0, 'cmu_employer' => 0.0, 'its' => 0.0,
        ];
        $rows = [];
        foreach ($period->getPayslips() as $ps) {
            $rows[] = $ps;
            $sum['cnps_employee'] += (float) $ps->getCnpsEmployee();
            $sum['cnps_employer'] += (float) $ps->getCnpsEmployer();
            $sum['family_benefit'] += (float) $ps->getFamilyBenefit();
            $sum['work_accident'] += (float) $ps->getWorkAccident();
            $sum['cmu_employee'] += (float) $ps->getCmuEmployee();
            $sum['cmu_employer'] += (float) $ps->getCmuEmployer();
            $sum['its'] += (float) $ps->getIts();
        }

        // Regroupements à reverser par organisme.
        $cnpsTotal = $sum['cnps_employee'] + $sum['cnps_employer'] + $sum['family_benefit'] + $sum['work_accident'];
        $cmuTotal = $sum['cmu_employee'] + $sum['cmu_employer'];

        return [
            'payslips' => $rows,
            'sum' => $sum,
            'cnps_total' => $cnpsTotal,
            'cmu_total' => $cmuTotal,
            'its_total' => $sum['its'],
            'grand_total' => $cnpsTotal + $cmuTotal + $sum['its'],
        ];
    }

    // ───────────────────────────── Historique par employé ─────────────────────────────

    #[Route('/employee/{id}/history', name: 'employee_history', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function employeeHistory(\App\Entity\Employee $employee, \App\Repository\PayslipRepository $payslipRepository): Response
    {
        $school = $this->context->getCurrentSchool();
        if (!$school) {
            $this->addFlash('warning', 'Veuillez sélectionner un établissement.');
            return $this->redirectToRoute('admin_employee_index');
        }

        $belongs = false;
        foreach ($employee->getSchools() as $s) {
            if ($s->getId() === $school->getId()) {
                $belongs = true;
                break;
            }
        }
        if (!$belongs) {
            $this->addFlash('warning', 'Employé introuvable pour cet établissement.');
            return $this->redirectToRoute('admin_employee_index');
        }

        $payslips = $payslipRepository->findByEmployee($employee->getId());
        $totalGross = 0.0;
        $totalNet = 0.0;
        foreach ($payslips as $ps) {
            $totalGross += (float) $ps->getGrossTotal();
            $totalNet += (float) $ps->getNetPay();
        }

        return $this->render('payroll/employee_history.html.twig', [
            'current_school' => $school,
            'employee' => $employee,
            'payslips' => $payslips,
            'total_gross' => $totalGross,
            'total_net' => $totalNet,
        ]);
    }

    // ───────────────────────────── Rubriques ─────────────────────────────

    #[Route('/components', name: 'components', methods: ['GET'])]
    public function components(): Response
    {
        $school = $this->context->getCurrentSchool();
        if (!$school) {
            return $this->redirectToRoute('admin_employee_index');
        }
        $this->payroll->ensureDefaultComponents($school);
        $this->em->flush();

        return $this->render('payroll/components.html.twig', [
            'current_school' => $school,
            'components' => $this->componentRepository->findBySchool($school->getId()),
        ]);
    }

    #[Route('/components/new', name: 'component_new', methods: ['GET', 'POST'])]
    #[Route('/components/{id}/edit', name: 'component_edit', methods: ['GET', 'POST'])]
    public function componentForm(Request $request, ?SalaryComponent $component = null): Response
    {
        $school = $this->context->getCurrentSchool();
        if (!$school) {
            return $this->redirectToRoute('admin_employee_index');
        }
        $isNew = $component === null;
        if ($isNew) {
            $component = (new SalaryComponent())->setSchool($school);
        } elseif ($component->getSchool()?->getId() !== $school->getId()) {
            $this->addFlash('warning', 'Rubrique introuvable pour cet établissement.');
            return $this->redirectToRoute('admin_payroll_components');
        }

        $form = $this->createForm(SalaryComponentType::class, $component);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $this->em->persist($component);
            $this->em->flush();
            $this->addFlash('success', $isNew ? 'Rubrique créée.' : 'Rubrique mise à jour.');
            return $this->redirectToRoute('admin_payroll_components');
        }

        return $this->render('payroll/component_form.html.twig', [
            'current_school' => $school,
            'form' => $form,
            'is_new' => $isNew,
            'component' => $component,
        ]);
    }

    #[Route('/components/{id}/delete', name: 'component_delete', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function componentDelete(Request $request, SalaryComponent $component): Response
    {
        $school = $this->context->getCurrentSchool();
        if (!$school || $component->getSchool()?->getId() !== $school->getId()) {
            return $this->redirectToRoute('admin_payroll_components');
        }
        if ($this->isCsrfTokenValid('delete_component' . $component->getId(), $request->request->get('_token'))) {
            $this->em->remove($component);
            $this->em->flush();
            $this->addFlash('success', 'Rubrique supprimée.');
        }

        return $this->redirectToRoute('admin_payroll_components');
    }

    // ───────────────────────────── Paramètres ─────────────────────────────

    #[Route('/settings', name: 'settings', methods: ['GET', 'POST'])]
    public function settings(Request $request): Response
    {
        $school = $this->context->getCurrentSchool();
        if (!$school) {
            return $this->redirectToRoute('admin_employee_index');
        }
        $settings = $this->payroll->ensureSettings($school);

        $form = $this->createForm(PayrollSettingsType::class, $settings);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            // Barème ITS depuis la zone de texte (une tranche par ligne : de;à;taux).
            $raw = trim((string) $request->request->get('its_brackets', ''));
            if ($raw !== '') {
                $brackets = [];
                foreach (preg_split('/\r?\n/', $raw) as $line) {
                    $line = trim($line);
                    if ($line === '') {
                        continue;
                    }
                    $parts = array_map('trim', explode(';', $line));
                    $from = (float) str_replace(' ', '', $parts[0] ?? '0');
                    $toRaw = $parts[1] ?? '';
                    $to = ($toRaw === '' || strtolower($toRaw) === 'inf') ? null : (float) str_replace(' ', '', $toRaw);
                    $rate = (float) str_replace(',', '.', $parts[2] ?? '0');
                    $brackets[] = ['from' => $from, 'to' => $to, 'rate' => $rate];
                }
                if ($brackets !== []) {
                    $settings->setItsBrackets($brackets);
                }
            }
            $this->em->flush();
            $this->addFlash('success', 'Paramètres de paie enregistrés.');
            return $this->redirectToRoute('admin_payroll_settings');
        }

        return $this->render('payroll/settings.html.twig', [
            'current_school' => $school,
            'form' => $form,
            'settings' => $settings,
        ]);
    }

    // ───────────────────────────── Helpers ─────────────────────────────

    private function ownedDraft(PayrollPeriod $period, string $token, Request $request): bool
    {
        $school = $this->context->getCurrentSchool();
        if (!$school || $period->getSchool()?->getId() !== $school->getId()) {
            $this->addFlash('warning', 'Période introuvable pour cet établissement.');
            return false;
        }
        if (!$period->isDraft()) {
            $this->addFlash('warning', 'Action impossible : la période n\'est plus en brouillon.');
            return false;
        }
        if (!$this->isCsrfTokenValid($token, $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton de sécurité invalide.');
            return false;
        }

        return true;
    }

    private function ownsPayslip(Payslip $payslip): bool
    {
        $school = $this->context->getCurrentSchool();

        return $school && $payslip->getPeriod()?->getSchool()?->getId() === $school->getId();
    }
}

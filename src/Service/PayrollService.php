<?php

namespace App\Service;

use App\Entity\Contract;
use App\Entity\Employee;
use App\Entity\PayrollPeriod;
use App\Entity\PayrollSettings;
use App\Entity\Payslip;
use App\Entity\PayslipLine;
use App\Entity\SalaryComponent;
use App\Entity\School;
use App\Repository\EmployeeRepository;
use App\Repository\PayrollSettingsRepository;
use App\Repository\SalaryComponentRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Moteur de paie (Tranche 1) : paramètres, rubriques et génération/calcul des
 * bulletins. Aucun taux codé en dur : tout provient de {@see PayrollSettings} et
 * des {@see SalaryComponent}. Barème par défaut inspiré de la Côte d'Ivoire.
 */
class PayrollService
{
    public function __construct(
        private EntityManagerInterface $em,
        private PayrollSettingsRepository $settingsRepository,
        private SalaryComponentRepository $componentRepository,
        private EmployeeRepository $employeeRepository,
    ) {
    }

    // ───────────────────────── Paramètres & rubriques ─────────────────────────

    public function ensureSettings(School $school): PayrollSettings
    {
        $settings = $this->settingsRepository->findForSchool((int) $school->getId());
        if ($settings === null) {
            $settings = (new PayrollSettings())->setSchool($school);
            $this->em->persist($settings);
        }

        return $settings;
    }

    /**
     * Crée quelques rubriques par défaut si l'établissement n'en a aucune.
     */
    public function ensureDefaultComponents(School $school): int
    {
        if ($this->componentRepository->countBySchool((int) $school->getId()) > 0) {
            return 0;
        }

        $defaults = [
            ['TRANSPORT', 'Prime de transport', SalaryComponent::DIRECTION_GAIN, SalaryComponent::MODE_FIXED, '30000.00', '0.000', false, false, 10],
            ['LOGEMENT', 'Indemnité de logement', SalaryComponent::DIRECTION_GAIN, SalaryComponent::MODE_FIXED, '0.00', '0.000', true, true, 20],
            ['PRIME', 'Prime / gratification', SalaryComponent::DIRECTION_GAIN, SalaryComponent::MODE_FIXED, '0.00', '0.000', true, true, 30],
            ['AVANCE', 'Avance sur salaire', SalaryComponent::DIRECTION_RETENUE, SalaryComponent::MODE_FIXED, '0.00', '0.000', false, false, 10],
        ];

        $count = 0;
        foreach ($defaults as [$code, $name, $dir, $mode, $amount, $rate, $taxable, $cnps, $order]) {
            $component = (new SalaryComponent())
                ->setSchool($school)
                ->setCode($code)
                ->setName($name)
                ->setDirection($dir)
                ->setCalcMode($mode)
                ->setBase(SalaryComponent::BASE_SALARY)
                ->setAmount($amount)
                ->setRate($rate)
                ->setTaxable($taxable)
                ->setCnpsSubject($cnps)
                ->setSortOrder($order)
                ->setIsSystem(false);
            $this->em->persist($component);
            $count++;
        }

        return $count;
    }

    // ───────────────────────── Génération des bulletins ─────────────────────────

    /**
     * (Re)génère tous les bulletins d'une période (à l'état brouillon uniquement).
     * Ne flush pas.
     */
    public function generateForPeriod(PayrollPeriod $period): int
    {
        $school = $period->getSchool();
        if ($school === null) {
            return 0;
        }
        $settings = $this->ensureSettings($school);
        $this->ensureDefaultComponents($school);
        $components = $this->componentRepository->findBySchool((int) $school->getId(), true);

        // Purge des bulletins existants (regénération complète).
        foreach ($period->getPayslips()->toArray() as $existing) {
            $this->em->remove($existing);
        }
        $period->getPayslips()->clear();

        $employees = $this->employeeRepository->createQueryBuilder('e')
            ->join('e.schools', 's')
            ->andWhere('s.id = :school')
            ->andWhere('e.isActive = true')
            ->setParameter('school', $school->getId())
            ->orderBy('e.lastName', 'ASC')
            ->addOrderBy('e.firstName', 'ASC')
            ->getQuery()
            ->getResult();

        $seq = 0;
        $totGross = $totNet = $totDed = $totEmployer = 0.0;

        foreach ($employees as $employee) {
            $contract = $employee->getActiveContract();
            $base = (float) ($contract?->getBaseSalary() ?: $employee->getSalary() ?: 0);
            if ($base <= 0) {
                continue; // rien à payer
            }

            $seq++;
            $reference = sprintf('PAIE-%04d%02d-%03d', $period->getYear(), $period->getMonth(), $seq);
            $payslip = (new Payslip())
                ->setEmployee($employee)
                ->setContract($contract)
                ->setReference($reference);
            $period->addPayslip($payslip);

            $this->computePayslip($payslip, $base, $contract, $settings, $components);
            $this->em->persist($payslip);

            $totGross += (float) $payslip->getGrossTotal();
            $totNet += (float) $payslip->getNetPay();
            $totDed += (float) $payslip->getTotalDeductions();
            $totEmployer += (float) $payslip->getEmployerTotal();
        }

        $period->setTotalGross($this->money($totGross))
            ->setTotalNet($this->money($totNet))
            ->setTotalDeductions($this->money($totDed))
            ->setTotalEmployer($this->money($totEmployer));

        return $seq;
    }

    /**
     * Calcule un bulletin : construit les lignes (gains, cotisations, retenues) et
     * renseigne les totaux figés.
     *
     * @param SalaryComponent[] $components
     */
    private function computePayslip(Payslip $payslip, float $base, ?Contract $contract, PayrollSettings $settings, array $components): void
    {
        $payslip->clearLines();

        // 1) Salaire de base (gain).
        $this->addLine($payslip, PayslipLine::KIND_GAIN, 'SALAIRE', 'Salaire de base', $base, null, null, 1);

        $gross = $base;
        $taxableGross = $base;
        $cnpsBase = $base;

        // 2) Rubriques « gain ».
        foreach ($components as $c) {
            if (!$c->isGain()) {
                continue;
            }
            $amount = $this->componentAmount($c, $base, $gross);
            if ($amount == 0.0) {
                continue;
            }
            $gross += $amount;
            if ($c->isTaxable()) {
                $taxableGross += $amount;
            }
            if ($c->isCnpsSubject()) {
                $cnpsBase += $amount;
            }
            $this->addLine($payslip, PayslipLine::KIND_GAIN, $c->getCode(), $c->getName(), $amount,
                $c->getCalcMode() === SalaryComponent::MODE_PERCENT ? $c->getRate() : null, null, 100 + $c->getSortOrder());
        }

        $declared = $contract === null ? true : (bool) $contract->isDeclared();
        $ceiling = (float) $settings->getCnpsCeiling();
        $cnpsAssiette = min($cnpsBase, $ceiling);

        // 3) Cotisations salariales (si déclaré).
        $cnpsEmployee = $cmuEmployee = $its = 0.0;
        $cnpsEmployer = $familyBenefit = $workAccident = $cmuEmployer = 0.0;
        $parts = $this->computeParts($contract, (float) $settings->getMaxParts());

        if ($declared) {
            $cnpsEmployee = $this->pct($cnpsAssiette, (float) $settings->getCnpsEmployeeRate());
            $cmuEmployee = (float) $settings->getCmuEmployee();
            $its = $this->computeIts($taxableGross, $parts, $settings->getItsBrackets());

            $cnpsEmployer = $this->pct($cnpsAssiette, (float) $settings->getCnpsEmployerRate());
            $familyBenefit = $this->pct($cnpsAssiette, (float) $settings->getFamilyBenefitRate());
            $workAccident = $this->pct($cnpsAssiette, (float) $settings->getWorkAccidentRate());
            $cmuEmployer = (float) $settings->getCmuEmployer();
        }

        // 4) Rubriques « retenue ».
        $componentDeductions = 0.0;
        foreach ($components as $c) {
            if ($c->isGain()) {
                continue;
            }
            $amount = $this->componentAmount($c, $base, $gross);
            if ($amount == 0.0) {
                continue;
            }
            $componentDeductions += $amount;
            $this->addLine($payslip, PayslipLine::KIND_DEDUCTION, $c->getCode(), $c->getName(), $amount,
                $c->getCalcMode() === SalaryComponent::MODE_PERCENT ? $c->getRate() : null, null, 300 + $c->getSortOrder());
        }

        // 5) Lignes de retenue « cotisations ».
        if ($cnpsEmployee > 0) {
            $this->addLine($payslip, PayslipLine::KIND_DEDUCTION, 'CNPS', 'CNPS (retraite)', $cnpsEmployee, $settings->getCnpsEmployeeRate(), $cnpsEmployer, 210);
        }
        if ($cmuEmployee > 0) {
            $this->addLine($payslip, PayslipLine::KIND_DEDUCTION, 'CMU', 'CMU (couverture maladie)', $cmuEmployee, null, $cmuEmployer, 220);
        }
        if ($its > 0) {
            $this->addLine($payslip, PayslipLine::KIND_DEDUCTION, 'ITS', 'ITS (impôt sur salaire)', $its, null, null, 230);
        }

        $otherDeductions = $cmuEmployee + $componentDeductions;
        $totalDeductions = $cnpsEmployee + $its + $otherDeductions;
        $net = $gross - $totalDeductions;
        $employerTotal = $cnpsEmployer + $familyBenefit + $workAccident + $cmuEmployer;

        $payslip->setBaseSalary($this->money($base))
            ->setParts(number_format($parts, 1, '.', ''))
            ->setGrossTotal($this->money($gross))
            ->setTaxableGross($this->money($taxableGross))
            ->setCnpsEmployee($this->money($cnpsEmployee))
            ->setIts($this->money($its))
            ->setOtherDeductions($this->money($otherDeductions))
            ->setTotalDeductions($this->money($totalDeductions))
            ->setNetPay($this->money($net))
            ->setEmployerTotal($this->money($employerTotal))
            ->setCmuEmployee($this->money($cmuEmployee))
            ->setCnpsEmployer($this->money($cnpsEmployer))
            ->setFamilyBenefit($this->money($familyBenefit))
            ->setWorkAccident($this->money($workAccident))
            ->setCmuEmployer($this->money($cmuEmployer))
            ->setPaymentMethod('virement');
    }

    private function componentAmount(SalaryComponent $c, float $baseSalary, float $runningGross): float
    {
        if ($c->getCalcMode() === SalaryComponent::MODE_FIXED) {
            return (float) $c->getAmount();
        }

        $base = $c->getBase() === SalaryComponent::BASE_GROSS ? $runningGross : $baseSalary;

        return $this->pct($base, (float) $c->getRate());
    }

    /**
     * Nombre de parts (quotient familial) : célibataire = 1, marié(e) = 2,
     * + 0,5 par enfant, plafonné.
     */
    private function computeParts(?Contract $contract, float $maxParts): float
    {
        $married = $contract !== null && $contract->getMaritalStatus() === 'married';
        $children = $contract !== null ? (int) ($contract->getNumberOfChildren() ?? 0) : 0;

        $parts = ($married ? 2.0 : 1.0) + 0.5 * $children;
        $parts = min($parts, $maxParts > 0 ? $maxParts : 5.0);

        return max(1.0, $parts);
    }

    /**
     * ITS par la méthode du quotient familial sur le barème mensuel.
     *
     * @param array<int, array{from: float, to: float|null, rate: float}> $brackets
     */
    private function computeIts(float $taxable, float $parts, array $brackets): float
    {
        if ($taxable <= 0 || $parts <= 0) {
            return 0.0;
        }
        $perPart = $taxable / $parts;
        $tax = 0.0;
        foreach ($brackets as $b) {
            $from = (float) ($b['from'] ?? 0);
            $to = $b['to'] ?? null;
            $rate = (float) ($b['rate'] ?? 0);
            if ($perPart <= $from) {
                continue;
            }
            $upper = $to === null ? $perPart : min($perPart, (float) $to);
            $tax += ($upper - $from) * $rate / 100;
        }

        return round($tax * $parts);
    }

    private function addLine(Payslip $payslip, string $kind, ?string $code, string $label, float $amount, ?string $rate, ?float $employerAmount, int $sort): void
    {
        $line = (new PayslipLine())
            ->setKind($kind)
            ->setCode($code)
            ->setLabel($label)
            ->setAmount($this->money($amount))
            ->setRate($rate)
            ->setEmployerAmount($employerAmount !== null ? $this->money($employerAmount) : null)
            ->setSortOrder($sort);
        $payslip->addLine($line);
    }

    private function pct(float $base, float $rate): float
    {
        return round($base * $rate / 100);
    }

    private function money(float $value): string
    {
        return number_format($value, 2, '.', '');
    }
}

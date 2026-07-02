<?php

namespace App\Entity;

use App\Repository\PayslipRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Bulletin de paie d'un employé pour une période donnée.
 *
 * Les montants sont figés au moment de la génération/recalcul (snapshot) : ils ne
 * dépendent plus des paramètres si ceux-ci changent après validation.
 */
#[ORM\Entity(repositoryClass: PayslipRepository::class)]
#[ORM\Table(name: 'payslip')]
#[ORM\HasLifecycleCallbacks]
class Payslip
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'payslips')]
    #[ORM\JoinColumn(nullable: false)]
    private ?PayrollPeriod $period = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Employee $employee = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Contract $contract = null;

    #[ORM\Column(length: 50)]
    private ?string $reference = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2, options: ['default' => '0.00'])]
    private string $baseSalary = '0.00';

    #[ORM\Column(type: Types::DECIMAL, precision: 4, scale: 1, options: ['default' => '1.0'])]
    private string $parts = '1.0';

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2, options: ['default' => '0.00'])]
    private string $grossTotal = '0.00';

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2, options: ['default' => '0.00'])]
    private string $taxableGross = '0.00';

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2, options: ['default' => '0.00'])]
    private string $cnpsEmployee = '0.00';

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2, options: ['default' => '0.00'])]
    private string $its = '0.00';

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2, options: ['default' => '0.00'])]
    private string $otherDeductions = '0.00';

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2, options: ['default' => '0.00'])]
    private string $totalDeductions = '0.00';

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2, options: ['default' => '0.00'])]
    private string $netPay = '0.00';

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2, options: ['default' => '0.00'])]
    private string $employerTotal = '0.00';

    // Ventilation des charges (pour l'état des cotisations à reverser).
    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2, options: ['default' => '0.00'])]
    private string $cmuEmployee = '0.00';

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2, options: ['default' => '0.00'])]
    private string $cnpsEmployer = '0.00';

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2, options: ['default' => '0.00'])]
    private string $familyBenefit = '0.00';

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2, options: ['default' => '0.00'])]
    private string $workAccident = '0.00';

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2, options: ['default' => '0.00'])]
    private string $cmuEmployer = '0.00';

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $paymentMethod = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $notes = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\OneToMany(mappedBy: 'payslip', targetEntity: PayslipLine::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['sortOrder' => 'ASC', 'id' => 'ASC'])]
    private Collection $lines;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->lines = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPeriod(): ?PayrollPeriod
    {
        return $this->period;
    }

    public function setPeriod(?PayrollPeriod $period): static
    {
        $this->period = $period;
        return $this;
    }

    public function getEmployee(): ?Employee
    {
        return $this->employee;
    }

    public function setEmployee(?Employee $employee): static
    {
        $this->employee = $employee;
        return $this;
    }

    public function getContract(): ?Contract
    {
        return $this->contract;
    }

    public function setContract(?Contract $contract): static
    {
        $this->contract = $contract;
        return $this;
    }

    public function getReference(): ?string
    {
        return $this->reference;
    }

    public function setReference(?string $reference): static
    {
        $this->reference = $reference;
        return $this;
    }

    public function getBaseSalary(): string
    {
        return $this->baseSalary;
    }

    public function setBaseSalary(string $v): static
    {
        $this->baseSalary = $v;
        return $this;
    }

    public function getParts(): string
    {
        return $this->parts;
    }

    public function setParts(string $v): static
    {
        $this->parts = $v;
        return $this;
    }

    public function getGrossTotal(): string
    {
        return $this->grossTotal;
    }

    public function setGrossTotal(string $v): static
    {
        $this->grossTotal = $v;
        return $this;
    }

    public function getTaxableGross(): string
    {
        return $this->taxableGross;
    }

    public function setTaxableGross(string $v): static
    {
        $this->taxableGross = $v;
        return $this;
    }

    public function getCnpsEmployee(): string
    {
        return $this->cnpsEmployee;
    }

    public function setCnpsEmployee(string $v): static
    {
        $this->cnpsEmployee = $v;
        return $this;
    }

    public function getIts(): string
    {
        return $this->its;
    }

    public function setIts(string $v): static
    {
        $this->its = $v;
        return $this;
    }

    public function getOtherDeductions(): string
    {
        return $this->otherDeductions;
    }

    public function setOtherDeductions(string $v): static
    {
        $this->otherDeductions = $v;
        return $this;
    }

    public function getTotalDeductions(): string
    {
        return $this->totalDeductions;
    }

    public function setTotalDeductions(string $v): static
    {
        $this->totalDeductions = $v;
        return $this;
    }

    public function getNetPay(): string
    {
        return $this->netPay;
    }

    public function setNetPay(string $v): static
    {
        $this->netPay = $v;
        return $this;
    }

    public function getEmployerTotal(): string
    {
        return $this->employerTotal;
    }

    public function setEmployerTotal(string $v): static
    {
        $this->employerTotal = $v;
        return $this;
    }

    public function getCmuEmployee(): string
    {
        return $this->cmuEmployee;
    }

    public function setCmuEmployee(string $v): static
    {
        $this->cmuEmployee = $v;
        return $this;
    }

    public function getCnpsEmployer(): string
    {
        return $this->cnpsEmployer;
    }

    public function setCnpsEmployer(string $v): static
    {
        $this->cnpsEmployer = $v;
        return $this;
    }

    public function getFamilyBenefit(): string
    {
        return $this->familyBenefit;
    }

    public function setFamilyBenefit(string $v): static
    {
        $this->familyBenefit = $v;
        return $this;
    }

    public function getWorkAccident(): string
    {
        return $this->workAccident;
    }

    public function setWorkAccident(string $v): static
    {
        $this->workAccident = $v;
        return $this;
    }

    public function getCmuEmployer(): string
    {
        return $this->cmuEmployer;
    }

    public function setCmuEmployer(string $v): static
    {
        $this->cmuEmployer = $v;
        return $this;
    }

    public function getPaymentMethod(): ?string
    {
        return $this->paymentMethod;
    }

    public function setPaymentMethod(?string $paymentMethod): static
    {
        $this->paymentMethod = $paymentMethod;
        return $this;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): static
    {
        $this->notes = $notes;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    /**
     * @return Collection<int, PayslipLine>
     */
    public function getLines(): Collection
    {
        return $this->lines;
    }

    public function addLine(PayslipLine $line): static
    {
        if (!$this->lines->contains($line)) {
            $this->lines->add($line);
            $line->setPayslip($this);
        }

        return $this;
    }

    public function removeLine(PayslipLine $line): static
    {
        $this->lines->removeElement($line);

        return $this;
    }

    public function clearLines(): void
    {
        $this->lines->clear();
    }

    /**
     * @return PayslipLine[]
     */
    public function getGainLines(): array
    {
        return array_values(array_filter($this->lines->toArray(), fn (PayslipLine $l) => $l->getKind() === PayslipLine::KIND_GAIN));
    }

    /**
     * @return PayslipLine[]
     */
    public function getDeductionLines(): array
    {
        return array_values(array_filter($this->lines->toArray(), fn (PayslipLine $l) => $l->getKind() === PayslipLine::KIND_DEDUCTION));
    }
}

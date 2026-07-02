<?php

namespace App\Entity;

use App\Repository\PayrollPeriodRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Période de paie mensuelle d'un établissement.
 *
 * Cycle : brouillon (bulletins regénérables) → validée (figée) → payée.
 */
#[ORM\Entity(repositoryClass: PayrollPeriodRepository::class)]
#[ORM\Table(name: 'payroll_period')]
#[ORM\UniqueConstraint(name: 'uniq_period_school_month', columns: ['school_id', 'year', 'month'])]
#[ORM\HasLifecycleCallbacks]
class PayrollPeriod
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_VALIDATED = 'validated';
    public const STATUS_PAID = 'paid';

    public const MONTHS = [
        1 => 'Janvier', 2 => 'Février', 3 => 'Mars', 4 => 'Avril', 5 => 'Mai', 6 => 'Juin',
        7 => 'Juillet', 8 => 'Août', 9 => 'Septembre', 10 => 'Octobre', 11 => 'Novembre', 12 => 'Décembre',
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?School $school = null;

    #[ORM\Column(type: Types::SMALLINT)]
    #[Assert\Range(min: 1, max: 12)]
    private int $month = 1;

    #[ORM\Column(type: Types::SMALLINT)]
    private int $year = 2026;

    #[ORM\Column(length: 20)]
    #[Assert\Choice(choices: [self::STATUS_DRAFT, self::STATUS_VALIDATED, self::STATUS_PAID])]
    private string $status = self::STATUS_DRAFT;

    #[ORM\Column(type: Types::DECIMAL, precision: 14, scale: 2, options: ['default' => '0.00'])]
    private string $totalGross = '0.00';

    #[ORM\Column(type: Types::DECIMAL, precision: 14, scale: 2, options: ['default' => '0.00'])]
    private string $totalNet = '0.00';

    #[ORM\Column(type: Types::DECIMAL, precision: 14, scale: 2, options: ['default' => '0.00'])]
    private string $totalDeductions = '0.00';

    #[ORM\Column(type: Types::DECIMAL, precision: 14, scale: 2, options: ['default' => '0.00'])]
    private string $totalEmployer = '0.00';

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $validatedBy = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $validatedAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $paidAt = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $paymentMethod = null;

    /** Dépense de caisse générée lors du paiement des salaires de la période. */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Depense $paymentDepense = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\OneToMany(mappedBy: 'period', targetEntity: Payslip::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $payslips;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->payslips = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSchool(): ?School
    {
        return $this->school;
    }

    public function setSchool(?School $school): static
    {
        $this->school = $school;
        return $this;
    }

    public function getMonth(): int
    {
        return $this->month;
    }

    public function setMonth(int $month): static
    {
        $this->month = $month;
        return $this;
    }

    public function getYear(): int
    {
        return $this->year;
    }

    public function setYear(int $year): static
    {
        $this->year = $year;
        return $this;
    }

    public function getLabel(): string
    {
        return (self::MONTHS[$this->month] ?? '') . ' ' . $this->year;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;
        return $this;
    }

    public function getStatusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_DRAFT => 'Brouillon',
            self::STATUS_VALIDATED => 'Validée',
            self::STATUS_PAID => 'Payée',
            default => $this->status,
        };
    }

    public function getStatusColor(): string
    {
        return match ($this->status) {
            self::STATUS_DRAFT => 'secondary',
            self::STATUS_VALIDATED => 'info',
            self::STATUS_PAID => 'success',
            default => 'secondary',
        };
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isValidated(): bool
    {
        return $this->status === self::STATUS_VALIDATED;
    }

    public function isPaid(): bool
    {
        return $this->status === self::STATUS_PAID;
    }

    public function getTotalGross(): string
    {
        return $this->totalGross;
    }

    public function setTotalGross(string $v): static
    {
        $this->totalGross = $v;
        return $this;
    }

    public function getTotalNet(): string
    {
        return $this->totalNet;
    }

    public function setTotalNet(string $v): static
    {
        $this->totalNet = $v;
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

    public function getTotalEmployer(): string
    {
        return $this->totalEmployer;
    }

    public function setTotalEmployer(string $v): static
    {
        $this->totalEmployer = $v;
        return $this;
    }

    public function getValidatedBy(): ?User
    {
        return $this->validatedBy;
    }

    public function setValidatedBy(?User $validatedBy): static
    {
        $this->validatedBy = $validatedBy;
        return $this;
    }

    public function getValidatedAt(): ?\DateTimeInterface
    {
        return $this->validatedAt;
    }

    public function setValidatedAt(?\DateTimeInterface $validatedAt): static
    {
        $this->validatedAt = $validatedAt;
        return $this;
    }

    public function getPaidAt(): ?\DateTimeInterface
    {
        return $this->paidAt;
    }

    public function setPaidAt(?\DateTimeInterface $paidAt): static
    {
        $this->paidAt = $paidAt;
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

    public function getPaymentDepense(): ?Depense
    {
        return $this->paymentDepense;
    }

    public function setPaymentDepense(?Depense $paymentDepense): static
    {
        $this->paymentDepense = $paymentDepense;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    /**
     * @return Collection<int, Payslip>
     */
    public function getPayslips(): Collection
    {
        return $this->payslips;
    }

    public function addPayslip(Payslip $payslip): static
    {
        if (!$this->payslips->contains($payslip)) {
            $this->payslips->add($payslip);
            $payslip->setPeriod($this);
        }

        return $this;
    }

    public function removePayslip(Payslip $payslip): static
    {
        $this->payslips->removeElement($payslip);

        return $this;
    }
}

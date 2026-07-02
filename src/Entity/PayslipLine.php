<?php

namespace App\Entity;

use App\Repository\PayslipLineRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Ligne détaillée d'un bulletin de paie (base, gain, retenue).
 */
#[ORM\Entity(repositoryClass: PayslipLineRepository::class)]
#[ORM\Table(name: 'payslip_line')]
class PayslipLine
{
    public const KIND_GAIN = 'gain';
    public const KIND_DEDUCTION = 'deduction';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'lines')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Payslip $payslip = null;

    #[ORM\Column(length: 30, nullable: true)]
    private ?string $code = null;

    #[ORM\Column(length: 120)]
    private ?string $label = null;

    #[ORM\Column(length: 12)]
    private string $kind = self::KIND_GAIN;

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2, nullable: true)]
    private ?string $base = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 6, scale: 3, nullable: true)]
    private ?string $rate = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2, options: ['default' => '0.00'])]
    private string $amount = '0.00';

    /** Part patronale associée (charges employeur), le cas échéant. */
    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2, nullable: true)]
    private ?string $employerAmount = null;

    #[ORM\Column(type: Types::INTEGER, options: ['default' => 100])]
    private int $sortOrder = 100;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPayslip(): ?Payslip
    {
        return $this->payslip;
    }

    public function setPayslip(?Payslip $payslip): static
    {
        $this->payslip = $payslip;
        return $this;
    }

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(?string $code): static
    {
        $this->code = $code;
        return $this;
    }

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function setLabel(?string $label): static
    {
        $this->label = $label;
        return $this;
    }

    public function getKind(): string
    {
        return $this->kind;
    }

    public function setKind(string $kind): static
    {
        $this->kind = $kind;
        return $this;
    }

    public function isGain(): bool
    {
        return $this->kind === self::KIND_GAIN;
    }

    public function getBase(): ?string
    {
        return $this->base;
    }

    public function setBase(?string $base): static
    {
        $this->base = $base;
        return $this;
    }

    public function getRate(): ?string
    {
        return $this->rate;
    }

    public function setRate(?string $rate): static
    {
        $this->rate = $rate;
        return $this;
    }

    public function getAmount(): string
    {
        return $this->amount;
    }

    public function setAmount(string $amount): static
    {
        $this->amount = $amount;
        return $this;
    }

    public function getEmployerAmount(): ?string
    {
        return $this->employerAmount;
    }

    public function setEmployerAmount(?string $employerAmount): static
    {
        $this->employerAmount = $employerAmount;
        return $this;
    }

    public function getSortOrder(): int
    {
        return $this->sortOrder;
    }

    public function setSortOrder(int $sortOrder): static
    {
        $this->sortOrder = $sortOrder;
        return $this;
    }
}

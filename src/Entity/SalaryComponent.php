<?php

namespace App\Entity;

use App\Repository\SalaryComponentRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Rubrique de paie configurable (gain ou retenue) propre à un établissement.
 *
 * Le mode de calcul est soit un montant fixe, soit un pourcentage d'une base
 * (salaire de base ou brut). Le moteur applique les rubriques actives à chaque
 * bulletin, en plus des cotisations CNPS/ITS calculées à partir des paramètres.
 */
#[ORM\Entity(repositoryClass: SalaryComponentRepository::class)]
#[ORM\Table(name: 'salary_component')]
#[ORM\UniqueConstraint(name: 'uniq_component_school_code', columns: ['school_id', 'code'])]
#[ORM\HasLifecycleCallbacks]
class SalaryComponent
{
    public const DIRECTION_GAIN = 'gain';
    public const DIRECTION_RETENUE = 'retenue';

    public const MODE_FIXED = 'fixed';
    public const MODE_PERCENT = 'percent';

    public const BASE_SALARY = 'base_salary';
    public const BASE_GROSS = 'gross';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?School $school = null;

    #[ORM\Column(length: 30)]
    #[Assert\NotBlank(message: 'Le code est obligatoire.')]
    #[Assert\Length(max: 30)]
    private ?string $code = null;

    #[ORM\Column(length: 120)]
    #[Assert\NotBlank(message: 'Le libellé est obligatoire.')]
    private ?string $name = null;

    #[ORM\Column(length: 10)]
    #[Assert\Choice(choices: [self::DIRECTION_GAIN, self::DIRECTION_RETENUE])]
    private string $direction = self::DIRECTION_GAIN;

    #[ORM\Column(length: 10)]
    #[Assert\Choice(choices: [self::MODE_FIXED, self::MODE_PERCENT])]
    private string $calcMode = self::MODE_FIXED;

    /** Base du calcul quand le mode est « pourcentage ». */
    #[ORM\Column(length: 20)]
    #[Assert\Choice(choices: [self::BASE_SALARY, self::BASE_GROSS])]
    private string $base = self::BASE_SALARY;

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2, options: ['default' => '0.00'])]
    #[Assert\PositiveOrZero]
    private string $amount = '0.00';

    #[ORM\Column(type: Types::DECIMAL, precision: 6, scale: 3, options: ['default' => '0.000'])]
    #[Assert\PositiveOrZero]
    private string $rate = '0.000';

    /** Entre dans la base imposable ITS (gains uniquement). */
    #[ORM\Column(options: ['default' => true])]
    private bool $taxable = true;

    /** Entre dans la base soumise à cotisation CNPS (gains uniquement). */
    #[ORM\Column(options: ['default' => true])]
    private bool $cnpsSubject = true;

    #[ORM\Column(type: Types::INTEGER, options: ['default' => 100])]
    private int $sortOrder = 100;

    #[ORM\Column(options: ['default' => true])]
    private bool $isActive = true;

    #[ORM\Column(options: ['default' => false])]
    private bool $isSystem = false;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
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

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(?string $code): static
    {
        $this->code = $code;
        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): static
    {
        $this->name = $name;
        return $this;
    }

    public function getDirection(): string
    {
        return $this->direction;
    }

    public function setDirection(string $direction): static
    {
        $this->direction = $direction;
        return $this;
    }

    public function isGain(): bool
    {
        return $this->direction === self::DIRECTION_GAIN;
    }

    public function getDirectionLabel(): string
    {
        return $this->isGain() ? 'Gain' : 'Retenue';
    }

    public function getCalcMode(): string
    {
        return $this->calcMode;
    }

    public function setCalcMode(string $calcMode): static
    {
        $this->calcMode = $calcMode;
        return $this;
    }

    public function getBase(): string
    {
        return $this->base;
    }

    public function setBase(string $base): static
    {
        $this->base = $base;
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

    public function getRate(): string
    {
        return $this->rate;
    }

    public function setRate(string $rate): static
    {
        $this->rate = $rate;
        return $this;
    }

    public function isTaxable(): bool
    {
        return $this->taxable;
    }

    public function setTaxable(bool $taxable): static
    {
        $this->taxable = $taxable;
        return $this;
    }

    public function isCnpsSubject(): bool
    {
        return $this->cnpsSubject;
    }

    public function setCnpsSubject(bool $cnpsSubject): static
    {
        $this->cnpsSubject = $cnpsSubject;
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

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;
        return $this;
    }

    public function isSystem(): bool
    {
        return $this->isSystem;
    }

    public function setIsSystem(bool $isSystem): static
    {
        $this->isSystem = $isSystem;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }
}

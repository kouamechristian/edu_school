<?php

namespace App\Entity;

use App\Repository\PayrollSettingsRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Paramètres de paie d'un établissement (un enregistrement par école).
 *
 * Tous les taux/plafonds sont modifiables : rien n'est codé en dur dans le moteur.
 * Valeurs par défaut inspirées du régime Côte d'Ivoire (CNPS + ITS), indicatives.
 */
#[ORM\Entity(repositoryClass: PayrollSettingsRepository::class)]
#[ORM\Table(name: 'payroll_settings')]
#[ORM\HasLifecycleCallbacks]
class PayrollSettings
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?School $school = null;

    // ── CNPS retraite ──
    #[ORM\Column(type: Types::DECIMAL, precision: 6, scale: 3, options: ['default' => '6.300'])]
    private string $cnpsEmployeeRate = '6.300';

    #[ORM\Column(type: Types::DECIMAL, precision: 6, scale: 3, options: ['default' => '7.700'])]
    private string $cnpsEmployerRate = '7.700';

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2, options: ['default' => '3375000.00'])]
    private string $cnpsCeiling = '3375000.00';

    // ── Autres charges patronales ──
    #[ORM\Column(type: Types::DECIMAL, precision: 6, scale: 3, options: ['default' => '5.000'])]
    private string $familyBenefitRate = '5.000';

    #[ORM\Column(type: Types::DECIMAL, precision: 6, scale: 3, options: ['default' => '2.000'])]
    private string $workAccidentRate = '2.000';

    // ── CMU (couverture maladie), forfait mensuel ──
    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, options: ['default' => '1000.00'])]
    private string $cmuEmployee = '1000.00';

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, options: ['default' => '1000.00'])]
    private string $cmuEmployer = '1000.00';

    // ── ITS : quotient familial + barème progressif mensuel ──
    #[ORM\Column(type: Types::DECIMAL, precision: 4, scale: 1, options: ['default' => '5.0'])]
    private string $maxParts = '5.0';

    /**
     * Barème ITS mensuel : liste de tranches [{from, to|null, rate}].
     * @var array<int, array{from: float, to: float|null, rate: float}>
     */
    #[ORM\Column(type: Types::JSON)]
    private array $itsBrackets = [];

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $updatedAt = null;

    public function __construct()
    {
        $this->updatedAt = new \DateTime();
        $this->itsBrackets = self::defaultItsBrackets();
    }

    /**
     * Barème ITS mensuel par défaut (indicatif, type Côte d'Ivoire).
     *
     * @return array<int, array{from: float, to: float|null, rate: float}>
     */
    public static function defaultItsBrackets(): array
    {
        return [
            ['from' => 0, 'to' => 75000, 'rate' => 0],
            ['from' => 75000, 'to' => 240000, 'rate' => 16],
            ['from' => 240000, 'to' => 800000, 'rate' => 21],
            ['from' => 800000, 'to' => 2400000, 'rate' => 24],
            ['from' => 2400000, 'to' => 8000000, 'rate' => 28],
            ['from' => 8000000, 'to' => null, 'rate' => 32],
        ];
    }

    #[ORM\PreUpdate]
    public function setUpdatedAtValue(): void
    {
        $this->updatedAt = new \DateTime();
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

    public function getCnpsEmployeeRate(): string
    {
        return $this->cnpsEmployeeRate;
    }

    public function setCnpsEmployeeRate(string $v): static
    {
        $this->cnpsEmployeeRate = $v;
        return $this;
    }

    public function getCnpsEmployerRate(): string
    {
        return $this->cnpsEmployerRate;
    }

    public function setCnpsEmployerRate(string $v): static
    {
        $this->cnpsEmployerRate = $v;
        return $this;
    }

    public function getCnpsCeiling(): string
    {
        return $this->cnpsCeiling;
    }

    public function setCnpsCeiling(string $v): static
    {
        $this->cnpsCeiling = $v;
        return $this;
    }

    public function getFamilyBenefitRate(): string
    {
        return $this->familyBenefitRate;
    }

    public function setFamilyBenefitRate(string $v): static
    {
        $this->familyBenefitRate = $v;
        return $this;
    }

    public function getWorkAccidentRate(): string
    {
        return $this->workAccidentRate;
    }

    public function setWorkAccidentRate(string $v): static
    {
        $this->workAccidentRate = $v;
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

    public function getCmuEmployer(): string
    {
        return $this->cmuEmployer;
    }

    public function setCmuEmployer(string $v): static
    {
        $this->cmuEmployer = $v;
        return $this;
    }

    public function getMaxParts(): string
    {
        return $this->maxParts;
    }

    public function setMaxParts(string $v): static
    {
        $this->maxParts = $v;
        return $this;
    }

    /**
     * @return array<int, array{from: float, to: float|null, rate: float}>
     */
    public function getItsBrackets(): array
    {
        return $this->itsBrackets;
    }

    /**
     * @param array<int, array{from: float, to: float|null, rate: float}> $brackets
     */
    public function setItsBrackets(array $brackets): static
    {
        $this->itsBrackets = $brackets;
        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updatedAt;
    }
}

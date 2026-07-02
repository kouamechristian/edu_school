<?php

namespace App\Entity;

use App\Repository\AccountingPeriodClosureRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Clôture d'une période comptable.
 *
 * Une clôture fige les écritures jusqu'à sa date de fin (plus aucune écriture
 * manuelle ne peut y être créée ou supprimée) et conserve un instantané des
 * totaux de la période (recettes, dépenses, résultat, trésorerie).
 */
#[ORM\Entity(repositoryClass: AccountingPeriodClosureRepository::class)]
#[ORM\Table(name: 'accounting_period_closure')]
#[ORM\Index(columns: ['end_date'], name: 'idx_closure_end_date')]
#[ORM\HasLifecycleCallbacks]
class AccountingPeriodClosure
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull]
    private ?School $school = null;

    #[ORM\Column(length: 120)]
    #[Assert\NotBlank]
    private ?string $label = null;

    /** Début de la période couverte (null = depuis l'origine). */
    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $startDate = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    #[Assert\NotNull(message: 'La date de clôture est obligatoire.')]
    private ?\DateTimeInterface $endDate = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 14, scale: 2, options: ['default' => '0.00'])]
    private string $totalRecette = '0.00';

    #[ORM\Column(type: Types::DECIMAL, precision: 14, scale: 2, options: ['default' => '0.00'])]
    private string $totalDepense = '0.00';

    #[ORM\Column(type: Types::DECIMAL, precision: 14, scale: 2, options: ['default' => '0.00'])]
    private string $totalVersement = '0.00';

    #[ORM\Column(type: Types::DECIMAL, precision: 14, scale: 2, options: ['default' => '0.00'])]
    private string $netResult = '0.00';

    #[ORM\Column(type: Types::DECIMAL, precision: 14, scale: 2, options: ['default' => '0.00'])]
    private string $cashBalance = '0.00';

    #[ORM\Column(type: Types::INTEGER, options: ['default' => 0])]
    private int $entryCount = 0;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $closedBy = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $notes = null;

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

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function setLabel(?string $label): static
    {
        $this->label = $label;
        return $this;
    }

    public function getStartDate(): ?\DateTimeInterface
    {
        return $this->startDate;
    }

    public function setStartDate(?\DateTimeInterface $startDate): static
    {
        $this->startDate = $startDate;
        return $this;
    }

    public function getEndDate(): ?\DateTimeInterface
    {
        return $this->endDate;
    }

    public function setEndDate(?\DateTimeInterface $endDate): static
    {
        $this->endDate = $endDate;
        return $this;
    }

    public function getTotalRecette(): string
    {
        return $this->totalRecette;
    }

    public function setTotalRecette(string $totalRecette): static
    {
        $this->totalRecette = $totalRecette;
        return $this;
    }

    public function getTotalDepense(): string
    {
        return $this->totalDepense;
    }

    public function setTotalDepense(string $totalDepense): static
    {
        $this->totalDepense = $totalDepense;
        return $this;
    }

    public function getTotalVersement(): string
    {
        return $this->totalVersement;
    }

    public function setTotalVersement(string $totalVersement): static
    {
        $this->totalVersement = $totalVersement;
        return $this;
    }

    public function getNetResult(): string
    {
        return $this->netResult;
    }

    public function setNetResult(string $netResult): static
    {
        $this->netResult = $netResult;
        return $this;
    }

    public function getCashBalance(): string
    {
        return $this->cashBalance;
    }

    public function setCashBalance(string $cashBalance): static
    {
        $this->cashBalance = $cashBalance;
        return $this;
    }

    public function getEntryCount(): int
    {
        return $this->entryCount;
    }

    public function setEntryCount(int $entryCount): static
    {
        $this->entryCount = $entryCount;
        return $this;
    }

    public function getClosedBy(): ?User
    {
        return $this->closedBy;
    }

    public function setClosedBy(?User $closedBy): static
    {
        $this->closedBy = $closedBy;
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
}

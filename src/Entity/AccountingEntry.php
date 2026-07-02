<?php

namespace App\Entity;

use App\Repository\AccountingEntryRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Écriture du journal comptable (livre de caisse enrichi).
 *
 * Chaque écriture est un mouvement d'argent unique : une recette (entrée), une
 * dépense (sortie) ou un versement bancaire (transfert de trésorerie). Les
 * écritures issues d'un paiement, d'une dépense ou d'un versement sont générées
 * automatiquement et tracées via {@see $sourceType}/{@see $sourceId} (idempotence).
 */
#[ORM\Entity(repositoryClass: AccountingEntryRepository::class)]
#[ORM\Table(name: 'accounting_entry')]
#[ORM\Index(columns: ['entry_date'], name: 'idx_entry_date')]
#[ORM\Index(columns: ['type'], name: 'idx_entry_type')]
#[ORM\UniqueConstraint(name: 'uniq_entry_source', columns: ['source_type', 'source_id'])]
#[ORM\HasLifecycleCallbacks]
class AccountingEntry
{
    public const TYPE_RECETTE = 'recette';
    public const TYPE_DEPENSE = 'depense';
    public const TYPE_VERSEMENT = 'versement';

    public const TYPES = [
        self::TYPE_RECETTE => 'Recette',
        self::TYPE_DEPENSE => 'Dépense',
        self::TYPE_VERSEMENT => 'Versement bancaire',
    ];

    public const SOURCE_MANUAL = 'manual';
    public const SOURCE_PAYMENT = 'payment';
    public const SOURCE_DEPENSE = 'depense';
    public const SOURCE_DEPOSIT = 'deposit';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull]
    private ?School $school = null;

    #[ORM\Column(length: 50, unique: true)]
    private ?string $reference = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    #[Assert\NotNull(message: 'La date est obligatoire.')]
    private ?\DateTimeInterface $entryDate = null;

    #[ORM\Column(length: 200)]
    #[Assert\NotBlank(message: 'Le libellé est obligatoire.')]
    #[Assert\Length(max: 200)]
    private ?string $label = null;

    #[ORM\Column(length: 20)]
    #[Assert\Choice(choices: [self::TYPE_RECETTE, self::TYPE_DEPENSE, self::TYPE_VERSEMENT], message: 'Type d\'écriture invalide.')]
    private ?string $type = self::TYPE_RECETTE;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?AccountingAccount $account = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2)]
    #[Assert\NotNull(message: 'Le montant est obligatoire.')]
    #[Assert\Positive(message: 'Le montant doit être supérieur à zéro.')]
    private ?string $amount = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $paymentMethod = null;

    /** Origine de l'écriture : manual|payment|depense|deposit. */
    #[ORM\Column(length: 20)]
    private string $sourceType = self::SOURCE_MANUAL;

    /** Identifiant de l'objet source (Payment/Depense/CashDeposit), null si saisie manuelle. */
    #[ORM\Column(nullable: true)]
    private ?int $sourceId = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $recordedBy = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $notes = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $updatedAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
        $this->entryDate = new \DateTime();
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

    public function getReference(): ?string
    {
        return $this->reference;
    }

    public function setReference(?string $reference): static
    {
        $this->reference = $reference;
        return $this;
    }

    public function getEntryDate(): ?\DateTimeInterface
    {
        return $this->entryDate;
    }

    public function setEntryDate(\DateTimeInterface $entryDate): static
    {
        $this->entryDate = $entryDate;
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

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(?string $type): static
    {
        $this->type = $type;
        return $this;
    }

    public function getTypeLabel(): string
    {
        return self::TYPES[$this->type] ?? ($this->type ?? '');
    }

    public function getTypeColor(): string
    {
        return match ($this->type) {
            self::TYPE_RECETTE => 'success',
            self::TYPE_DEPENSE => 'danger',
            self::TYPE_VERSEMENT => 'info',
            default => 'secondary',
        };
    }

    /** Signe de trésorerie : +1 pour une entrée, -1 pour une sortie/versement. */
    public function getCashSign(): int
    {
        return $this->type === self::TYPE_RECETTE ? 1 : -1;
    }

    public function getAccount(): ?AccountingAccount
    {
        return $this->account;
    }

    public function setAccount(?AccountingAccount $account): static
    {
        $this->account = $account;
        return $this;
    }

    public function getAmount(): ?string
    {
        return $this->amount;
    }

    public function setAmount(string $amount): static
    {
        $this->amount = $amount;
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

    public function getSourceType(): string
    {
        return $this->sourceType;
    }

    public function setSourceType(string $sourceType): static
    {
        $this->sourceType = $sourceType;
        return $this;
    }

    public function getSourceId(): ?int
    {
        return $this->sourceId;
    }

    public function setSourceId(?int $sourceId): static
    {
        $this->sourceId = $sourceId;
        return $this;
    }

    public function isManual(): bool
    {
        return $this->sourceType === self::SOURCE_MANUAL;
    }

    public function getRecordedBy(): ?User
    {
        return $this->recordedBy;
    }

    public function setRecordedBy(?User $recordedBy): static
    {
        $this->recordedBy = $recordedBy;
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

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updatedAt;
    }
}

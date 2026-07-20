<?php

namespace App\Entity;

use App\Repository\DepenseRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Dépense (sortie d'argent) effectuée depuis une caisse.
 *
 * Une dépense créée par un caissier est d'abord « en attente » : elle n'impacte NI le
 * solde de la caisse NI la comptabilité tant que le fondateur ne l'a pas approuvée
 * (statut « confirmée »). Le fondateur peut aussi la rejeter (statut « rejetée »).
 * Seule une dépense confirmée est prise en compte.
 */
#[ORM\Entity(repositoryClass: DepenseRepository::class)]
#[ORM\Table(name: 'depense')]
#[ORM\HasLifecycleCallbacks]
class Depense implements SchoolOwnedInterface
{
    public const CATEGORIES = [
        'salaire' => 'Salaire',
        'loyer' => 'Loyer',
        'fournitures' => 'Fournitures',
        'services' => 'Services',
        'maintenance' => 'Maintenance',
        'autre' => 'Autre',
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 50, unique: true)]
    private ?string $numero = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull(message: 'La caisse est obligatoire.')]
    private ?CashRegister $cashRegister = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true)]
    private ?School $school = null;

    #[ORM\Column(length: 150)]
    #[Assert\NotBlank(message: 'Le motif de la dépense est obligatoire.')]
    #[Assert\Length(max: 150)]
    private ?string $libelle = null;

    #[ORM\Column(length: 20)]
    #[Assert\NotBlank(message: 'La catégorie est obligatoire.')]
    #[Assert\Choice(choices: ['salaire', 'loyer', 'fournitures', 'services', 'maintenance', 'autre'], message: 'Catégorie invalide.')]
    private ?string $category = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2)]
    #[Assert\NotBlank(message: 'Le montant est obligatoire.')]
    #[Assert\Positive(message: 'Le montant doit être supérieur à zéro.')]
    private ?string $amount = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    #[Assert\NotNull(message: 'La date est obligatoire.')]
    private ?\DateTimeInterface $depenseDate = null;

    #[ORM\Column(length: 20)]
    #[Assert\Choice(choices: ['espèces', 'chèque', 'virement', 'carte', 'mobile_money'], message: 'Méthode de paiement invalide.')]
    private ?string $paymentMethod = 'espèces';

    #[ORM\Column(length: 150, nullable: true)]
    private ?string $beneficiary = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $reference = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $notes = null;

    #[ORM\Column(length: 20, options: ['default' => 'en_attente'])]
    #[Assert\Choice(choices: ['en_attente', 'confirmée', 'rejetée', 'annulée'])]
    private string $status = 'en_attente';

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $recordedBy = null;

    /**
     * Fondateur ayant approuvé (ou rejeté) la dépense, et l'horodatage de la décision.
     */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $approvedBy = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $approvedAt = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $rejectionReason = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $updatedAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
        $this->depenseDate = new \DateTime();
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

    public function getNumero(): ?string
    {
        return $this->numero;
    }

    public function setNumero(?string $numero): static
    {
        $this->numero = $numero;
        return $this;
    }

    public function getCashRegister(): ?CashRegister
    {
        return $this->cashRegister;
    }

    public function setCashRegister(?CashRegister $cashRegister): static
    {
        $this->cashRegister = $cashRegister;
        return $this;
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

    public function getLibelle(): ?string
    {
        return $this->libelle;
    }

    public function setLibelle(?string $libelle): static
    {
        $this->libelle = $libelle;
        return $this;
    }

    public function getCategory(): ?string
    {
        return $this->category;
    }

    public function setCategory(?string $category): static
    {
        $this->category = $category;
        return $this;
    }

    public function getCategoryLabel(): string
    {
        return self::CATEGORIES[$this->category] ?? ($this->category ?? '');
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

    public function getDepenseDate(): ?\DateTimeInterface
    {
        return $this->depenseDate;
    }

    public function setDepenseDate(\DateTimeInterface $depenseDate): static
    {
        $this->depenseDate = $depenseDate;
        return $this;
    }

    public function getPaymentMethod(): ?string
    {
        return $this->paymentMethod;
    }

    public function setPaymentMethod(string $paymentMethod): static
    {
        $this->paymentMethod = $paymentMethod;
        return $this;
    }

    public function getBeneficiary(): ?string
    {
        return $this->beneficiary;
    }

    public function setBeneficiary(?string $beneficiary): static
    {
        $this->beneficiary = $beneficiary;
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

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;
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
            'en_attente' => 'En attente',
            'confirmée' => 'Approuvée',
            'rejetée' => 'Rejetée',
            'annulée' => 'Annulée',
            default => $this->status,
        };
    }

    public function getStatusColor(): string
    {
        return match ($this->status) {
            'en_attente' => 'warning',
            'confirmée' => 'success',
            'rejetée' => 'danger',
            'annulée' => 'secondary',
            default => 'secondary',
        };
    }

    public function isPending(): bool
    {
        return $this->status === 'en_attente';
    }

    public function isApproved(): bool
    {
        return $this->status === 'confirmée';
    }

    public function isRejected(): bool
    {
        return $this->status === 'rejetée';
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

    public function getApprovedBy(): ?User
    {
        return $this->approvedBy;
    }

    public function setApprovedBy(?User $approvedBy): static
    {
        $this->approvedBy = $approvedBy;
        return $this;
    }

    public function getApprovedAt(): ?\DateTimeInterface
    {
        return $this->approvedAt;
    }

    public function setApprovedAt(?\DateTimeInterface $approvedAt): static
    {
        $this->approvedAt = $approvedAt;
        return $this;
    }

    public function getRejectionReason(): ?string
    {
        return $this->rejectionReason;
    }

    public function setRejectionReason(?string $rejectionReason): static
    {
        $this->rejectionReason = $rejectionReason;
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

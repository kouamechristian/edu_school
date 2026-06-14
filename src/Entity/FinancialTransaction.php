<?php

namespace App\Entity;

use App\Repository\FinancialTransactionRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: FinancialTransactionRepository::class)]
#[ORM\Table(name: 'financial_transaction')]
#[ORM\HasLifecycleCallbacks]
class FinancialTransaction
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 50, unique: true)]
    #[Assert\NotBlank(message: 'Le numéro de transaction est obligatoire')]
    #[Assert\Length(max: 50)]
    private ?string $transactionNumber = null;

    #[ORM\Column(length: 20)]
    #[Assert\Choice(choices: ['entrée', 'sortie', 'transfert'], message: 'Type de transaction invalide')]
    private ?string $type = null;

    #[ORM\Column(length: 20)]
    #[Assert\Choice(choices: ['paiement', 'remboursement', 'bourse', 'frais', 'autre'], message: 'Catégorie de transaction invalide')]
    private ?string $category = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    #[Assert\NotBlank(message: 'Le montant est obligatoire')]
    #[Assert\Positive(message: 'Le montant doit être positif')]
    private ?string $amount = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    #[Assert\NotBlank(message: 'La date de transaction est obligatoire')]
    private ?\DateTimeInterface $transactionDate = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length: 20)]
    #[Assert\Choice(choices: ['espèces', 'chèque', 'virement', 'carte', 'mobile_money'], message: 'Méthode de paiement invalide')]
    private ?string $paymentMethod = null;

    #[ORM\Column(length: 20)]
    #[Assert\Choice(choices: ['en_attente', 'confirmé', 'annulé', 'en_erreur'], message: 'Statut de transaction invalide')]
    private ?string $status = 'en_attente';

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $reference = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $notes = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true)]
    private ?Student $student = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true)]
    private ?Payment $payment = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true)]
    private ?User $recordedBy = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true)]
    private ?TransactionType $transactionType = null;

    /**
     * Établissement rattaché à la transaction (permet le suivi financier par école).
     */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true)]
    private ?School $school = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $updatedAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
        $this->transactionDate = new \DateTime();
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

    public function getTransactionNumber(): ?string
    {
        return $this->transactionNumber;
    }

    public function setTransactionNumber(string $transactionNumber): static
    {
        $this->transactionNumber = $transactionNumber;
        return $this;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;
        return $this;
    }

    public function getTypeLabel(): string
    {
        if ($this->transactionType) {
            return $this->transactionType->getName();
        }

        return match($this->type) {
            'entrée' => 'Entrée',
            'sortie' => 'Sortie',
            'transfert' => 'Transfert',
            default => $this->type
        };
    }

    public function getTransactionType(): ?TransactionType
    {
        return $this->transactionType;
    }

    public function setTransactionType(?TransactionType $transactionType): static
    {
        $this->transactionType = $transactionType;

        // Le sens comptable (type) reste synchronisé avec le type choisi.
        if ($transactionType !== null) {
            $this->type = $transactionType->getDirection();
        }

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

    public function getCategory(): ?string
    {
        return $this->category;
    }

    public function setCategory(string $category): static
    {
        $this->category = $category;
        return $this;
    }

    public function getCategoryLabel(): string
    {
        return match($this->category) {
            'paiement' => 'Paiement',
            'remboursement' => 'Remboursement',
            'bourse' => 'Bourse',
            'frais' => 'Frais',
            'autre' => 'Autre',
            default => $this->category
        };
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

    public function getTransactionDate(): ?\DateTimeInterface
    {
        return $this->transactionDate;
    }

    public function setTransactionDate(\DateTimeInterface $transactionDate): static
    {
        $this->transactionDate = $transactionDate;
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

    public function getPaymentMethod(): ?string
    {
        return $this->paymentMethod;
    }

    public function setPaymentMethod(string $paymentMethod): static
    {
        $this->paymentMethod = $paymentMethod;
        return $this;
    }

    public function getPaymentMethodLabel(): string
    {
        return match($this->paymentMethod) {
            'espèces' => 'Espèces',
            'chèque' => 'Chèque',
            'virement' => 'Virement',
            'carte' => 'Carte bancaire',
            'mobile_money' => 'Mobile Money',
            default => $this->paymentMethod
        };
    }

    public function getStatus(): ?string
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
        return match($this->status) {
            'en_attente' => 'En attente',
            'confirmé' => 'Confirmé',
            'annulé' => 'Annulé',
            'en_erreur' => 'En erreur',
            default => $this->status
        };
    }

    public function getStatusColor(): string
    {
        return match($this->status) {
            'en_attente' => 'warning',
            'confirmé' => 'success',
            'annulé' => 'danger',
            'en_erreur' => 'danger',
            default => 'secondary'
        };
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

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): static
    {
        $this->notes = $notes;
        return $this;
    }

    public function getStudent(): ?Student
    {
        return $this->student;
    }

    public function setStudent(?Student $student): static
    {
        $this->student = $student;
        return $this;
    }

    public function getPayment(): ?Payment
    {
        return $this->payment;
    }

    public function setPayment(?Payment $payment): static
    {
        $this->payment = $payment;
        return $this;
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

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): static
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeInterface $updatedAt): static
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    public function isConfirmed(): bool
    {
        return $this->status === 'confirmé';
    }

    public function isPending(): bool
    {
        return $this->status === 'en_attente';
    }

    public function isCancelled(): bool
    {
        return $this->status === 'annulé';
    }

    public function __toString(): string
    {
        return $this->transactionNumber ?? '';
    }
}

<?php

namespace App\Entity;

use App\Repository\PaymentRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: PaymentRepository::class)]
#[ORM\Table(name: 'payment')]
#[ORM\HasLifecycleCallbacks]
class Payment
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 50, unique: true)]
    #[Assert\Length(max: 50)]
    private ?string $paymentNumber = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotBlank(message: 'L\'élève est obligatoire')]
    private ?Student $student = null;

    #[ORM\ManyToOne(inversedBy: 'payments')]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotBlank(message: 'Le frais est obligatoire')]
    private ?Fee $fee = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?StudentFee $studentFee = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    #[Assert\NotBlank(message: 'Le montant est obligatoire')]
    #[Assert\Positive(message: 'Le montant doit être positif')]
    private ?string $amount = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    #[Assert\NotBlank(message: 'La date de paiement est obligatoire')]
    private ?\DateTimeInterface $paymentDate = null;

    #[ORM\Column(length: 20)]
    #[Assert\Choice(choices: ['espèces', 'chèque', 'virement', 'carte', 'mobile_money'], message: 'Méthode de paiement invalide')]
    private ?string $paymentMethod = null;

    #[ORM\Column(length: 20)]
    #[Assert\Choice(choices: ['en_attente', 'payé', 'partiellement_payé', 'annulé', 'remboursé'], message: 'Statut de paiement invalide')]
    private ?string $status = 'en_attente';

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $notes = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $reference = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $receiptPath = null;

    // ── Passerelle de paiement en ligne (GeniusPay) ──

    #[ORM\Column(length: 30, nullable: true)]
    private ?string $provider = null;

    #[ORM\Column(length: 100, unique: true, nullable: true)]
    private ?string $providerTransactionId = null;

    #[ORM\Column(length: 40, nullable: true)]
    private ?string $providerStatus = null;

    #[ORM\Column(length: 30, nullable: true)]
    private ?string $payerPhone = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $checkoutUrl = null;

    #[ORM\Column(length: 64, unique: true, nullable: true)]
    private ?string $idempotencyKey = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true)]
    private ?User $recordedBy = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?CashRegister $cashRegister = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $updatedAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
        $this->paymentDate = new \DateTime();
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

    public function getPaymentNumber(): ?string
    {
        return $this->paymentNumber;
    }

    public function setPaymentNumber(string $paymentNumber): static
    {
        $this->paymentNumber = $paymentNumber;
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

    public function getFee(): ?Fee
    {
        return $this->fee;
    }

    public function setFee(?Fee $fee): static
    {
        $this->fee = $fee;
        return $this;
    }

    public function getStudentFee(): ?StudentFee
    {
        return $this->studentFee;
    }

    public function setStudentFee(?StudentFee $studentFee): static
    {
        $this->studentFee = $studentFee;
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

    public function getPaymentDate(): ?\DateTimeInterface
    {
        return $this->paymentDate;
    }

    public function setPaymentDate(\DateTimeInterface $paymentDate): static
    {
        $this->paymentDate = $paymentDate;
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
            'payé' => 'Payé',
            'partiellement_payé' => 'Partiellement payé',
            'annulé' => 'Annulé',
            'remboursé' => 'Remboursé',
            default => $this->status
        };
    }

    public function getStatusColor(): string
    {
        return match($this->status) {
            'en_attente' => 'warning',
            'payé' => 'success',
            'partiellement_payé' => 'info',
            'annulé' => 'danger',
            'remboursé' => 'secondary',
            default => 'secondary'
        };
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

    public function getReference(): ?string
    {
        return $this->reference;
    }

    public function setReference(?string $reference): static
    {
        $this->reference = $reference;
        return $this;
    }

    public function getReceiptPath(): ?string
    {
        return $this->receiptPath;
    }

    public function setReceiptPath(?string $receiptPath): static
    {
        $this->receiptPath = $receiptPath;
        return $this;
    }

    public function getProvider(): ?string
    {
        return $this->provider;
    }

    public function setProvider(?string $provider): static
    {
        $this->provider = $provider;
        return $this;
    }

    public function getProviderTransactionId(): ?string
    {
        return $this->providerTransactionId;
    }

    public function setProviderTransactionId(?string $providerTransactionId): static
    {
        $this->providerTransactionId = $providerTransactionId;
        return $this;
    }

    public function getProviderStatus(): ?string
    {
        return $this->providerStatus;
    }

    public function setProviderStatus(?string $providerStatus): static
    {
        $this->providerStatus = $providerStatus;
        return $this;
    }

    public function getPayerPhone(): ?string
    {
        return $this->payerPhone;
    }

    public function setPayerPhone(?string $payerPhone): static
    {
        $this->payerPhone = $payerPhone;
        return $this;
    }

    public function getCheckoutUrl(): ?string
    {
        return $this->checkoutUrl;
    }

    public function setCheckoutUrl(?string $checkoutUrl): static
    {
        $this->checkoutUrl = $checkoutUrl;
        return $this;
    }

    public function getIdempotencyKey(): ?string
    {
        return $this->idempotencyKey;
    }

    public function setIdempotencyKey(?string $idempotencyKey): static
    {
        $this->idempotencyKey = $idempotencyKey;
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

    public function getCashRegister(): ?CashRegister
    {
        return $this->cashRegister;
    }

    public function setCashRegister(?CashRegister $cashRegister): static
    {
        $this->cashRegister = $cashRegister;
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

    public function isPaid(): bool
    {
        return $this->status === 'payé';
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
        return $this->paymentNumber ?? '';
    }
}

<?php

namespace App\Entity;

use App\Repository\OnlinePaymentRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Tentative de paiement en ligne via la passerelle GeniusPay.
 *
 * C'est NOTRE trace de ce qui a été initié : quel élève, quel frais, quel
 * montant. Quand le webhook arrive, on ne croit pas ce qu'il raconte — on
 * retrouve cette ligne par sa référence et on compare. Sans cela, n'importe
 * qui capable de forger un webhook pourrait solder une scolarité.
 *
 * L'unicité de `reference` porte aussi l'idempotence : un webhook rejoué ne
 * peut pas créer deux encaissements.
 */
#[ORM\Entity(repositoryClass: OnlinePaymentRepository::class)]
#[ORM\Table(name: 'online_payment')]
#[ORM\Index(columns: ['status'], name: 'idx_online_payment_status')]
#[ORM\HasLifecycleCallbacks]
class OnlinePayment implements SchoolOwnedInterface
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';
    public const STATUS_CANCELLED = 'cancelled';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /** Référence GeniusPay (« MTX-… »), unique côté passerelle comme chez nous. */
    #[ORM\Column(length: 100, unique: true)]
    private ?string $reference = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?School $school = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Student $student = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?StudentFee $studentFee = null;

    /** Parent à l'origine de la demande (traçabilité). */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $initiatedBy = null;

    /** Montant attendu, figé à l'initiation : le webhook doit correspondre. */
    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2)]
    private ?string $amount = null;

    #[ORM\Column(length: 20)]
    private string $status = self::STATUS_PENDING;

    /** `sandbox` ou `live`, déduit du préfixe de la clé API utilisée. */
    #[ORM\Column(length: 20)]
    private string $environment = 'sandbox';

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $checkoutUrl = null;

    /** Paiement comptable créé une fois l'encaissement confirmé. */
    #[ORM\OneToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Payment $payment = null;

    /** Dernière charge utile reçue (webhook ou vérification), pour l'audit. */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $lastPayload = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $failureReason = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $completedAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getReference(): ?string
    {
        return $this->reference;
    }

    public function setReference(string $reference): static
    {
        $this->reference = $reference;
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

    public function getStudent(): ?Student
    {
        return $this->student;
    }

    public function setStudent(?Student $student): static
    {
        $this->student = $student;
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

    public function getInitiatedBy(): ?User
    {
        return $this->initiatedBy;
    }

    public function setInitiatedBy(?User $initiatedBy): static
    {
        $this->initiatedBy = $initiatedBy;
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

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;
        return $this;
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function getStatusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING => 'En attente',
            self::STATUS_COMPLETED => 'Payé',
            self::STATUS_FAILED => 'Échoué',
            self::STATUS_CANCELLED => 'Annulé',
            default => $this->status,
        };
    }

    public function getStatusColor(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING => 'warning',
            self::STATUS_COMPLETED => 'success',
            self::STATUS_FAILED, self::STATUS_CANCELLED => 'danger',
            default => 'secondary',
        };
    }

    public function getEnvironment(): string
    {
        return $this->environment;
    }

    public function setEnvironment(string $environment): static
    {
        $this->environment = $environment;
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

    public function getPayment(): ?Payment
    {
        return $this->payment;
    }

    public function setPayment(?Payment $payment): static
    {
        $this->payment = $payment;
        return $this;
    }

    public function getLastPayload(): ?string
    {
        return $this->lastPayload;
    }

    public function setLastPayload(?string $lastPayload): static
    {
        $this->lastPayload = $lastPayload;
        return $this;
    }

    public function getFailureReason(): ?string
    {
        return $this->failureReason;
    }

    public function setFailureReason(?string $failureReason): static
    {
        $this->failureReason = $failureReason;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function getCompletedAt(): ?\DateTimeInterface
    {
        return $this->completedAt;
    }

    public function setCompletedAt(?\DateTimeInterface $completedAt): static
    {
        $this->completedAt = $completedAt;
        return $this;
    }
}

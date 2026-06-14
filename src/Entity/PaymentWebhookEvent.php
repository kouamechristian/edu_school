<?php

namespace App\Entity;

use App\Repository\PaymentWebhookEventRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Journal des webhooks de paiement reçus.
 *
 * Sert à la fois d'audit et de garde-fou anti-rejeu : l'identifiant d'événement
 * du fournisseur est unique, ce qui garantit qu'un même webhook n'est traité qu'une fois.
 */
#[ORM\Entity(repositoryClass: PaymentWebhookEventRepository::class)]
#[ORM\Table(name: 'payment_webhook_event')]
#[ORM\UniqueConstraint(name: 'uniq_provider_event', columns: ['provider', 'event_id'])]
class PaymentWebhookEvent
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 30)]
    private ?string $provider = null;

    #[ORM\Column(length: 100)]
    private ?string $eventId = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $type = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $payload = null;

    #[ORM\Column]
    private bool $signatureValid = false;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $receivedAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $processedAt = null;

    public function __construct()
    {
        $this->receivedAt = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getProvider(): ?string
    {
        return $this->provider;
    }

    public function setProvider(string $provider): static
    {
        $this->provider = $provider;
        return $this;
    }

    public function getEventId(): ?string
    {
        return $this->eventId;
    }

    public function setEventId(string $eventId): static
    {
        $this->eventId = $eventId;
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

    public function getPayload(): ?string
    {
        return $this->payload;
    }

    public function setPayload(?string $payload): static
    {
        $this->payload = $payload;
        return $this;
    }

    public function isSignatureValid(): bool
    {
        return $this->signatureValid;
    }

    public function setSignatureValid(bool $signatureValid): static
    {
        $this->signatureValid = $signatureValid;
        return $this;
    }

    public function getReceivedAt(): ?\DateTimeInterface
    {
        return $this->receivedAt;
    }

    public function getProcessedAt(): ?\DateTimeInterface
    {
        return $this->processedAt;
    }

    public function markProcessed(): static
    {
        $this->processedAt = new \DateTime();
        return $this;
    }
}

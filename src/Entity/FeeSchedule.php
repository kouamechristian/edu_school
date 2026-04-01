<?php

namespace App\Entity;

use App\Repository\FeeScheduleRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: FeeScheduleRepository::class)]
#[ORM\Table(name: 'fee_schedule')]
class FeeSchedule
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Fee::class, inversedBy: 'schedules')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Fee $fee = null;

    #[ORM\Column]
    #[Assert\NotBlank(message: 'Le numéro d\'ordre est obligatoire')]
    #[Assert\Positive(message: 'Le numéro d\'ordre doit être positif')]
    private ?int $orderNumber = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    #[Assert\NotBlank(message: 'Le montant est obligatoire')]
    #[Assert\Positive(message: 'Le montant doit être positif')]
    private ?string $amount = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    #[Assert\NotBlank(message: 'La date d\'échéance est obligatoire')]
    private ?\DateTimeInterface $dueDate = null;

    public function getId(): ?int
    {
        return $this->id;
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

    public function getOrderNumber(): ?int
    {
        return $this->orderNumber;
    }

    public function setOrderNumber(int $orderNumber): static
    {
        $this->orderNumber = $orderNumber;
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

    public function getDueDate(): ?\DateTimeInterface
    {
        return $this->dueDate;
    }

    public function setDueDate(\DateTimeInterface $dueDate): static
    {
        $this->dueDate = $dueDate;
        return $this;
    }

    public function isOverdue(): bool
    {
        return $this->dueDate < new \DateTime('today');
    }

    public function isDueSoon(int $days = 7): bool
    {
        $limit = new \DateTime("+{$days} days");
        return !$this->isOverdue() && $this->dueDate <= $limit;
    }

    public function __toString(): string
    {
        return sprintf('Échéance #%d - %s FCFA', $this->orderNumber ?? 0, number_format((float)($this->amount ?? 0), 0, ',', ' '));
    }
}

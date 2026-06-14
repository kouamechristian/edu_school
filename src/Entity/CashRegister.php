<?php

namespace App\Entity;

use App\Repository\CashRegisterRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: CashRegisterRepository::class)]
#[ORM\Table(name: 'cash_register')]
#[ORM\Index(columns: ['status'], name: 'idx_cash_register_status')]
#[ORM\HasLifecycleCallbacks]
class CashRegister
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?School $school = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true)]
    private ?User $cashier = null;

    /**
     * Caisse « en ligne » par défaut d'un établissement : reçoit automatiquement
     * les paiements mobile/passerelle (sans caissier humain). Une seule par école.
     */
    #[ORM\Column(options: ['default' => false])]
    private bool $isOnline = false;

    #[ORM\Column(length: 20)]
    #[Assert\Choice(choices: ['open', 'closed'])]
    private string $status = 'open';

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    #[Assert\PositiveOrZero]
    private string $openingBalance = '0.00';

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    private ?string $closingBalance = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $openedAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $closedAt = null;

    // Validation de la caisse par le fondateur
    #[ORM\Column(options: ['default' => false])]
    private bool $isValidated = false;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $validatedBy = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $validatedAt = null;

    // Autorisation de faire des dépenses, accordée par le fondateur
    #[ORM\Column(options: ['default' => false])]
    private bool $expenseAuthorized = false;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $authorizedBy = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $authorizedAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $updatedAt = null;

    public function __construct()
    {
        $this->openedAt = new \DateTime();
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
        $this->status = 'open';
        $this->openingBalance = '0.00';
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

    public function getCashier(): ?User
    {
        return $this->cashier;
    }

    public function setCashier(?User $cashier): static
    {
        $this->cashier = $cashier;
        return $this;
    }

    public function isOnline(): bool
    {
        return $this->isOnline;
    }

    public function setIsOnline(bool $isOnline): static
    {
        $this->isOnline = $isOnline;
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

    public function getOpeningBalance(): string
    {
        return $this->openingBalance;
    }

    public function setOpeningBalance(string $openingBalance): static
    {
        $this->openingBalance = $openingBalance;
        return $this;
    }

    public function getClosingBalance(): ?string
    {
        return $this->closingBalance;
    }

    public function setClosingBalance(?string $closingBalance): static
    {
        $this->closingBalance = $closingBalance;
        return $this;
    }

    public function getOpenedAt(): ?\DateTimeInterface
    {
        return $this->openedAt;
    }

    public function setOpenedAt(\DateTimeInterface $openedAt): static
    {
        $this->openedAt = $openedAt;
        return $this;
    }

    public function getClosedAt(): ?\DateTimeInterface
    {
        return $this->closedAt;
    }

    public function setClosedAt(?\DateTimeInterface $closedAt): static
    {
        $this->closedAt = $closedAt;
        return $this;
    }

    public function isValidated(): bool
    {
        return $this->isValidated;
    }

    public function setIsValidated(bool $isValidated): static
    {
        $this->isValidated = $isValidated;
        return $this;
    }

    public function getValidatedBy(): ?User
    {
        return $this->validatedBy;
    }

    public function setValidatedBy(?User $validatedBy): static
    {
        $this->validatedBy = $validatedBy;
        return $this;
    }

    public function getValidatedAt(): ?\DateTimeInterface
    {
        return $this->validatedAt;
    }

    public function setValidatedAt(?\DateTimeInterface $validatedAt): static
    {
        $this->validatedAt = $validatedAt;
        return $this;
    }

    public function isExpenseAuthorized(): bool
    {
        return $this->expenseAuthorized;
    }

    public function setExpenseAuthorized(bool $expenseAuthorized): static
    {
        $this->expenseAuthorized = $expenseAuthorized;
        return $this;
    }

    public function getAuthorizedBy(): ?User
    {
        return $this->authorizedBy;
    }

    public function setAuthorizedBy(?User $authorizedBy): static
    {
        $this->authorizedBy = $authorizedBy;
        return $this;
    }

    public function getAuthorizedAt(): ?\DateTimeInterface
    {
        return $this->authorizedAt;
    }

    public function setAuthorizedAt(?\DateTimeInterface $authorizedAt): static
    {
        $this->authorizedAt = $authorizedAt;
        return $this;
    }

    public function close(?string $closingBalance = null): static
    {
        $this->status = 'closed';
        $this->closedAt = new \DateTime();
        $this->closingBalance = $closingBalance;
        return $this;
    }

    public function isOpen(): bool
    {
        return $this->status === 'open';
    }

    public function __toString(): string
    {
        return sprintf(
            'Caisse %s - %s (%s)',
            $this->school?->getName() ?? '',
            $this->cashier?->getFullName() ?? '',
            $this->status
        );
    }
}


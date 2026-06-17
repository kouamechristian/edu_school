<?php

namespace App\Entity;

use App\Repository\ContractRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ContractRepository::class)]
#[ORM\Table(name: 'contract')]
#[ORM\HasLifecycleCallbacks]
class Contract
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Employee::class, inversedBy: 'contracts')]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull(message: 'L\'employé est obligatoire')]
    private ?Employee $employee = null;

    #[ORM\Column(length: 50, unique: true)]
    private ?string $reference = null;

    #[ORM\Column(length: 30)]
    #[Assert\NotBlank(message: 'Le type de contrat est obligatoire')]
    #[Assert\Choice(choices: ['cdi', 'cdd', 'stage', 'prestation', 'interim', 'vacation'], message: 'Type de contrat invalide')]
    private ?string $contractType = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Length(max: 255)]
    private ?string $jobTitle = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    #[Assert\NotNull(message: 'La date de début est obligatoire')]
    private ?\DateTimeInterface $startDate = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $endDate = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $trialEndDate = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2, nullable: true)]
    #[Assert\PositiveOrZero(message: 'Le salaire doit être positif')]
    private ?string $baseSalary = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 5, scale: 2, nullable: true)]
    #[Assert\PositiveOrZero(message: 'Le nombre d\'heures doit être positif')]
    private ?string $weeklyHours = null;

    #[ORM\Column(length: 20)]
    #[Assert\Choice(choices: ['draft', 'active', 'suspended', 'terminated', 'expired'], message: 'Statut invalide')]
    private ?string $status = 'active';

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
        $this->startDate = new \DateTime();
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

    public function getEmployee(): ?Employee
    {
        return $this->employee;
    }

    public function setEmployee(?Employee $employee): static
    {
        $this->employee = $employee;
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

    public function getContractType(): ?string
    {
        return $this->contractType;
    }

    public function setContractType(string $contractType): static
    {
        $this->contractType = $contractType;
        return $this;
    }

    public function getContractTypeLabel(): string
    {
        return match ($this->contractType) {
            'cdi' => 'CDI',
            'cdd' => 'CDD',
            'stage' => 'Stage',
            'prestation' => 'Prestation de service',
            'interim' => 'Intérim',
            'vacation' => 'Vacation',
            default => $this->contractType ?? '—',
        };
    }

    public function getJobTitle(): ?string
    {
        return $this->jobTitle;
    }

    public function setJobTitle(?string $jobTitle): static
    {
        $this->jobTitle = $jobTitle;
        return $this;
    }

    public function getStartDate(): ?\DateTimeInterface
    {
        return $this->startDate;
    }

    public function setStartDate(\DateTimeInterface $startDate): static
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

    public function getTrialEndDate(): ?\DateTimeInterface
    {
        return $this->trialEndDate;
    }

    public function setTrialEndDate(?\DateTimeInterface $trialEndDate): static
    {
        $this->trialEndDate = $trialEndDate;
        return $this;
    }

    public function getBaseSalary(): ?string
    {
        return $this->baseSalary;
    }

    public function setBaseSalary(?string $baseSalary): static
    {
        $this->baseSalary = $baseSalary;
        return $this;
    }

    public function getWeeklyHours(): ?string
    {
        return $this->weeklyHours;
    }

    public function setWeeklyHours(?string $weeklyHours): static
    {
        $this->weeklyHours = $weeklyHours;
        return $this;
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
        return match ($this->status) {
            'draft' => 'Brouillon',
            'active' => 'En cours',
            'suspended' => 'Suspendu',
            'terminated' => 'Rompu',
            'expired' => 'Expiré',
            default => $this->status ?? '—',
        };
    }

    public function getStatusBadgeClass(): string
    {
        return match ($this->status) {
            'active' => 'success',
            'draft' => 'secondary',
            'suspended' => 'warning',
            'terminated' => 'danger',
            'expired' => 'dark',
            default => 'secondary',
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

    /**
     * Indique si le contrat est arrivé à échéance (date de fin dépassée).
     */
    public function isExpired(): bool
    {
        return $this->endDate !== null && $this->endDate < new \DateTime('today');
    }

    public function __toString(): string
    {
        return $this->reference ?? 'Contrat';
    }
}

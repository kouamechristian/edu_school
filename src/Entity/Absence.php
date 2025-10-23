<?php

namespace App\Entity;

use App\Repository\AbsenceRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: AbsenceRepository::class)]
#[ORM\Table(name: 'absence')]
#[ORM\HasLifecycleCallbacks]
class Absence
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Student::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotBlank(message: 'L\'élève est obligatoire')]
    private ?Student $student = null;

    #[ORM\ManyToOne(targetEntity: AbsenceType::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotBlank(message: 'Le type d\'absence est obligatoire')]
    private ?AbsenceType $absenceType = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    #[Assert\NotBlank(message: 'La date est obligatoire')]
    private ?\DateTimeInterface $date = null;

    #[ORM\Column(type: Types::TIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $startTime = null;

    #[ORM\Column(type: Types::TIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $endTime = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $reason = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $justification = null;

    #[ORM\Column(length: 50, nullable: true)]
    #[Assert\Choice(choices: ['pending', 'justified', 'unjustified'], message: 'Statut de justification invalide')]
    private ?string $justificationStatus = 'pending';

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $justificationDocument = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $justificationDate = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $justificationSubmittedAt = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?User $recordedBy = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?User $justifiedBy = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $notes = null;

    #[ORM\Column]
    private bool $isActive = true;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $updatedAt = null;

    #[ORM\ManyToOne(targetEntity: School::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?School $school = null;

    #[ORM\ManyToOne(targetEntity: Period::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?Period $period = null;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
        $this->justificationStatus = 'pending';
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

    public function getStudent(): ?Student
    {
        return $this->student;
    }

    public function setStudent(?Student $student): static
    {
        $this->student = $student;
        return $this;
    }

    public function getAbsenceType(): ?AbsenceType
    {
        return $this->absenceType;
    }

    public function setAbsenceType(?AbsenceType $absenceType): static
    {
        $this->absenceType = $absenceType;
        return $this;
    }

    public function getDate(): ?\DateTimeInterface
    {
        return $this->date;
    }

    public function setDate(\DateTimeInterface $date): static
    {
        $this->date = $date;
        return $this;
    }

    public function getStartTime(): ?\DateTimeInterface
    {
        return $this->startTime;
    }

    public function setStartTime(?\DateTimeInterface $startTime): static
    {
        $this->startTime = $startTime;
        return $this;
    }

    public function getEndTime(): ?\DateTimeInterface
    {
        return $this->endTime;
    }

    public function setEndTime(?\DateTimeInterface $endTime): static
    {
        $this->endTime = $endTime;
        return $this;
    }

    public function getReason(): ?string
    {
        return $this->reason;
    }

    public function setReason(?string $reason): static
    {
        $this->reason = $reason;
        return $this;
    }

    public function getJustification(): ?string
    {
        return $this->justification;
    }

    public function setJustification(?string $justification): static
    {
        $this->justification = $justification;
        return $this;
    }

    public function getJustificationStatus(): ?string
    {
        return $this->justificationStatus;
    }

    public function setJustificationStatus(?string $justificationStatus): static
    {
        $this->justificationStatus = $justificationStatus;
        return $this;
    }

    public function getJustificationStatusLabel(): string
    {
        return match($this->justificationStatus) {
            'pending' => 'En attente',
            'justified' => 'Justifiée',
            'unjustified' => 'Non justifiée',
            default => 'Inconnu'
        };
    }

    public function getJustificationDocument(): ?string
    {
        return $this->justificationDocument;
    }

    public function setJustificationDocument(?string $justificationDocument): static
    {
        $this->justificationDocument = $justificationDocument;
        return $this;
    }

    public function getJustificationDate(): ?\DateTimeInterface
    {
        return $this->justificationDate;
    }

    public function setJustificationDate(?\DateTimeInterface $justificationDate): static
    {
        $this->justificationDate = $justificationDate;
        return $this;
    }

    public function getJustificationSubmittedAt(): ?\DateTimeInterface
    {
        return $this->justificationSubmittedAt;
    }

    public function setJustificationSubmittedAt(?\DateTimeInterface $justificationSubmittedAt): static
    {
        $this->justificationSubmittedAt = $justificationSubmittedAt;
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

    public function getJustifiedBy(): ?User
    {
        return $this->justifiedBy;
    }

    public function setJustifiedBy(?User $justifiedBy): static
    {
        $this->justifiedBy = $justifiedBy;
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

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;
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

    public function getSchool(): ?School
    {
        return $this->school;
    }

    public function setSchool(?School $school): static
    {
        $this->school = $school;
        return $this;
    }

    public function getPeriod(): ?Period
    {
        return $this->period;
    }

    public function setPeriod(?Period $period): static
    {
        $this->period = $period;
        return $this;
    }

    /**
     * Calcule la durée de l'absence en heures
     */
    public function getDurationInHours(): ?float
    {
        if (!$this->startTime || !$this->endTime) {
            return null;
        }

        $start = $this->startTime->format('H:i');
        $end = $this->endTime->format('H:i');
        
        $startTime = strtotime($start);
        $endTime = strtotime($end);
        
        if ($endTime < $startTime) {
            $endTime += 86400; // Ajouter 24 heures si c'est le lendemain
        }
        
        return ($endTime - $startTime) / 3600; // Convertir en heures
    }

    /**
     * Vérifie si l'absence est justifiée
     */
    public function isJustified(): bool
    {
        return $this->justificationStatus === 'justified';
    }

    /**
     * Vérifie si l'absence est en attente de justification
     */
    public function isPendingJustification(): bool
    {
        return $this->justificationStatus === 'pending';
    }

    /**
     * Vérifie si l'absence est non justifiée
     */
    public function isUnjustified(): bool
    {
        return $this->justificationStatus === 'unjustified';
    }

    public function __toString(): string
    {
        return sprintf(
            '%s - %s (%s)',
            $this->student?->getFullName() ?? 'Élève',
            $this->absenceType?->getName() ?? 'Absence',
            $this->date?->format('d/m/Y') ?? ''
        );
    }
}

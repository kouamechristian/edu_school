<?php

namespace App\Entity;

use App\Repository\RegistrationRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Inscription d'un élève pour une année scolaire donnée.
 *
 * La table `student` est un référentiel de tous les élèves ayant un jour fréquenté
 * l'établissement (état civil, parent, matricules…). C'est l'inscription qui rattache
 * un élève à une année scolaire, un niveau et une classe : un élève peut donc avoir
 * plusieurs inscriptions au fil des années (historique des scolarités).
 *
 * Les frais de scolarité (StudentFee) sont rattachés à l'inscription, car ils sont
 * propres à une année.
 */
#[ORM\Entity(repositoryClass: RegistrationRepository::class)]
#[ORM\Table(name: 'registration')]
#[ORM\HasLifecycleCallbacks]
class Registration
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: School::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull(message: 'L\'établissement est obligatoire')]
    private ?School $school = null;

    #[ORM\ManyToOne(targetEntity: SchoolYear::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull(message: 'L\'année scolaire est obligatoire')]
    private ?SchoolYear $schoolYear = null;

    #[ORM\ManyToOne(targetEntity: Classroom::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Classroom $classroom = null;

    /**
     * Préinscription d'origine : une inscription part toujours d'une préinscription
     * validée, dont les informations servent à créer/remplir l'élève (table student).
     * Lien 1‑à‑1 : une préinscription validée est inscrite une seule fois.
     */
    #[ORM\OneToOne(targetEntity: PreRegistration::class, inversedBy: 'registration')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?PreRegistration $preRegistration = null;

    #[ORM\Column]
    private bool $isRepeating = false;

    /**
     * Élève boursier pour cette année (peut ouvrir droit à une exonération/réduction
     * des frais de scolarité).
     */
    #[ORM\Column]
    private bool $boursier = false;

    #[ORM\Column]
    private bool $isActive = true;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $enrolledAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $updatedAt = null;

    #[ORM\OneToMany(mappedBy: 'registration', targetEntity: StudentFee::class, cascade: ['persist'])]
    private Collection $studentFees;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
        $this->enrolledAt = new \DateTime();
        $this->studentFees = new ArrayCollection();
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

    /**
     * L'élève n'est plus stocké directement sur l'inscription : il est porté par la
     * préinscription d'origine (élève créé pour un « nouvel élève », ou élève réutilisé
     * pour un « ancien élève »). Getter dérivé pour conserver l'API existante.
     */
    public function getStudent(): ?Student
    {
        return $this->preRegistration?->getStudent()
            ?? $this->preRegistration?->getExistingStudent();
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

    public function getSchoolYear(): ?SchoolYear
    {
        return $this->schoolYear;
    }

    public function setSchoolYear(?SchoolYear $schoolYear): static
    {
        $this->schoolYear = $schoolYear;
        return $this;
    }

    public function getClassroom(): ?Classroom
    {
        return $this->classroom;
    }

    public function setClassroom(?Classroom $classroom): static
    {
        $this->classroom = $classroom;
        return $this;
    }

    public function getPreRegistration(): ?PreRegistration
    {
        return $this->preRegistration;
    }

    public function setPreRegistration(?PreRegistration $preRegistration): static
    {
        $this->preRegistration = $preRegistration;
        // Synchronise le côté inverse du lien 1‑à‑1.
        if ($preRegistration !== null && $preRegistration->getRegistration() !== $this) {
            $preRegistration->setRegistration($this);
        }
        return $this;
    }

    /**
     * Le niveau de l'inscription est celui de sa classe (déduit, non stocké).
     */
    public function getLevel(): ?Level
    {
        return $this->classroom?->getLevel();
    }

    public function isRepeating(): bool
    {
        return $this->isRepeating;
    }

    public function setIsRepeating(bool $isRepeating): static
    {
        $this->isRepeating = $isRepeating;
        return $this;
    }

    public function isBoursier(): bool
    {
        return $this->boursier;
    }

    public function setBoursier(bool $boursier): static
    {
        $this->boursier = $boursier;
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

    public function getEnrolledAt(): ?\DateTimeInterface
    {
        return $this->enrolledAt;
    }

    public function setEnrolledAt(?\DateTimeInterface $enrolledAt): static
    {
        $this->enrolledAt = $enrolledAt;
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

    /**
     * @return Collection<int, StudentFee>
     */
    public function getStudentFees(): Collection
    {
        return $this->studentFees;
    }

    public function addStudentFee(StudentFee $studentFee): static
    {
        if (!$this->studentFees->contains($studentFee)) {
            $this->studentFees->add($studentFee);
            $studentFee->setRegistration($this);
        }

        return $this;
    }

    public function removeStudentFee(StudentFee $studentFee): static
    {
        if ($this->studentFees->removeElement($studentFee)) {
            if ($studentFee->getRegistration() === $this) {
                $studentFee->setRegistration(null);
            }
        }

        return $this;
    }

    /**
     * Total des frais (actifs) de l'année d'inscription.
     */
    public function getTotalTuition(): float
    {
        $total = 0.0;
        foreach ($this->studentFees as $sf) {
            if ($sf->getFee()?->isActive()) {
                $total += (float) $sf->getAmount();
            }
        }

        return $total;
    }

    /**
     * Total déjà payé sur les frais (actifs) de l'année d'inscription.
     */
    public function getTotalPaid(): float
    {
        $total = 0.0;
        foreach ($this->studentFees as $sf) {
            if ($sf->getFee()?->isActive()) {
                $total += (float) $sf->getPaidAmount();
            }
        }

        return $total;
    }

    /**
     * Reste à payer sur l'année d'inscription.
     */
    public function getRemainingTuition(): float
    {
        return max(0.0, $this->getTotalTuition() - $this->getTotalPaid());
    }

    /**
     * Total des paiements annulés sur les frais (actifs) de l'année d'inscription.
     */
    public function getTotalCancelled(): float
    {
        $total = 0.0;
        foreach ($this->studentFees as $sf) {
            if ($sf->getFee()?->isActive()) {
                $total += $sf->getCancelledAmount();
            }
        }

        return $total;
    }

    public function __toString(): string
    {
        return sprintf(
            '%s - %s',
            $this->getStudent()?->getFullName() ?? '',
            $this->schoolYear?->getName() ?? ''
        );
    }
}

<?php

namespace App\Entity;

use App\Repository\StudentRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: StudentRepository::class)]
#[ORM\Table(name: 'student')]
#[ORM\HasLifecycleCallbacks]
class Student
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank(message: 'Le nom est obligatoire')]
    #[Assert\Length(max: 100)]
    private ?string $firstName = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank(message: 'Le prénom est obligatoire')]
    #[Assert\Length(max: 100)]
    private ?string $lastName = null;

    #[ORM\Column(length: 20, nullable: true)]
    #[Assert\Length(max: 20)]
    private ?string $phone = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Email(message: 'L\'email n\'est pas valide')]
    private ?string $email = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $address = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $dateOfBirth = null;

    #[ORM\Column(length: 1, nullable: true)]
    #[Assert\Choice(choices: ['M', 'F'], message: 'Le genre doit être M ou F')]
    private ?string $gender = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $matriculeInterne = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $matriculeNational = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $placeOfBirth = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $nationality = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $birthCertificateNumber = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $cmuNumber = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $lastSchoolAttended = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $photo = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $parentName = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $parentPhone = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $parentEmail = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $parentFunction = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $parentAddress = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $emergencyContact = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $emergencyPhone = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $medicalInfo = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $notes = null;

    #[ORM\Column]
    private bool $isActive = true;

    /**
     * Statut administratif de l'élève : « affecté » (placé par l'État) ou
     * « non affecté ». Par défaut « non affecté » à la création ; conditionne les
     * frais de scolarité applicables (cf. Fee::type) et se gère via le bouton dédié.
     */
    #[ORM\Column(length: 20, options: ['default' => 'non_affecte'])]
    #[Assert\Choice(choices: ['affecte', 'non_affecte'], message: 'Le statut doit être Affecté ou Non affecté')]
    private string $status = 'non_affecte';

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $updatedAt = null;

    #[ORM\ManyToOne(targetEntity: School::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?School $school = null;

    /**
     * Compte parent rattaché (auto-association via le portail parent).
     *
     * Un élève ne peut référencer qu'UN seul parent : c'est la garantie, au niveau
     * du schéma, qu'un enfant n'est pas rattaché à plusieurs comptes parents à la fois.
     */
    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'children')]
    #[ORM\JoinColumn(name: 'parent_user_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?User $parentUser = null;

    #[ORM\OneToMany(mappedBy: 'student', targetEntity: Grade::class)]
    private Collection $grades;

    #[ORM\OneToOne(inversedBy: 'student', targetEntity: PreRegistration::class, cascade: ['persist', 'remove'])]
    #[ORM\JoinColumn(name: 'pre_registration_id', referencedColumnName: 'id')]
    private ?PreRegistration $preRegistration = null;

    #[ORM\OneToMany(mappedBy: 'student', targetEntity: StudentFee::class, cascade: ['persist', 'remove'])]
    private Collection $studentFees;

    /**
     * Préinscriptions de réinscription où cet élève est réutilisé (« ancien élève »).
     * Avec la préinscription d'origine (preRegistration), elles permettent de
     * reconstituer l'historique des inscriptions (l'inscription n'est plus liée
     * directement à l'élève : elle est portée par sa préinscription).
     */
    #[ORM\OneToMany(mappedBy: 'existingStudent', targetEntity: PreRegistration::class)]
    private Collection $reinscriptions;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
        $this->grades = new ArrayCollection();
        $this->studentFees = new ArrayCollection();
        $this->reinscriptions = new ArrayCollection();
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

    public function getFirstName(): ?string
    {
        return $this->firstName;
    }

    public function setFirstName(string $firstName): static
    {
        $this->firstName = $firstName;
        return $this;
    }

    public function getLastName(): ?string
    {
        return $this->lastName;
    }

    public function setLastName(string $lastName): static
    {
        $this->lastName = $lastName;
        return $this;
    }

    public function getFullName(): string
    {
        if ($this->firstName && $this->lastName) {
            return $this->firstName . ' ' . $this->lastName;
        }
        return 'Élève';
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function setPhone(?string $phone): static
    {
        $this->phone = $phone;
        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): static
    {
        $this->email = $email;
        return $this;
    }

    public function getAddress(): ?string
    {
        return $this->address;
    }

    public function setAddress(?string $address): static
    {
        $this->address = $address;
        return $this;
    }

    public function getDateOfBirth(): ?\DateTimeInterface
    {
        return $this->dateOfBirth;
    }

    public function setDateOfBirth(?\DateTimeInterface $dateOfBirth): static
    {
        $this->dateOfBirth = $dateOfBirth;
        return $this;
    }

    public function getGender(): ?string
    {
        return $this->gender;
    }

    public function setGender(?string $gender): static
    {
        $this->gender = $gender;
        return $this;
    }

    public function getMatriculeInterne(): ?string
    {
        return $this->matriculeInterne;
    }

    public function setMatriculeInterne(?string $matriculeInterne): static
    {
        $this->matriculeInterne = $matriculeInterne;
        return $this;
    }

    public function getMatriculeNational(): ?string
    {
        return $this->matriculeNational;
    }

    public function setMatriculeNational(?string $matriculeNational): static
    {
        $this->matriculeNational = $matriculeNational;
        return $this;
    }

    public function getPlaceOfBirth(): ?string
    {
        return $this->placeOfBirth;
    }

    public function setPlaceOfBirth(?string $placeOfBirth): static
    {
        $this->placeOfBirth = $placeOfBirth;
        return $this;
    }

    public function getNationality(): ?string
    {
        return $this->nationality;
    }

    public function setNationality(?string $nationality): static
    {
        $this->nationality = $nationality;
        return $this;
    }

    public function getBirthCertificateNumber(): ?string
    {
        return $this->birthCertificateNumber;
    }

    public function setBirthCertificateNumber(?string $birthCertificateNumber): static
    {
        $this->birthCertificateNumber = $birthCertificateNumber;
        return $this;
    }

    public function getCmuNumber(): ?string
    {
        return $this->cmuNumber;
    }

    public function setCmuNumber(?string $cmuNumber): static
    {
        $this->cmuNumber = $cmuNumber;
        return $this;
    }

    public function getLastSchoolAttended(): ?string
    {
        return $this->lastSchoolAttended;
    }

    public function setLastSchoolAttended(?string $lastSchoolAttended): static
    {
        $this->lastSchoolAttended = $lastSchoolAttended;
        return $this;
    }

    public function isRepeating(): bool
    {
        return $this->getLatestRegistration()?->isRepeating() ?? false;
    }

    public function getPhoto(): ?string
    {
        return $this->photo;
    }

    public function setPhoto(?string $photo): static
    {
        $this->photo = $photo;
        return $this;
    }

    public function getParentFunction(): ?string
    {
        return $this->parentFunction;
    }

    public function setParentFunction(?string $parentFunction): static
    {
        $this->parentFunction = $parentFunction;
        return $this;
    }

    public function getParentAddress(): ?string
    {
        return $this->parentAddress;
    }

    public function setParentAddress(?string $parentAddress): static
    {
        $this->parentAddress = $parentAddress;
        return $this;
    }

    public function getParentName(): ?string
    {
        return $this->parentName;
    }

    public function setParentName(?string $parentName): static
    {
        $this->parentName = $parentName;
        return $this;
    }

    public function getParentPhone(): ?string
    {
        return $this->parentPhone;
    }

    public function setParentPhone(?string $parentPhone): static
    {
        $this->parentPhone = $parentPhone;
        return $this;
    }

    public function getParentEmail(): ?string
    {
        return $this->parentEmail;
    }

    public function setParentEmail(?string $parentEmail): static
    {
        $this->parentEmail = $parentEmail;
        return $this;
    }

    public function getEmergencyContact(): ?string
    {
        return $this->emergencyContact;
    }

    public function setEmergencyContact(?string $emergencyContact): static
    {
        $this->emergencyContact = $emergencyContact;
        return $this;
    }

    public function getEmergencyPhone(): ?string
    {
        return $this->emergencyPhone;
    }

    public function setEmergencyPhone(?string $emergencyPhone): static
    {
        $this->emergencyPhone = $emergencyPhone;
        return $this;
    }

    public function getMedicalInfo(): ?string
    {
        return $this->medicalInfo;
    }

    public function setMedicalInfo(?string $medicalInfo): static
    {
        $this->medicalInfo = $medicalInfo;
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

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;
        return $this;
    }

    public function isAffecte(): bool
    {
        return $this->status === 'affecte';
    }

    public function getStatusLabel(): string
    {
        return match($this->status) {
            'affecte' => 'Affecté',
            'non_affecte' => 'Non affecté',
            default => 'Inconnu'
        };
    }

    public function getStatusColor(): string
    {
        return match($this->status) {
            'affecte' => 'success',
            'non_affecte' => 'secondary',
            default => 'secondary'
        };
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

    /**
     * Niveau / classe / année courants : déduits de l'inscription la plus récente.
     * Le rattachement scolaire est porté par Registration (plus de colonnes sur student).
     */
    public function getLevel(): ?Level
    {
        return $this->getLatestRegistration()?->getLevel();
    }

    public function getClassroom(): ?Classroom
    {
        return $this->getLatestRegistration()?->getClassroom();
    }

    public function getSchoolYear(): ?SchoolYear
    {
        return $this->getLatestRegistration()?->getSchoolYear();
    }

    public function getParentUser(): ?User
    {
        return $this->parentUser;
    }

    public function setParentUser(?User $parentUser): static
    {
        $this->parentUser = $parentUser;
        return $this;
    }

    /**
     * @return Collection<int, Grade>
     */
    public function getGrades(): Collection
    {
        return $this->grades;
    }

    public function addGrade(Grade $grade): static
    {
        if (!$this->grades->contains($grade)) {
            $this->grades->add($grade);
            $grade->setStudent($this);
        }

        return $this;
    }

    public function removeGrade(Grade $grade): static
    {
        if ($this->grades->removeElement($grade)) {
            // set the owning side to null (unless already changed)
            if ($grade->getStudent() === $this) {
                $grade->setStudent(null);
            }
        }

        return $this;
    }

    public function getPreRegistration(): ?PreRegistration
    {
        return $this->preRegistration;
    }

    public function setPreRegistration(?PreRegistration $preRegistration): static
    {
        $this->preRegistration = $preRegistration;
        return $this;
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
            $studentFee->setStudent($this);
        }

        return $this;
    }

    public function removeStudentFee(StudentFee $studentFee): static
    {
        if ($this->studentFees->removeElement($studentFee)) {
            if ($studentFee->getStudent() === $this) {
                $studentFee->setStudent(null);
            }
        }

        return $this;
    }

    /**
     * Préinscriptions de réinscription (côté inverse de PreRegistration.existingStudent).
     *
     * @return Collection<int, PreRegistration>
     */
    public function getReinscriptions(): Collection
    {
        return $this->reinscriptions;
    }

    /**
     * Historique des inscriptions de l'élève, reconstitué à partir de ses préinscriptions :
     *  - la préinscription d'origine (nouvel élève) → son inscription ;
     *  - les préinscriptions de réinscription (ancien élève) → leurs inscriptions.
     * L'inscription n'étant plus liée directement à l'élève, elle est atteinte via la
     * préinscription qui l'a produite.
     *
     * @return Registration[]
     */
    public function getRegistrations(): array
    {
        $registrations = [];

        if ($this->preRegistration?->getRegistration() !== null) {
            $registrations[] = $this->preRegistration->getRegistration();
        }

        foreach ($this->reinscriptions as $preRegistration) {
            $registration = $preRegistration->getRegistration();
            if ($registration !== null && !in_array($registration, $registrations, true)) {
                $registrations[] = $registration;
            }
        }

        return $registrations;
    }

    /**
     * Inscription de l'élève pour une année scolaire donnée, le cas échéant.
     */
    public function getRegistrationForYear(?SchoolYear $year): ?Registration
    {
        if (!$year) {
            return null;
        }

        foreach ($this->getRegistrations() as $registration) {
            if ($registration->getSchoolYear()?->getId() === $year->getId()) {
                return $registration;
            }
        }

        return null;
    }

    /**
     * Registration de scolarité à considérer : celle de l'année donnée (par id) si
     * elle existe, sinon l'inscription la plus récente. Sert aux lectures financières
     * (espace parent, etc.) pour cibler la bonne année.
     */
    public function getScolariteRegistration(?int $yearId = null): ?Registration
    {
        if ($yearId !== null) {
            foreach ($this->getRegistrations() as $registration) {
                if ($registration->getSchoolYear()?->getId() === $yearId) {
                    return $registration;
                }
            }
        }

        return $this->getLatestRegistration();
    }

    /**
     * Registration la plus récente (année scolaire la plus récente).
     */
    public function getLatestRegistration(): ?Registration
    {
        $latest = null;
        foreach ($this->getRegistrations() as $registration) {
            $start = $registration->getSchoolYear()?->getStartDate();
            if ($start === null) {
                continue;
            }
            $latestStart = $latest?->getSchoolYear()?->getStartDate();
            if ($latestStart === null || $start > $latestStart) {
                $latest = $registration;
            }
        }

        return $latest;
    }

    public function getTotalTuition(): float
    {
        $total = 0;
        foreach ($this->studentFees as $sf) {
            if ($sf->getFee()?->isActive()) {
                $total += (float) $sf->getAmount();
            }
        }
        return $total;
    }

    public function getTotalPaid(): float
    {
        $total = 0;
        foreach ($this->studentFees as $sf) {
            if ($sf->getFee()?->isActive()) {
                $total += (float) $sf->getPaidAmount();
            }
        }
        return $total;
    }

    public function getRemainingTuition(): float
    {
        return max(0, $this->getTotalTuition() - $this->getTotalPaid());
    }

    public function __toString(): string
    {
        return $this->getFullName();
    }
}

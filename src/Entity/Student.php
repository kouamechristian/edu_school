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
    private ?string $studentNumber = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $parentName = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $parentPhone = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $parentEmail = null;

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

    #[ORM\Column(length: 20)]
    #[Assert\Choice(choices: ['affecte', 'non_affecte'], message: 'Le statut doit être Affecté ou Non affecté')]
    private ?string $status = 'affecte';

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $updatedAt = null;

    #[ORM\ManyToOne(targetEntity: School::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?School $school = null;

    #[ORM\ManyToOne(targetEntity: Level::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?Level $level = null;

    #[ORM\ManyToOne(targetEntity: Classroom::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?Classroom $classroom = null;

    #[ORM\ManyToOne(targetEntity: SchoolYear::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?SchoolYear $schoolYear = null;

    #[ORM\OneToMany(mappedBy: 'student', targetEntity: Grade::class)]
    private Collection $grades;

    #[ORM\OneToMany(mappedBy: 'student', targetEntity: SchoolGroup::class)]
    private Collection $schoolGroups;

    #[ORM\OneToOne(inversedBy: 'student', targetEntity: PreRegistration::class, cascade: ['persist', 'remove'])]
    #[ORM\JoinColumn(name: 'pre_registration_id', referencedColumnName: 'id')]
    private ?PreRegistration $preRegistration = null;

    #[ORM\OneToMany(mappedBy: 'student', targetEntity: StudentFee::class, cascade: ['persist', 'remove'])]
    private Collection $studentFees;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
        $this->grades = new ArrayCollection();
        $this->schoolGroups = new ArrayCollection();
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

    public function getStudentNumber(): ?string
    {
        return $this->studentNumber;
    }

    public function setStudentNumber(?string $studentNumber): static
    {
        $this->studentNumber = $studentNumber;
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
            'affecte' => 'Affecté',
            'non_affecte' => 'Non affecté',
            default => $this->status ?? 'Inconnu'
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

    public function getLevel(): ?Level
    {
        return $this->level;
    }

    public function setLevel(?Level $level): static
    {
        $this->level = $level;
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

    public function getSchoolYear(): ?SchoolYear
    {
        return $this->schoolYear;
    }

    public function setSchoolYear(?SchoolYear $schoolYear): static
    {
        $this->schoolYear = $schoolYear;
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

    /**
     * @return Collection<int, SchoolGroup>
     */
    public function getSchoolGroups(): Collection
    {
        return $this->schoolGroups;
    }

    public function addSchoolGroup(SchoolGroup $schoolGroup): static
    {
        if (!$this->schoolGroups->contains($schoolGroup)) {
            $this->schoolGroups->add($schoolGroup);
            $schoolGroup->setStudent($this);
        }

        return $this;
    }

    public function removeSchoolGroup(SchoolGroup $schoolGroup): static
    {
        if ($this->schoolGroups->removeElement($schoolGroup)) {
            // set the owning side to null (unless already changed)
            if ($schoolGroup->getStudent() === $this) {
                $schoolGroup->setStudent(null);
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

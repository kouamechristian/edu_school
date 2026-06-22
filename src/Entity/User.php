<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: '`user`')]
#[ORM\HasLifecycleCallbacks]
#[UniqueEntity(fields: ['username'], message: 'Ce nom d\'utilisateur est déjà utilisé')]
#[UniqueEntity(fields: ['email'], message: 'Cet email est déjà utilisé')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180, unique: true)]
    #[Assert\NotBlank(message: 'Le nom d\'utilisateur est obligatoire')]
    #[Assert\Length(min: 3, max: 180, minMessage: 'Le nom d\'utilisateur doit contenir au moins {{ limit }} caractères')]
    private ?string $username = null;

    #[ORM\Column(length: 180, unique: true)]
    #[Assert\NotBlank(message: 'L\'email est obligatoire')]
    #[Assert\Email(message: 'L\'email n\'est pas valide')]
    private ?string $email = null;

    /**
     * @var list<string> The user roles
     */
    #[ORM\Column]
    private array $roles = [];

    /**
     * @var string The hashed password
     */
    #[ORM\Column]
    private ?string $password = null;

    #[ORM\Column]
    private bool $isActive = true;

    /**
     * Lorsque true, l'utilisateur est forcé de changer son mot de passe à la
     * prochaine connexion (ex. compte créé automatiquement avec un mot de passe
     * par défaut).
     */
    #[ORM\Column]
    private bool $mustChangePassword = false;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $lastLogin = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $avatar = null;

    #[ORM\Column(length: 100, nullable: true)]
    #[Assert\Length(max: 100)]
    private ?string $firstName = null;

    #[ORM\Column(length: 100, nullable: true)]
    #[Assert\Length(max: 100)]
    private ?string $lastName = null;

    #[ORM\Column(length: 20, nullable: true)]
    #[Assert\Length(max: 20)]
    private ?string $phone = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $address = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $dateOfBirth = null;

    #[ORM\Column(length: 1, nullable: true)]
    #[Assert\Choice(choices: ['M', 'F'], message: 'Le genre doit être M ou F')]
    private ?string $gender = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $updatedAt = null;

    #[ORM\Column(length: 50, nullable: true)]
    #[Assert\Choice(choices: ['admin', 'directeur', 'enseignant', 'personnel', 'parent'])]
    private ?string $userType = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true)]
    private ?SchoolGroup $schoolGroup = null;

    #[ORM\OneToOne(mappedBy: 'user', targetEntity: Employee::class, cascade: ['remove'])]
    private ?Employee $employee = null;

    #[ORM\ManyToMany(targetEntity: School::class, inversedBy: 'users')]
    #[ORM\JoinTable(
        name: 'user_school',
        joinColumns: [new ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id')],
        inverseJoinColumns: [new ORM\JoinColumn(name: 'school_id', referencedColumnName: 'id')]
    )]
    private Collection $schools;

    /**
     * Dernier établissement sélectionné par l'utilisateur (contexte de bascule).
     *
     * Persisté en base afin que le choix survive à la déconnexion et à l'expiration
     * de la session : à la reconnexion, l'utilisateur retrouve son établissement et
     * non le premier de la liste.
     */
    #[ORM\ManyToOne(targetEntity: School::class)]
    #[ORM\JoinColumn(name: 'last_school_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?School $lastSchool = null;

    /**
     * Enfants rattachés à ce parent (auto-association via matricule + date de naissance).
     *
     * Relation 1—N : côté inverse de Student.parentUser. Comme un élève ne référence
     * qu'UN parent, le schéma garantit qu'un enfant ne peut être rattaché à plusieurs
     * comptes parents à la fois. Ce lien explicite complète — sans le remplacer — le lien
     * historique par e-mail (Student.parentEmail ↔ User.email).
     *
     * @var Collection<int, Student>
     */
    #[ORM\OneToMany(mappedBy: 'parentUser', targetEntity: Student::class)]
    private Collection $children;

    /**
     * Matières enseignées par cet utilisateur (enseignant).
     *
     * @var Collection<int, Subject>
     */
    #[ORM\ManyToMany(targetEntity: Subject::class)]
    #[ORM\JoinTable(
        name: 'user_teaching_subject',
        joinColumns: [new ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id')],
        inverseJoinColumns: [new ORM\JoinColumn(name: 'subject_id', referencedColumnName: 'id')]
    )]
    private Collection $teachingSubjects;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
        $this->roles = ['ROLE_USER'];
        $this->schools = new ArrayCollection();
        $this->children = new ArrayCollection();
        $this->teachingSubjects = new ArrayCollection();
    }

    #[ORM\PostLoad]
    public function initializeCollections(): void
    {
        if (!isset($this->schools)) {
            $this->schools = new ArrayCollection();
        }
        if (!isset($this->children)) {
            $this->children = new ArrayCollection();
        }
        if (!isset($this->teachingSubjects)) {
            $this->teachingSubjects = new ArrayCollection();
        }
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

    public function getUsername(): ?string
    {
        return $this->username;
    }

    public function setUsername(string $username): static
    {
        $this->username = $username;
        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;
        return $this;
    }

    /**
     * A visual identifier that represents this user.
     *
     * @see UserInterface
     */
    public function getUserIdentifier(): string
    {
        return (string) $this->username;
    }

    /**
     * @see UserInterface
     *
     * @return list<string>
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        // guarantee every user at least has ROLE_USER
        $roles[] = 'ROLE_USER';

        return array_unique($roles);
    }

    /**
     * @param list<string> $roles
     */
    public function setRoles(array $roles): static
    {
        $this->roles = $roles;
        return $this;
    }

    /**
     * @see PasswordAuthenticatedUserInterface
     */
    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;
        return $this;
    }

    /**
     * @see UserInterface
     */
    public function eraseCredentials(): void
    {
        // If you store any temporary, sensitive data on the user, clear it here
        // $this->plainPassword = null;
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

    public function isMustChangePassword(): bool
    {
        return $this->mustChangePassword;
    }

    public function setMustChangePassword(bool $mustChangePassword): static
    {
        $this->mustChangePassword = $mustChangePassword;
        return $this;
    }

    public function getLastLogin(): ?\DateTimeInterface
    {
        return $this->lastLogin;
    }

    public function setLastLogin(?\DateTimeInterface $lastLogin): static
    {
        $this->lastLogin = $lastLogin;
        return $this;
    }

    public function getAvatar(): ?string
    {
        return $this->avatar;
    }

    public function setAvatar(?string $avatar): static
    {
        $this->avatar = $avatar;
        return $this;
    }

    public function getFirstName(): ?string
    {
        return $this->firstName;
    }

    public function setFirstName(?string $firstName): static
    {
        $this->firstName = $firstName;
        return $this;
    }

    public function getLastName(): ?string
    {
        return $this->lastName;
    }

    public function setLastName(?string $lastName): static
    {
        $this->lastName = $lastName;
        return $this;
    }

    public function getFullName(): string
    {
        if ($this->firstName && $this->lastName) {
            return $this->firstName . ' ' . $this->lastName;
        }
        return $this->username;
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

    public function getUserType(): ?string
    {
        return $this->userType;
    }

    public function setUserType(?string $userType): static
    {
        $this->userType = $userType;
        return $this;
    }
    
    /**
     * Crée un Employee associé à ce User
     */
    public function createEmployee(): Employee
    {
        if ($this->employee) {
            return $this->employee;
        }
        
        $employee = new Employee();
        $employee->setUser($this);
        $employee->setFirstName($this->firstName ?? '');
        $employee->setLastName($this->lastName ?? '');
        $employee->setPhone($this->phone);
        $employee->setAddress($this->address);
        $employee->setDateOfBirth($this->dateOfBirth);
        $employee->setGender($this->gender);
        
        // Mapper le userType vers employeeType
        $employeeType = match($this->userType) {
            'enseignant' => 'enseignant',
            'personnel' => 'personnel',
            'directeur' => 'directeur',
            default => 'personnel'
        };
        $employee->setEmployeeType($employeeType);
        
        $this->employee = $employee;
        return $employee;
    }

    public function getUserTypeLabel(): string
    {
        return match($this->userType) {
            'admin' => 'Administrateur',
            'directeur' => 'Directeur',
            'enseignant' => 'Enseignant',
            'personnel' => 'Personnel',
            'parent' => 'Parent',
            default => 'Utilisateur'
        };
    }

    public function getInitials(): string
    {
        if ($this->firstName && $this->lastName) {
            return strtoupper(substr($this->firstName, 0, 1) . substr($this->lastName, 0, 1));
        }
        return strtoupper(substr($this->username, 0, 2));
    }

    public function hasRole(string $role): bool
    {
        return in_array($role, $this->getRoles());
    }

    /**
     * Libellés lisibles des rôles applicatifs.
     */
    public const ROLE_LABELS = [
        'ROLE_SUPER_ADMIN' => 'Super administrateur',
        'ROLE_FONDATEUR' => 'Fondateur',
        'ROLE_ADMIN' => 'Administrateur',
        'ROLE_DIRECTEUR' => 'Directeur',
        'ROLE_INSCRIPTION' => 'Agent d\'inscription',
        'ROLE_CAISSE' => 'Caissier',
        'ROLE_RECOUVREMENT' => 'Agent de recouvrement',
        'ROLE_RH' => 'Ressources Humaines',
        'ROLE_ENSEIGNANT' => 'Enseignant',
        'ROLE_EDUCATEUR' => 'Éducateur',
        'ROLE_CORRESPONDANT_FICHIER' => 'Correspondant fichier',
        'ROLE_PARENT' => 'Parent',
        'ROLE_USER' => 'Utilisateur',
    ];

    public function getRoleLabel(string $role): string
    {
        return self::ROLE_LABELS[$role] ?? $role;
    }

    /**
     * @return Collection<int, School>
     */
    public function getSchools(): Collection
    {
        if (!isset($this->schools)) {
            $this->schools = new ArrayCollection();
        }
        return $this->schools;
    }

    public function addSchool(School $school): static
    {
        if (!$this->schools->contains($school)) {
            $this->schools->add($school);
        }

        return $this;
    }

    public function removeSchool(School $school): static
    {
        $this->schools->removeElement($school);

        return $this;
    }

    public function getLastSchool(): ?School
    {
        return $this->lastSchool;
    }

    public function setLastSchool(?School $lastSchool): static
    {
        $this->lastSchool = $lastSchool;

        return $this;
    }

    /**
     * @return Collection<int, Student>
     */
    public function getChildren(): Collection
    {
        if (!isset($this->children)) {
            $this->children = new ArrayCollection();
        }
        return $this->children;
    }

    public function addChild(Student $child): static
    {
        if (!$this->getChildren()->contains($child)) {
            $this->children->add($child);
            $child->setParentUser($this);
        }

        return $this;
    }

    public function removeChild(Student $child): static
    {
        if ($this->getChildren()->removeElement($child)) {
            // Dissocier le côté propriétaire de la relation.
            if ($child->getParentUser() === $this) {
                $child->setParentUser(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Subject>
     */
    public function getTeachingSubjects(): Collection
    {
        if (!isset($this->teachingSubjects)) {
            $this->teachingSubjects = new ArrayCollection();
        }
        return $this->teachingSubjects;
    }

    public function addTeachingSubject(Subject $subject): static
    {
        if (!$this->getTeachingSubjects()->contains($subject)) {
            $this->teachingSubjects->add($subject);
        }

        return $this;
    }

    public function removeTeachingSubject(Subject $subject): static
    {
        $this->getTeachingSubjects()->removeElement($subject);

        return $this;
    }

    public function getSchoolGroup(): ?SchoolGroup
    {
        return $this->schoolGroup;
    }

    public function setSchoolGroup(?SchoolGroup $schoolGroup): static
    {
        $this->schoolGroup = $schoolGroup;

        return $this;
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

    public function __toString(): string
    {
        return $this->getFullName();
    }
}


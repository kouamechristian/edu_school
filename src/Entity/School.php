<?php

namespace App\Entity;

use App\Repository\SchoolRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: SchoolRepository::class)]
#[ORM\HasLifecycleCallbacks]
class School
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Le nom de l\'établissement est obligatoire')]
    #[Assert\Length(max: 255)]
    private ?string $name = null;

    #[ORM\Column(length: 50, unique: true)]
    #[Assert\Length(max: 50)]
    private ?string $code = null;

    #[ORM\Column(length: 50)]
    #[Assert\NotBlank]
    #[Assert\Choice(choices: ['PRESCOLAIRE-PRIMAIRE', 'SECONDAIRE GENERAL', 'TECHNIQUE ET PROFESSIONNEL', 'UNIVERSITE'])]
    private ?string $type = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $address = null;

    #[ORM\Column(length: 20, nullable: true)]
    #[Assert\Length(max: 20)]
    private ?string $phone = null;

    #[ORM\Column(length: 100, nullable: true)]
    #[Assert\Email(message: 'L\'email n\'est pas valide')]
    #[Assert\Length(max: 100)]
    private ?string $email = null;

    #[ORM\Column(length: 100, nullable: true)]
    #[Assert\Length(max: 100)]
    private ?string $director = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $logo = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $cachetDirection = null;

    #[ORM\Column(length: 20, nullable: true)]
    #[Assert\Length(max: 20)]
    private ?string $badgeBackgroundColor = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Length(max: 255)]
    private ?string $sousTutelle = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $updatedAt = null;

    #[ORM\Column]
    private bool $isActive = true;

    #[ORM\ManyToOne(inversedBy: 'schools')]
    #[ORM\JoinColumn(nullable: true)]
    private ?SchoolGroup $schoolGroup = null;

    #[ORM\ManyToMany(targetEntity: User::class, mappedBy: 'schools')]
    private Collection $users;

    public function __construct()
    {
        $this->users = new ArrayCollection();
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
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

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;
        return $this;
    }

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(string $code): static
    {
        $this->code = $code;
        return $this;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;
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

    public function getDirector(): ?string
    {
        return $this->director;
    }

    public function setDirector(?string $director): static
    {
        $this->director = $director;
        return $this;
    }

    public function getLogo(): ?string
    {
        return $this->logo;
    }

    public function setLogo(?string $logo): static
    {
        $this->logo = $logo;
        return $this;
    }

    public function getCachetDirection(): ?string
    {
        return $this->cachetDirection;
    }

    public function setCachetDirection(?string $cachetDirection): static
    {
        $this->cachetDirection = $cachetDirection;
        return $this;
    }

    public function getBadgeBackgroundColor(): ?string
    {
        return $this->badgeBackgroundColor;
    }

    public function setBadgeBackgroundColor(?string $badgeBackgroundColor): static
    {
        $this->badgeBackgroundColor = $badgeBackgroundColor;
        return $this;
    }

    public function getSousTutelle(): ?string
    {
        return $this->sousTutelle;
    }

    public function setSousTutelle(?string $sousTutelle): static
    {
        $this->sousTutelle = $sousTutelle;
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

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;
        return $this;
    }

    public function __toString(): string
    {
        return $this->name ?? '';
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

    public function getTypeLabel(): string
    {
        return match($this->type) {
            'PRESCOLAIRE-PRIMAIRE' => 'Préscolaire-Primaire',
            'SECONDAIRE GENERAL' => 'Secondaire Général',
            'TECHNIQUE ET PROFESSIONNEL' => 'Technique et Professionnel',
            'UNIVERSITE' => 'Université',
            default => $this->type
        };
    }
}



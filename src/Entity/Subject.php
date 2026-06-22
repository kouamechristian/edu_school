<?php

namespace App\Entity;

use App\Repository\SubjectRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: SubjectRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Subject
{
    /** Langue vivante (LV). */
    public const LV_CHOICES = ['AUCUN', 'ALLEMAND', 'ESPAGNOLE'];

    /** Matière de conduite (oui / non). */
    public const CONDUITE_CHOICES = ['OUI', 'NON'];

    /** Art / musique. */
    public const ART_MUSIQUE_CHOICES = ['MUSIQUE', 'ART PLASTIQUE'];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank(message: 'Le nom de la matière est obligatoire')]
    #[Assert\Length(max: 100)]
    private ?string $name = null;

    #[ORM\Column(length: 50, unique: true)]
    #[Assert\Length(max: 50)]
    private ?string $code = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true)]
    private ?School $school = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true)]
    private ?Level $level = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 5, scale: 2, nullable: true)]
    #[Assert\PositiveOrZero]
    private ?string $coefficient = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\ManyToOne(targetEntity: SubjectType::class)]
    #[ORM\JoinColumn(name: 'type_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?SubjectType $type = null;


    #[ORM\Column(nullable: true)]
    #[Assert\PositiveOrZero]
    private ?int $hoursPerWeek = null;

    #[ORM\Column(nullable: true)]
    #[Assert\PositiveOrZero(message: 'Le numéro d\'ordre sur le bulletin doit être positif.')]
    private ?int $bulletinOrderNumber = null;

    #[ORM\Column(length: 7, nullable: true)]
    #[Assert\Regex(pattern: '/^#[0-9A-F]{6}$/i', message: 'Format couleur invalide (ex: #FF5733)')]
    private ?string $color = null;

    /** Langue vivante (LV) : AUCUN / ALLEMAND / ESPAGNOLE. */
    #[ORM\Column(length: 20, nullable: true, options: ['default' => 'AUCUN'])]
    #[Assert\Choice(choices: self::LV_CHOICES, message: 'Veuillez choisir une LV valide.')]
    private ?string $lv = 'AUCUN';

    /** Matière de conduite : OUI / NON. */
    #[ORM\Column(name: 'matiere_conduite', length: 5, nullable: true)]
    #[Assert\Choice(choices: self::CONDUITE_CHOICES, message: 'Veuillez choisir OUI ou NON.')]
    private ?string $matiereConduite = null;

    /** Art / musique : MUSIQUE / ART PLASTIQUE. */
    #[ORM\Column(name: 'art_musique', length: 20, nullable: true)]
    #[Assert\Choice(choices: self::ART_MUSIQUE_CHOICES, message: 'Veuillez choisir une valeur valide.')]
    private ?string $artMusique = null;

    /** Barème de la note sur le bulletin (ex. 20). */
    #[ORM\Column(name: 'note_sur_bulletin', nullable: true)]
    #[Assert\Positive(message: 'La note sur le bulletin doit être un nombre positif.')]
    private ?int $noteSurBulletin = null;

    #[ORM\Column]
    private bool $isActive = true;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $updatedAt = null;

    /**
     * Équivalence du référentiel rattachée à cette matière (au plus une).
     */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?SubjectEquivalent $subjectEquivalent = null;

    public function __construct()
    {
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

    public function getCoefficient(): ?string
    {
        return $this->coefficient;
    }

    public function setCoefficient(?string $coefficient): static
    {
        $this->coefficient = $coefficient;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;
        return $this;
    }

    public function getType(): ?SubjectType
    {
        return $this->type;
    }

    public function setType(?SubjectType $type): static
    {
        $this->type = $type;
        return $this;
    }

    public function getTypeLabel(): string
    {
        return $this->type?->getLabel() ?? 'Non défini';
    }

    public function getHoursPerWeek(): ?int
    {
        return $this->hoursPerWeek;
    }

    public function setHoursPerWeek(?int $hoursPerWeek): static
    {
        $this->hoursPerWeek = $hoursPerWeek;
        return $this;
    }

    public function getBulletinOrderNumber(): ?int
    {
        return $this->bulletinOrderNumber;
    }

    public function setBulletinOrderNumber(?int $bulletinOrderNumber): static
    {
        $this->bulletinOrderNumber = $bulletinOrderNumber;
        return $this;
    }

    public function getColor(): ?string
    {
        return $this->color;
    }

    public function setColor(?string $color): static
    {
        $this->color = $color;
        return $this;
    }

    public function getLv(): ?string
    {
        return $this->lv;
    }

    public function setLv(?string $lv): static
    {
        $this->lv = $lv;
        return $this;
    }

    public function getMatiereConduite(): ?string
    {
        return $this->matiereConduite;
    }

    public function setMatiereConduite(?string $matiereConduite): static
    {
        $this->matiereConduite = $matiereConduite;
        return $this;
    }

    public function getArtMusique(): ?string
    {
        return $this->artMusique;
    }

    public function setArtMusique(?string $artMusique): static
    {
        $this->artMusique = $artMusique;
        return $this;
    }

    public function getNoteSurBulletin(): ?int
    {
        return $this->noteSurBulletin;
    }

    public function setNoteSurBulletin(?int $noteSurBulletin): static
    {
        $this->noteSurBulletin = $noteSurBulletin;
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

    public function getSubjectEquivalent(): ?SubjectEquivalent
    {
        return $this->subjectEquivalent;
    }

    public function setSubjectEquivalent(?SubjectEquivalent $subjectEquivalent): static
    {
        $this->subjectEquivalent = $subjectEquivalent;
        return $this;
    }

    public function __toString(): string
    {
        return $this->name ?? '';
    }
}


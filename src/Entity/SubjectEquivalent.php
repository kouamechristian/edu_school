<?php

namespace App\Entity;

use App\Repository\SubjectEquivalentRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Équivalent (correspondance) d'une matière.
 *
 * Une matière (Subject) peut avoir plusieurs équivalents (N-1 : plusieurs équivalents
 * pointent vers une matière), et chaque équivalent appartient à un établissement
 * (1-N : un établissement possède plusieurs équivalents).
 */
#[ORM\Entity(repositoryClass: SubjectEquivalentRepository::class)]
#[ORM\Table(name: 'subject_equivalent')]
#[ORM\HasLifecycleCallbacks]
class SubjectEquivalent
{
    /**
     * Liste des matières disponibles pour le champ « matière parente » (subject_paren).
     */
    public const SUBJECTS = [
        'FRANÇAIS',
        'MATHÉMATIQUE',
        'HISTOIRE-GÉOGRAPHIE',
        'PHYSIQUE-CHIMIE',
        'SVT',
        'PHILOSOPHIE',
        'ANGLAIS',
        'ESPAGNOL',
        'ALLEMAND',
        'EPS',
        'MUSIQUE',
        'EDHC',
        'CONDUITE',
        'ART-PLASTIQUE',
        'DICTÉE',
        "ACTIVITÉ D'ÉVEIL AU MILIEU",
        'EXPLOITATION DE TEXTE',
        'COMPOSITION FRANÇAISE',
        'ORTHOGRAPHE',
        'EXPRESSION ORALE',
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(nullable: true)]
    private ?int $numeroOrdre = null;

    #[ORM\Column(length: 50)]
    #[Assert\NotBlank(message: 'Le code est obligatoire.')]
    #[Assert\Length(max: 50)]
    private ?string $code = null;

    #[ORM\Column(length: 150)]
    #[Assert\NotBlank(message: 'Le libellé est obligatoire.')]
    #[Assert\Length(max: 150)]
    private ?string $libelle = null;

    /**
     * Matière parente (choisie dans SubjectEquivalent::SUBJECTS).
     */
    #[ORM\Column(name: 'subject_paren', length: 100, nullable: true)]
    #[Assert\Choice(choices: self::SUBJECTS, message: 'Veuillez choisir une matière valide.')]
    private ?string $subjectParent = null;

    /**
     * Établissement propriétaire (1 établissement → N équivalents).
     */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull(message: 'L\'établissement est obligatoire.')]
    private ?School $school = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $updatedAt = null;

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

    public function getNumeroOrdre(): ?int
    {
        return $this->numeroOrdre;
    }

    public function setNumeroOrdre(?int $numeroOrdre): static
    {
        $this->numeroOrdre = $numeroOrdre;
        return $this;
    }

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(?string $code): static
    {
        $this->code = $code;
        return $this;
    }

    public function getLibelle(): ?string
    {
        return $this->libelle;
    }

    public function setLibelle(?string $libelle): static
    {
        $this->libelle = $libelle;
        return $this;
    }

    public function getSubjectParent(): ?string
    {
        return $this->subjectParent;
    }

    public function setSubjectParent(?string $subjectParent): static
    {
        $this->subjectParent = $subjectParent;
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

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function __toString(): string
    {
        return trim(($this->code ?? '') . ' - ' . ($this->libelle ?? ''), ' -');
    }
}

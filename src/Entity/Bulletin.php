<?php

namespace App\Entity;

use App\Repository\BulletinRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Un bulletin défini par l'utilisateur : un libellé, une base de moyenne (« moyenne sur »),
 * un niveau et une période. À sa création, on liste tous les élèves du niveau choisi
 * avec leur moyenne (ramenée sur la base choisie).
 */
#[ORM\Entity(repositoryClass: BulletinRepository::class)]
#[ORM\Table(name: 'bulletin')]
#[ORM\HasLifecycleCallbacks]
class Bulletin
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 150)]
    #[Assert\NotBlank(message: 'Le libellé est obligatoire.')]
    #[Assert\Length(max: 150)]
    private ?string $libelle = null;

    /**
     * Base de la moyenne (ex. 20 = moyenne sur 20).
     */
    #[ORM\Column]
    #[Assert\NotNull(message: 'La base de la moyenne est obligatoire.')]
    #[Assert\Positive(message: 'La base de la moyenne doit être un nombre positif.')]
    private ?int $moyenneSur = 20;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull(message: 'Le niveau est obligatoire.')]
    private ?Level $level = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull(message: 'La période est obligatoire.')]
    private ?Period $period = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?School $school = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?SchoolYear $schoolYear = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true)]
    private ?User $createdBy = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(options: ['default' => false])]
    private bool $isValidated = false;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $validatedAt = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true)]
    private ?User $validatedBy = null;

    /**
     * Date du dernier calcul des moyennes (snapshot dans les lignes).
     */
    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $computedAt = null;

    /**
     * @var Collection<int, BulletinLine>
     */
    #[ORM\OneToMany(mappedBy: 'bulletin', targetEntity: BulletinLine::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['rank' => 'ASC'])]
    private Collection $lines;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->lines = new ArrayCollection();
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

    public function getValidatedAt(): ?\DateTimeInterface
    {
        return $this->validatedAt;
    }

    public function setValidatedAt(?\DateTimeInterface $validatedAt): static
    {
        $this->validatedAt = $validatedAt;
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

    public function getComputedAt(): ?\DateTimeInterface
    {
        return $this->computedAt;
    }

    public function setComputedAt(?\DateTimeInterface $computedAt): static
    {
        $this->computedAt = $computedAt;
        return $this;
    }

    public function isComputed(): bool
    {
        return $this->computedAt !== null;
    }

    /**
     * @return Collection<int, BulletinLine>
     */
    public function getLines(): Collection
    {
        return $this->lines;
    }

    public function addLine(BulletinLine $line): static
    {
        if (!$this->lines->contains($line)) {
            $this->lines->add($line);
            $line->setBulletin($this);
        }
        return $this;
    }

    public function clearLines(): void
    {
        $this->lines->clear();
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getMoyenneSur(): ?int
    {
        return $this->moyenneSur;
    }

    public function setMoyenneSur(?int $moyenneSur): static
    {
        $this->moyenneSur = $moyenneSur;
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

    public function getPeriod(): ?Period
    {
        return $this->period;
    }

    public function setPeriod(?Period $period): static
    {
        $this->period = $period;
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

    public function getSchoolYear(): ?SchoolYear
    {
        return $this->schoolYear;
    }

    public function setSchoolYear(?SchoolYear $schoolYear): static
    {
        $this->schoolYear = $schoolYear;
        return $this;
    }

    public function getCreatedBy(): ?User
    {
        return $this->createdBy;
    }

    public function setCreatedBy(?User $createdBy): static
    {
        $this->createdBy = $createdBy;
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
}

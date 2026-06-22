<?php

namespace App\Entity;

use App\Repository\BulletinRepository;
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
    #[ORM\JoinColumn(nullable: true)]
    private ?User $createdBy = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
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

<?php

namespace App\Entity;

use App\Repository\CycleRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: CycleRepository::class)]
class Cycle
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank(message: 'Le libellé du cycle est obligatoire')]
    #[Assert\Length(max: 100)]
    private ?string $libelle = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true)]
    private ?School $school = null;

    /**
     * Niveaux rattachés à ce cycle (un cycle regroupe un ou plusieurs niveaux).
     *
     * @var Collection<int, Level>
     */
    #[ORM\OneToMany(mappedBy: 'cycle', targetEntity: Level::class)]
    private Collection $levels;

    /**
     * Facultés rattachées à ce cycle (un cycle a une ou plusieurs facultés).
     *
     * @var Collection<int, Faculty>
     */
    #[ORM\OneToMany(mappedBy: 'cycle', targetEntity: Faculty::class)]
    private Collection $faculties;

    /**
     * Séries rattachées à ce cycle (un cycle a une ou plusieurs séries).
     *
     * @var Collection<int, Round>
     */
    #[ORM\OneToMany(mappedBy: 'cycle', targetEntity: Round::class)]
    private Collection $rounds;

    public function __construct()
    {
        $this->levels = new ArrayCollection();
        $this->faculties = new ArrayCollection();
        $this->rounds = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getLibelle(): ?string
    {
        return $this->libelle;
    }

    public function setLibelle(string $libelle): static
    {
        $this->libelle = $libelle;
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
     * @return Collection<int, Level>
     */
    public function getLevels(): Collection
    {
        return $this->levels;
    }

    public function addLevel(Level $level): static
    {
        if (!$this->levels->contains($level)) {
            $this->levels->add($level);
            $level->setCycle($this);
        }

        return $this;
    }

    public function removeLevel(Level $level): static
    {
        if ($this->levels->removeElement($level)) {
            if ($level->getCycle() === $this) {
                $level->setCycle(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Faculty>
     */
    public function getFaculties(): Collection
    {
        return $this->faculties;
    }

    public function addFaculty(Faculty $faculty): static
    {
        if (!$this->faculties->contains($faculty)) {
            $this->faculties->add($faculty);
            $faculty->setCycle($this);
        }

        return $this;
    }

    public function removeFaculty(Faculty $faculty): static
    {
        if ($this->faculties->removeElement($faculty)) {
            if ($faculty->getCycle() === $this) {
                $faculty->setCycle(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Round>
     */
    public function getRounds(): Collection
    {
        return $this->rounds;
    }

    public function addRound(Round $round): static
    {
        if (!$this->rounds->contains($round)) {
            $this->rounds->add($round);
            $round->setCycle($this);
        }

        return $this;
    }

    public function removeRound(Round $round): static
    {
        if ($this->rounds->removeElement($round)) {
            if ($round->getCycle() === $this) {
                $round->setCycle(null);
            }
        }

        return $this;
    }

    public function __toString(): string
    {
        return $this->libelle ?? '';
    }
}

<?php

namespace App\Entity;

use App\Repository\FacultyRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: FacultyRepository::class)]
class Faculty
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank(message: 'Le libellé de la faculté est obligatoire')]
    #[Assert\Length(max: 100)]
    private ?string $libelle = null;

    #[ORM\ManyToOne(targetEntity: Cycle::class, inversedBy: 'faculties')]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotBlank(message: 'Le cycle est obligatoire')]
    private ?Cycle $cycle = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true)]
    private ?School $school = null;

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

    public function getCycle(): ?Cycle
    {
        return $this->cycle;
    }

    public function setCycle(?Cycle $cycle): static
    {
        $this->cycle = $cycle;
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

    public function __toString(): string
    {
        return $this->libelle ?? '';
    }
}

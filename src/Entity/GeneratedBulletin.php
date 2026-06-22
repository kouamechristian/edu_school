<?php

namespace App\Entity;

use App\Repository\GeneratedBulletinRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Trace d'une génération de bulletins pour une classe et une période.
 * Une fois généré, les évaluations de la classe/période sont verrouillées.
 */
#[ORM\Entity(repositoryClass: GeneratedBulletinRepository::class)]
#[ORM\Table(name: 'generated_bulletin')]
#[ORM\UniqueConstraint(name: 'uniq_classroom_period', columns: ['classroom_id', 'period_id'])]
class GeneratedBulletin
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Classroom $classroom = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Period $period = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?SchoolYear $schoolYear = null;

    #[ORM\Column(options: ['default' => 0])]
    private int $studentCount = 0;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $generatedAt = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $generatedBy = null;

    public function __construct()
    {
        $this->generatedAt = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getPeriod(): ?Period
    {
        return $this->period;
    }

    public function setPeriod(?Period $period): static
    {
        $this->period = $period;
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

    public function getStudentCount(): int
    {
        return $this->studentCount;
    }

    public function setStudentCount(int $studentCount): static
    {
        $this->studentCount = $studentCount;
        return $this;
    }

    public function getGeneratedAt(): ?\DateTimeInterface
    {
        return $this->generatedAt;
    }

    public function setGeneratedAt(\DateTimeInterface $generatedAt): static
    {
        $this->generatedAt = $generatedAt;
        return $this;
    }

    public function getGeneratedBy(): ?User
    {
        return $this->generatedBy;
    }

    public function setGeneratedBy(?User $generatedBy): static
    {
        $this->generatedBy = $generatedBy;
        return $this;
    }
}

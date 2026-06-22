<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Ligne d'un bulletin : moyenne (sur la base du bulletin), rang et mention d'un élève,
 * figées au moment du calcul des moyennes (snapshot).
 */
#[ORM\Entity]
#[ORM\Table(name: 'bulletin_line')]
class BulletinLine
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'lines')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Bulletin $bulletin = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Student $student = null;

    /**
     * Moyenne ramenée sur la base du bulletin (null = non noté).
     */
    #[ORM\Column(type: 'decimal', precision: 6, scale: 2, nullable: true)]
    private ?string $average = null;

    #[ORM\Column(name: 'rang', nullable: true)]
    private ?int $rank = null;

    #[ORM\Column(length: 60, nullable: true)]
    private ?string $mention = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getBulletin(): ?Bulletin
    {
        return $this->bulletin;
    }

    public function setBulletin(?Bulletin $bulletin): static
    {
        $this->bulletin = $bulletin;
        return $this;
    }

    public function getStudent(): ?Student
    {
        return $this->student;
    }

    public function setStudent(?Student $student): static
    {
        $this->student = $student;
        return $this;
    }

    public function getAverage(): ?string
    {
        return $this->average;
    }

    public function setAverage(?string $average): static
    {
        $this->average = $average;
        return $this;
    }

    public function getRank(): ?int
    {
        return $this->rank;
    }

    public function setRank(?int $rank): static
    {
        $this->rank = $rank;
        return $this;
    }

    public function getMention(): ?string
    {
        return $this->mention;
    }

    public function setMention(?string $mention): static
    {
        $this->mention = $mention;
        return $this;
    }
}

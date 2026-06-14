<?php

namespace App\Entity;

use App\Repository\SubjectTypeRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Type de matière (référentiel) : libellé et numéro d'ordre d'affichage sur le bulletin.
 */
#[ORM\Entity(repositoryClass: SubjectTypeRepository::class)]
#[ORM\Table(name: 'subject_type')]
class SubjectType
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    #[Assert\NotBlank(message: 'Le numéro d\'ordre sur le bulletin est obligatoire.')]
    #[Assert\PositiveOrZero(message: 'Le numéro d\'ordre doit être positif.')]
    private ?int $orderNumber = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank(message: 'Le libellé est obligatoire.')]
    #[Assert\Length(max: 100)]
    private ?string $label = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOrderNumber(): ?int
    {
        return $this->orderNumber;
    }

    public function setOrderNumber(int $orderNumber): static
    {
        $this->orderNumber = $orderNumber;
        return $this;
    }

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function setLabel(string $label): static
    {
        $this->label = $label;
        return $this;
    }

    public function __toString(): string
    {
        return $this->label ?? '';
    }
}

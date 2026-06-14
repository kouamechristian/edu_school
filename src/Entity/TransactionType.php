<?php

namespace App\Entity;

use App\Repository\TransactionTypeRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Type de transaction financière personnalisable (ex: Salaire, Loyer, Don...).
 * Le « sens » (direction) détermine l'impact comptable : entrée, sortie ou transfert.
 */
#[ORM\Entity(repositoryClass: TransactionTypeRepository::class)]
#[ORM\HasLifecycleCallbacks]
class TransactionType
{
    public const DIRECTIONS = [
        'entrée' => 'Entrée',
        'sortie' => 'Sortie',
        'transfert' => 'Transfert',
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100, unique: true)]
    #[Assert\NotBlank(message: 'Le nom du type est obligatoire.')]
    #[Assert\Length(max: 100)]
    private ?string $name = null;

    #[ORM\Column(length: 20)]
    #[Assert\NotBlank(message: 'Le sens est obligatoire.')]
    #[Assert\Choice(choices: ['entrée', 'sortie', 'transfert'], message: 'Sens invalide.')]
    private ?string $direction = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(options: ['default' => true])]
    private bool $isActive = true;

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

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;
        return $this;
    }

    public function getDirection(): ?string
    {
        return $this->direction;
    }

    public function setDirection(string $direction): static
    {
        $this->direction = $direction;
        return $this;
    }

    public function getDirectionLabel(): string
    {
        return self::DIRECTIONS[$this->direction] ?? ($this->direction ?? '');
    }

    public function getDirectionColor(): string
    {
        return match ($this->direction) {
            'entrée' => 'success',
            'sortie' => 'danger',
            'transfert' => 'info',
            default => 'secondary',
        };
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

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function __toString(): string
    {
        return $this->name ?? '';
    }
}

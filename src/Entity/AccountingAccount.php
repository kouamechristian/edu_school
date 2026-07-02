<?php

namespace App\Entity;

use App\Repository\AccountingAccountRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Compte du plan comptable (livre de caisse enrichi).
 *
 * Un compte regroupe des écritures de même nature : une recette (ex. « Frais de
 * scolarité ») ou une dépense (ex. « Salaires »). Il permet de ventiler le
 * journal et de produire la balance et le compte de résultat par poste.
 */
#[ORM\Entity(repositoryClass: AccountingAccountRepository::class)]
#[ORM\Table(name: 'accounting_account')]
#[ORM\UniqueConstraint(name: 'uniq_account_school_code', columns: ['school_id', 'code'])]
#[ORM\HasLifecycleCallbacks]
class AccountingAccount
{
    public const TYPE_RECETTE = 'recette';
    public const TYPE_DEPENSE = 'depense';

    public const TYPES = [
        self::TYPE_RECETTE => 'Recette',
        self::TYPE_DEPENSE => 'Dépense',
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull]
    private ?School $school = null;

    #[ORM\Column(length: 30)]
    #[Assert\NotBlank(message: 'Le code du compte est obligatoire.')]
    #[Assert\Length(max: 30)]
    private ?string $code = null;

    #[ORM\Column(length: 120)]
    #[Assert\NotBlank(message: 'Le libellé du compte est obligatoire.')]
    #[Assert\Length(max: 120)]
    private ?string $name = null;

    #[ORM\Column(length: 20)]
    #[Assert\Choice(choices: [self::TYPE_RECETTE, self::TYPE_DEPENSE], message: 'Type de compte invalide.')]
    private ?string $type = self::TYPE_RECETTE;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    /** Compte du système (créé automatiquement, non supprimable). */
    #[ORM\Column(options: ['default' => false])]
    private bool $isSystem = false;

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

    public function getSchool(): ?School
    {
        return $this->school;
    }

    public function setSchool(?School $school): static
    {
        $this->school = $school;
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

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): static
    {
        $this->name = $name;
        return $this;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(?string $type): static
    {
        $this->type = $type;
        return $this;
    }

    public function getTypeLabel(): string
    {
        return self::TYPES[$this->type] ?? ($this->type ?? '');
    }

    public function isRecette(): bool
    {
        return $this->type === self::TYPE_RECETTE;
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

    public function isSystem(): bool
    {
        return $this->isSystem;
    }

    public function setIsSystem(bool $isSystem): static
    {
        $this->isSystem = $isSystem;
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
        return sprintf('%s — %s', $this->code, $this->name);
    }
}

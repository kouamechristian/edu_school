<?php

namespace App\Entity;

use App\Repository\DocumentTypeRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: DocumentTypeRepository::class)]
#[ORM\Table(name: 'document_type')]
#[ORM\HasLifecycleCallbacks]
class DocumentType
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank(message: 'Le nom du type de document est obligatoire')]
    #[Assert\Length(max: 100)]
    private ?string $name = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column]
    private bool $isRequired = false;

    #[ORM\Column]
    private bool $isActive = true;

    #[ORM\Column(type: Types::JSON)]
    private array $allowedMimeTypes = [];

    #[ORM\Column]
    private int $maxFileSize = 5242880; // 5MB par défaut

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $updatedAt = null;

    #[ORM\OneToMany(mappedBy: 'documentType', targetEntity: PreRegistrationDocument::class)]
    private Collection $preRegistrationDocuments;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
        $this->isRequired = false;
        $this->isActive = true;
        $this->allowedMimeTypes = ['application/pdf', 'image/jpeg', 'image/png'];
        $this->maxFileSize = 5242880; // 5MB
        $this->preRegistrationDocuments = new ArrayCollection();
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

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;
        return $this;
    }

    public function isRequired(): bool
    {
        return $this->isRequired;
    }

    public function setIsRequired(bool $isRequired): static
    {
        $this->isRequired = $isRequired;
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

    public function getAllowedMimeTypes(): array
    {
        return $this->allowedMimeTypes;
    }

    public function setAllowedMimeTypes(array $allowedMimeTypes): static
    {
        $this->allowedMimeTypes = $allowedMimeTypes;
        return $this;
    }

    public function addAllowedMimeType(string $mimeType): static
    {
        if (!in_array($mimeType, $this->allowedMimeTypes)) {
            $this->allowedMimeTypes[] = $mimeType;
        }
        return $this;
    }

    public function removeAllowedMimeType(string $mimeType): static
    {
        $key = array_search($mimeType, $this->allowedMimeTypes);
        if ($key !== false) {
            unset($this->allowedMimeTypes[$key]);
            $this->allowedMimeTypes = array_values($this->allowedMimeTypes);
        }
        return $this;
    }

    public function isMimeTypeAllowed(string $mimeType): bool
    {
        return in_array($mimeType, $this->allowedMimeTypes);
    }

    public function getMaxFileSize(): int
    {
        return $this->maxFileSize;
    }

    public function setMaxFileSize(int $maxFileSize): static
    {
        $this->maxFileSize = $maxFileSize;
        return $this;
    }

    public function getFormattedMaxFileSize(): string
    {
        $bytes = $this->maxFileSize;
        $units = ['B', 'KB', 'MB', 'GB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, 2) . ' ' . $units[$i];
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

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeInterface $updatedAt): static
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    /**
     * @return Collection<int, PreRegistrationDocument>
     */
    public function getPreRegistrationDocuments(): Collection
    {
        return $this->preRegistrationDocuments;
    }

    public function addPreRegistrationDocument(PreRegistrationDocument $preRegistrationDocument): static
    {
        if (!$this->preRegistrationDocuments->contains($preRegistrationDocument)) {
            $this->preRegistrationDocuments->add($preRegistrationDocument);
            $preRegistrationDocument->setDocumentType($this);
        }

        return $this;
    }

    public function removePreRegistrationDocument(PreRegistrationDocument $preRegistrationDocument): static
    {
        if ($this->preRegistrationDocuments->removeElement($preRegistrationDocument)) {
            if ($preRegistrationDocument->getDocumentType() === $this) {
                $preRegistrationDocument->setDocumentType(null);
            }
        }

        return $this;
    }

    public function __toString(): string
    {
        return $this->name ?? 'Type de document';
    }
}

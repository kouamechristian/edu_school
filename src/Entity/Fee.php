<?php

namespace App\Entity;

use App\Repository\FeeRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: FeeRepository::class)]
#[ORM\Table(name: 'fee')]
#[ORM\HasLifecycleCallbacks]
class Fee
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank(message: 'Le nom du frais est obligatoire')]
    #[Assert\Length(max: 100)]
    private ?string $name = null;

    #[ORM\Column(length: 50, unique: true)]
    #[Assert\Length(max: 50)]
    private ?string $code = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotBlank(message: 'L\'établissement est obligatoire')]
    private ?School $school = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true)]
    private ?Level $level = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    #[Assert\NotBlank(message: 'Le montant est obligatoire')]
    #[Assert\Positive(message: 'Le montant doit être positif')]
    private ?string $amount = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length: 20)]
    #[Assert\Choice(choices: ['pour_tous', 'affecte', 'non_affecte'], message: 'Le type doit être Pour tous, Affecté ou Non affecté')]
    private ?string $type = 'pour_tous';

    #[ORM\Column(length: 20)]
    #[Assert\NotBlank(message: 'La catégorie est obligatoire')]
    #[Assert\Choice(choices: ['scolarite', 'article', 'autre_frais'], message: 'La catégorie doit être Scolarité, Article ou Autre frais')]
    private ?string $category = 'scolarite';

    #[ORM\Column(length: 20)]
    #[Assert\Choice(choices: ['unique', 'mensuel', 'trimestriel', 'annuel'], message: 'La fréquence doit être unique, mensuel, trimestriel ou annuel')]
    private ?string $frequency = 'unique';

    #[ORM\Column]
    private bool $isActive = true;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $updatedAt = null;

    #[ORM\OneToMany(mappedBy: 'fee', targetEntity: Payment::class)]
    private Collection $payments;

    #[ORM\OneToMany(mappedBy: 'fee', targetEntity: StudentFee::class, cascade: ['persist', 'remove'])]
    private Collection $studentFees;

    #[ORM\OneToMany(mappedBy: 'fee', targetEntity: FeeSchedule::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['orderNumber' => 'ASC'])]
    private Collection $schedules;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
        $this->payments = new ArrayCollection();
        $this->studentFees = new ArrayCollection();
        $this->schedules = new ArrayCollection();
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

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(string $code): static
    {
        $this->code = $code;
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

    public function getLevel(): ?Level
    {
        return $this->level;
    }

    public function setLevel(?Level $level): static
    {
        $this->level = $level;
        return $this;
    }

    public function getAmount(): ?string
    {
        return $this->amount;
    }

    public function setAmount(string $amount): static
    {
        $this->amount = $amount;
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

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;
        return $this;
    }

    public function getTypeLabel(): string
    {
        return match($this->type) {
            'pour_tous' => 'Pour tous',
            'affecte' => 'Affecté',
            'non_affecte' => 'Non affecté',
            default => $this->type
        };
    }

    public function getCategory(): ?string
    {
        return $this->category;
    }

    public function setCategory(string $category): static
    {
        $this->category = $category;
        return $this;
    }

    public function getCategoryLabel(): string
    {
        return match($this->category) {
            'scolarite' => 'Scolarité',
            'article' => 'Article',
            'autre_frais' => 'Autre frais',
            default => $this->category
        };
    }

    public function getCategoryColor(): string
    {
        return match($this->category) {
            'scolarite' => 'primary',
            'article' => 'info',
            'autre_frais' => 'warning',
            default => 'secondary'
        };
    }

    public function getFrequency(): ?string
    {
        return $this->frequency;
    }

    public function setFrequency(string $frequency): static
    {
        $this->frequency = $frequency;
        return $this;
    }

    public function getFrequencyLabel(): string
    {
        return match($this->frequency) {
            'unique' => 'Unique',
            'mensuel' => 'Mensuel',
            'trimestriel' => 'Trimestriel',
            'annuel' => 'Annuel',
            default => $this->frequency
        };
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
     * @return Collection<int, Payment>
     */
    public function getPayments(): Collection
    {
        return $this->payments;
    }

    public function addPayment(Payment $payment): static
    {
        if (!$this->payments->contains($payment)) {
            $this->payments->add($payment);
            $payment->setFee($this);
        }

        return $this;
    }

    public function removePayment(Payment $payment): static
    {
        if ($this->payments->removeElement($payment)) {
            if ($payment->getFee() === $this) {
                $payment->setFee(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, StudentFee>
     */
    public function getStudentFees(): Collection
    {
        return $this->studentFees;
    }

    public function addStudentFee(StudentFee $studentFee): static
    {
        if (!$this->studentFees->contains($studentFee)) {
            $this->studentFees->add($studentFee);
            $studentFee->setFee($this);
        }

        return $this;
    }

    public function removeStudentFee(StudentFee $studentFee): static
    {
        if ($this->studentFees->removeElement($studentFee)) {
            if ($studentFee->getFee() === $this) {
                $studentFee->setFee(null);
            }
        }

        return $this;
    }

    public function getAssignedStudentsCount(): int
    {
        return $this->studentFees->count();
    }

    /**
     * @return Collection<int, FeeSchedule>
     */
    public function getSchedules(): Collection
    {
        return $this->schedules;
    }

    public function addSchedule(FeeSchedule $schedule): static
    {
        if (!$this->schedules->contains($schedule)) {
            $this->schedules->add($schedule);
            $schedule->setFee($this);
        }

        return $this;
    }

    public function removeSchedule(FeeSchedule $schedule): static
    {
        if ($this->schedules->removeElement($schedule)) {
            if ($schedule->getFee() === $this) {
                $schedule->setFee(null);
            }
        }

        return $this;
    }

    public function getSchedulesTotalAmount(): float
    {
        $total = 0;
        foreach ($this->schedules as $schedule) {
            $total += (float) $schedule->getAmount();
        }
        return $total;
    }

    public function getFinalAmount(): float
    {
        return (float) $this->amount;
    }

    public function __toString(): string
    {
        return $this->name ?? '';
    }
}

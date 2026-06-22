<?php

namespace App\Entity;

use App\Repository\StudentFeeRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: StudentFeeRepository::class)]
#[ORM\Table(name: 'student_fee')]
#[ORM\UniqueConstraint(name: 'unique_student_fee', columns: ['student_id', 'fee_id'])]
#[ORM\HasLifecycleCallbacks]
class StudentFee
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Student::class, inversedBy: 'studentFees')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Student $student = null;

    #[ORM\ManyToOne(targetEntity: Fee::class, inversedBy: 'studentFees')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Fee $fee = null;

    /**
     * Registration (année scolaire) à laquelle ce frais est rattaché.
     *
     * Nullable pendant la phase de migration : les frais existants seront rattachés
     * progressivement à l'inscription correspondante. À terme, ce champ deviendra
     * obligatoire et remplacera le lien direct vers l'élève.
     */
    #[ORM\ManyToOne(targetEntity: Registration::class, inversedBy: 'studentFees')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Registration $registration = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    #[Assert\PositiveOrZero]
    private ?string $amount = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private ?string $paidAmount = '0.00';

    #[ORM\Column(length: 20)]
    #[Assert\Choice(choices: ['non_paye', 'partiellement_paye', 'paye'])]
    private ?string $status = 'non_paye';

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $updatedAt = null;

    /**
     * Paiements rattachés à cette ligne de frais (côté inverse de Payment.studentFee).
     */
    #[ORM\OneToMany(mappedBy: 'studentFee', targetEntity: Payment::class)]
    private Collection $payments;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
        $this->paidAmount = '0.00';
        $this->status = 'non_paye';
        $this->payments = new ArrayCollection();
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

    public function getStudent(): ?Student
    {
        return $this->student;
    }

    public function setStudent(?Student $student): static
    {
        $this->student = $student;
        return $this;
    }

    public function getFee(): ?Fee
    {
        return $this->fee;
    }

    public function setFee(?Fee $fee): static
    {
        $this->fee = $fee;
        return $this;
    }

    public function getRegistration(): ?Registration
    {
        return $this->registration;
    }

    public function setRegistration(?Registration $registration): static
    {
        $this->registration = $registration;
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

    public function getPaidAmount(): ?string
    {
        return $this->paidAmount;
    }

    public function setPaidAmount(string $paidAmount): static
    {
        $this->paidAmount = $paidAmount;
        $this->updateStatus();
        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;
        return $this;
    }

    public function getStatusLabel(): string
    {
        return match($this->status) {
            'non_paye' => 'Non payé',
            'partiellement_paye' => 'Partiellement payé',
            'paye' => 'Payé',
            default => $this->status
        };
    }

    public function getStatusColor(): string
    {
        return match($this->status) {
            'non_paye' => 'danger',
            'partiellement_paye' => 'warning',
            'paye' => 'success',
            default => 'secondary'
        };
    }

    public function getRemainingAmount(): float
    {
        return max(0, (float) $this->amount - (float) $this->paidAmount);
    }

    public function getPaymentPercentage(): float
    {
        if ((float) $this->amount <= 0) {
            return 100;
        }
        return min(100, round(((float) $this->paidAmount / (float) $this->amount) * 100, 2));
    }

    private function updateStatus(): void
    {
        $paid = (float) $this->paidAmount;
        $total = (float) $this->amount;

        if ($paid >= $total) {
            $this->status = 'paye';
        } elseif ($paid > 0) {
            $this->status = 'partiellement_paye';
        } else {
            $this->status = 'non_paye';
        }
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
            $payment->setStudentFee($this);
        }

        return $this;
    }

    public function removePayment(Payment $payment): static
    {
        if ($this->payments->removeElement($payment)) {
            if ($payment->getStudentFee() === $this) {
                $payment->setStudentFee(null);
            }
        }

        return $this;
    }

    /**
     * Montant total des paiements annulés sur cette ligne de frais.
     */
    public function getCancelledAmount(): float
    {
        $total = 0.0;
        foreach ($this->payments as $payment) {
            if ($payment->getStatus() === 'annulé') {
                $total += (float) $payment->getAmount();
            }
        }

        return $total;
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
        return sprintf('%s - %s', $this->student?->getFullName() ?? '', $this->fee?->getName() ?? '');
    }
}

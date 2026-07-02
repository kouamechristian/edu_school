<?php

namespace App\Entity;

use App\Repository\BankReconciliationRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Rapprochement caisse / banque.
 *
 * Fige, à une date de relevé donnée, le solde théorique de la banque (issu des
 * mouvements du journal) et le solde réel du relevé bancaire saisi, pour faire
 * ressortir l'écart. Le volet caisse (théorique vs espèces comptées) est optionnel.
 */
#[ORM\Entity(repositoryClass: BankReconciliationRepository::class)]
#[ORM\Table(name: 'bank_reconciliation')]
#[ORM\Index(columns: ['statement_date'], name: 'idx_reconciliation_date')]
#[ORM\HasLifecycleCallbacks]
class BankReconciliation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull]
    private ?School $school = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $periodFrom = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    #[Assert\NotNull(message: 'La date du relevé est obligatoire.')]
    private ?\DateTimeInterface $statementDate = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 14, scale: 2, options: ['default' => '0.00'])]
    private string $bankTheoretical = '0.00';

    #[ORM\Column(type: Types::DECIMAL, precision: 14, scale: 2, options: ['default' => '0.00'])]
    private string $statementBalance = '0.00';

    #[ORM\Column(type: Types::DECIMAL, precision: 14, scale: 2, options: ['default' => '0.00'])]
    private string $bankDifference = '0.00';

    #[ORM\Column(type: Types::DECIMAL, precision: 14, scale: 2, options: ['default' => '0.00'])]
    private string $cashTheoretical = '0.00';

    #[ORM\Column(type: Types::DECIMAL, precision: 14, scale: 2, nullable: true)]
    private ?string $cashCounted = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 14, scale: 2, nullable: true)]
    private ?string $cashDifference = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $reconciledBy = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $notes = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
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

    public function getPeriodFrom(): ?\DateTimeInterface
    {
        return $this->periodFrom;
    }

    public function setPeriodFrom(?\DateTimeInterface $periodFrom): static
    {
        $this->periodFrom = $periodFrom;
        return $this;
    }

    public function getStatementDate(): ?\DateTimeInterface
    {
        return $this->statementDate;
    }

    public function setStatementDate(?\DateTimeInterface $statementDate): static
    {
        $this->statementDate = $statementDate;
        return $this;
    }

    public function getBankTheoretical(): string
    {
        return $this->bankTheoretical;
    }

    public function setBankTheoretical(string $bankTheoretical): static
    {
        $this->bankTheoretical = $bankTheoretical;
        return $this;
    }

    public function getStatementBalance(): string
    {
        return $this->statementBalance;
    }

    public function setStatementBalance(string $statementBalance): static
    {
        $this->statementBalance = $statementBalance;
        return $this;
    }

    public function getBankDifference(): string
    {
        return $this->bankDifference;
    }

    public function setBankDifference(string $bankDifference): static
    {
        $this->bankDifference = $bankDifference;
        return $this;
    }

    public function isBankBalanced(): bool
    {
        return abs((float) $this->bankDifference) < 0.01;
    }

    public function getCashTheoretical(): string
    {
        return $this->cashTheoretical;
    }

    public function setCashTheoretical(string $cashTheoretical): static
    {
        $this->cashTheoretical = $cashTheoretical;
        return $this;
    }

    public function getCashCounted(): ?string
    {
        return $this->cashCounted;
    }

    public function setCashCounted(?string $cashCounted): static
    {
        $this->cashCounted = $cashCounted;
        return $this;
    }

    public function getCashDifference(): ?string
    {
        return $this->cashDifference;
    }

    public function setCashDifference(?string $cashDifference): static
    {
        $this->cashDifference = $cashDifference;
        return $this;
    }

    public function getReconciledBy(): ?User
    {
        return $this->reconciledBy;
    }

    public function setReconciledBy(?User $reconciledBy): static
    {
        $this->reconciledBy = $reconciledBy;
        return $this;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): static
    {
        $this->notes = $notes;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }
}

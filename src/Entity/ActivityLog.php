<?php

namespace App\Entity;

use App\Repository\ActivityLogRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Journal d'activité (« Mouchard »).
 *
 * Trace horodatée de chaque action sensible menée dans le logiciel : création,
 * modification et suppression d'entités métier, ainsi que les évènements de
 * sécurité (connexion, déconnexion, échec de connexion).
 *
 * Une ligne est volontairement AUTONOME : elle mémorise un instantané du nom
 * d'utilisateur, du libellé de l'entité concernée et une description lisible,
 * afin de rester exploitable même si l'utilisateur ou l'entité d'origine est
 * supprimé plus tard (les relations sont en ON DELETE SET NULL).
 */
#[ORM\Entity(repositoryClass: ActivityLogRepository::class)]
#[ORM\Table(name: 'activity_log')]
#[ORM\Index(columns: ['created_at'], name: 'idx_activity_created_at')]
#[ORM\Index(columns: ['action'], name: 'idx_activity_action')]
#[ORM\Index(columns: ['entity_type'], name: 'idx_activity_entity_type')]
class ActivityLog implements SchoolOwnedInterface
{
    public const ACTION_CREATE = 'create';
    public const ACTION_UPDATE = 'update';
    public const ACTION_DELETE = 'delete';
    public const ACTION_LOGIN = 'login';
    public const ACTION_LOGOUT = 'logout';
    public const ACTION_LOGIN_FAILED = 'login_failed';

    /** Libellés lisibles des actions. */
    public const ACTION_LABELS = [
        self::ACTION_CREATE => 'Création',
        self::ACTION_UPDATE => 'Modification',
        self::ACTION_DELETE => 'Suppression',
        self::ACTION_LOGIN => 'Connexion',
        self::ACTION_LOGOUT => 'Déconnexion',
        self::ACTION_LOGIN_FAILED => 'Échec de connexion',
    ];

    /** Couleur Bootstrap associée à chaque action. */
    public const ACTION_COLORS = [
        self::ACTION_CREATE => 'success',
        self::ACTION_UPDATE => 'primary',
        self::ACTION_DELETE => 'danger',
        self::ACTION_LOGIN => 'info',
        self::ACTION_LOGOUT => 'secondary',
        self::ACTION_LOGIN_FAILED => 'warning',
    ];

    /** Icône FontAwesome associée à chaque action. */
    public const ACTION_ICONS = [
        self::ACTION_CREATE => 'fa-plus',
        self::ACTION_UPDATE => 'fa-pen',
        self::ACTION_DELETE => 'fa-trash',
        self::ACTION_LOGIN => 'fa-right-to-bracket',
        self::ACTION_LOGOUT => 'fa-right-from-bracket',
        self::ACTION_LOGIN_FAILED => 'fa-triangle-exclamation',
    ];

    /**
     * Libellés français des entités métier tracées (nom court de classe → libellé).
     * Une entité absente de cette table affiche simplement son nom court.
     */
    public const ENTITY_LABELS = [
        'Student' => 'Élève',
        'Registration' => 'Inscription',
        'PreRegistration' => 'Préinscription',
        'Payment' => 'Paiement',
        'Depense' => 'Dépense',
        'CashDeposit' => 'Versement',
        'CashRegister' => 'Caisse',
        'Fee' => 'Frais',
        'FeeSchedule' => 'Échéancier de frais',
        'StudentFee' => 'Frais élève',
        'User' => 'Utilisateur',
        'Employee' => 'Employé',
        'Teacher' => 'Enseignant',
        'School' => 'Établissement',
        'SchoolGroup' => 'Groupe d\'établissements',
        'SchoolYear' => 'Année scolaire',
        'Classroom' => 'Classe',
        'Level' => 'Niveau',
        'Cycle' => 'Cycle',
        'Faculty' => 'Filière',
        'Subject' => 'Matière',
        'Course' => 'Cours',
        'Grade' => 'Note',
        'Evaluation' => 'Évaluation',
        'Bulletin' => 'Bulletin',
        'Absence' => 'Absence',
        'Contract' => 'Contrat',
        'Payslip' => 'Bulletin de paie',
        'PayrollPeriod' => 'Période de paie',
        'SalaryComponent' => 'Rubrique de paie',
        'Document' => 'Document',
        'DocumentType' => 'Type de document',
        'StudentDropout' => 'Abandon',
        'StudentTransfer' => 'Transfert',
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /**
     * Auteur de l'action. En ON DELETE SET NULL : si le compte est supprimé, la
     * trace subsiste (le nom d'utilisateur reste conservé dans $username).
     */
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?User $user = null;

    /** Instantané du nom d'utilisateur (survit à la suppression du compte). */
    #[ORM\Column(length: 180)]
    private string $username = 'système';

    #[ORM\ManyToOne(targetEntity: School::class)]
    #[ORM\JoinColumn(name: 'school_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?School $school = null;

    #[ORM\Column(length: 30)]
    private string $action = self::ACTION_UPDATE;

    /** Nom court de la classe de l'entité concernée (ex. « Payment »). */
    #[ORM\Column(length: 100, nullable: true)]
    private ?string $entityType = null;

    #[ORM\Column(nullable: true)]
    private ?int $entityId = null;

    /** Instantané du libellé (__toString) de l'entité concernée. */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $entityLabel = null;

    /** Phrase lisible résumant l'action. */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    /**
     * Détail des champs modifiés pour une mise à jour : ['champ' => [ancien, nouveau]].
     *
     * @var array<string, array{0: mixed, 1: mixed}>|null
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $changes = null;

    #[ORM\Column(length: 45, nullable: true)]
    private ?string $ipAddress = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $route = null;

    #[ORM\Column(length: 10, nullable: true)]
    private ?string $method = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;
        return $this;
    }

    public function getUsername(): string
    {
        return $this->username;
    }

    public function setUsername(string $username): static
    {
        $this->username = $username;
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

    public function getAction(): string
    {
        return $this->action;
    }

    public function setAction(string $action): static
    {
        $this->action = $action;
        return $this;
    }

    public function getActionLabel(): string
    {
        return self::ACTION_LABELS[$this->action] ?? $this->action;
    }

    public function getActionColor(): string
    {
        return self::ACTION_COLORS[$this->action] ?? 'secondary';
    }

    public function getActionIcon(): string
    {
        return self::ACTION_ICONS[$this->action] ?? 'fa-circle-info';
    }

    public function getEntityType(): ?string
    {
        return $this->entityType;
    }

    public function setEntityType(?string $entityType): static
    {
        $this->entityType = $entityType;
        return $this;
    }

    public function getEntityTypeLabel(): ?string
    {
        if ($this->entityType === null) {
            return null;
        }

        return self::ENTITY_LABELS[$this->entityType] ?? $this->entityType;
    }

    public function getEntityId(): ?int
    {
        return $this->entityId;
    }

    public function setEntityId(?int $entityId): static
    {
        $this->entityId = $entityId;
        return $this;
    }

    public function getEntityLabel(): ?string
    {
        return $this->entityLabel;
    }

    public function setEntityLabel(?string $entityLabel): static
    {
        // On borne la longueur pour respecter la colonne (255).
        if ($entityLabel !== null && mb_strlen($entityLabel) > 255) {
            $entityLabel = mb_substr($entityLabel, 0, 252) . '…';
        }
        $this->entityLabel = $entityLabel;
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

    public function getChanges(): ?array
    {
        return $this->changes;
    }

    public function setChanges(?array $changes): static
    {
        $this->changes = $changes;
        return $this;
    }

    public function getIpAddress(): ?string
    {
        return $this->ipAddress;
    }

    public function setIpAddress(?string $ipAddress): static
    {
        $this->ipAddress = $ipAddress;
        return $this;
    }

    public function getRoute(): ?string
    {
        return $this->route;
    }

    public function setRoute(?string $route): static
    {
        $this->route = $route;
        return $this;
    }

    public function getMethod(): ?string
    {
        return $this->method;
    }

    public function setMethod(?string $method): static
    {
        $this->method = $method;
        return $this;
    }

    public function getCreatedAt(): \DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): static
    {
        $this->createdAt = $createdAt;
        return $this;
    }
}

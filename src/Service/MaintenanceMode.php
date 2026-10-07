<?php

namespace App\Service;

use App\Entity\User;
use App\Security\RoleGrantPolicy;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Mode maintenance de l'application.
 *
 * L'état est stocké dans un fichier JSON (var/maintenance.json) plutôt qu'en base :
 * il reste lisible même si la base est indisponible ou en cours de migration — ce
 * qui est justement le cas typique d'une maintenance.
 *
 * Pendant la maintenance, seul le super-administrateur « réel » peut se connecter
 * et utiliser l'application ; tous les autres comptes voient la page de maintenance.
 */
class MaintenanceMode
{
    /** @var array{enabled: bool, message: ?string, until: ?string, enabledAt: ?string, enabledBy: ?string}|null */
    private ?array $state = null;

    public function __construct(
        #[Autowire('%kernel.project_dir%/var/maintenance.json')]
        private readonly string $stateFile,
    ) {
    }

    public function isEnabled(): bool
    {
        return $this->getState()['enabled'];
    }

    /**
     * Seul le super-administrateur réel (rôle stocké) contourne la maintenance.
     */
    public function canBypass(?User $user): bool
    {
        return $user !== null && RoleGrantPolicy::isRealSuperAdmin($user);
    }

    /** Message affiché aux utilisateurs (ou null pour le message par défaut). */
    public function getMessage(): ?string
    {
        return $this->getState()['message'];
    }

    /** Date de fin prévue (indicative), ou null si non précisée. */
    public function getUntil(): ?\DateTimeImmutable
    {
        return $this->toDate($this->getState()['until']);
    }

    public function getEnabledAt(): ?\DateTimeImmutable
    {
        return $this->toDate($this->getState()['enabledAt']);
    }

    public function getEnabledBy(): ?string
    {
        return $this->getState()['enabledBy'];
    }

    public function enable(?string $message, ?\DateTimeInterface $until, User $by): void
    {
        $message = $message !== null ? trim($message) : null;

        $this->write([
            'enabled' => true,
            'message' => $message !== '' ? $message : null,
            'until' => $until?->format(\DateTimeInterface::ATOM),
            'enabledAt' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
            'enabledBy' => $by->getFullName() ?: $by->getUserIdentifier(),
        ]);
    }

    public function disable(): void
    {
        $this->write(self::defaultState());
    }

    /**
     * @return array{enabled: bool, message: ?string, until: ?string, enabledAt: ?string, enabledBy: ?string}
     */
    private function getState(): array
    {
        if ($this->state !== null) {
            return $this->state;
        }

        $state = self::defaultState();
        if (is_file($this->stateFile)) {
            $data = json_decode((string) @file_get_contents($this->stateFile), true);
            if (is_array($data)) {
                $state = [
                    'enabled' => (bool) ($data['enabled'] ?? false),
                    'message' => isset($data['message']) ? (string) $data['message'] : null,
                    'until' => isset($data['until']) ? (string) $data['until'] : null,
                    'enabledAt' => isset($data['enabledAt']) ? (string) $data['enabledAt'] : null,
                    'enabledBy' => isset($data['enabledBy']) ? (string) $data['enabledBy'] : null,
                ];
            }
        }

        return $this->state = $state;
    }

    private function write(array $state): void
    {
        $dir = \dirname($this->stateFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        if (file_put_contents($this->stateFile, json_encode($state, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE), \LOCK_EX) === false) {
            throw new \RuntimeException(sprintf('Impossible d\'écrire l\'état de maintenance dans « %s ».', $this->stateFile));
        }

        $this->state = $state;
    }

    private function toDate(?string $value): ?\DateTimeImmutable
    {
        if ($value === null) {
            return null;
        }

        try {
            return new \DateTimeImmutable($value);
        } catch (\Exception) {
            return null;
        }
    }

    private static function defaultState(): array
    {
        return ['enabled' => false, 'message' => null, 'until' => null, 'enabledAt' => null, 'enabledBy' => null];
    }
}

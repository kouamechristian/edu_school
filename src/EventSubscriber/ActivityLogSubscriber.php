<?php

namespace App\EventSubscriber;

use App\Entity\AccountingEntry;
use App\Entity\ActivityLog;
use App\Entity\Notification;
use App\Entity\School;
use App\Service\ActivityLogger;
use Doctrine\Bundle\DoctrineBundle\EventSubscriber\EventSubscriberInterface;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Events;

/**
 * Alimente automatiquement le journal d'activité (« Mouchard ») à chaque
 * création, modification ou suppression d'une entité métier.
 *
 * Fonctionnement (calqué sur {@see AccountingSubscriber}) :
 *  - en `onFlush`, on parcourt les insertions / mises à jour / suppressions
 *    planifiées et on met en file un instantané (libellé, changements, établissement),
 *    car le changeset et le libellé ne sont fiables qu'à ce moment ;
 *  - en `postFlush`, les identifiants sont désormais stables : on construit les
 *    lignes {@see ActivityLog}, on les persiste puis on reflush une seule fois.
 *
 * Un drapeau de ré-entrance ($processing) empêche la journalisation de se
 * journaliser elle-même.
 */
class ActivityLogSubscriber implements EventSubscriberInterface
{
    /**
     * Entités techniques / auto-générées exclues du journal (évite le bruit et la récursion).
     */
    private const IGNORED_ENTITIES = [
        ActivityLog::class,
        AccountingEntry::class,
        Notification::class,
    ];

    /**
     * Champs ignorés dans le détail des modifications (bruit ou données sensibles).
     */
    private const IGNORED_FIELDS = ['password', 'plainPassword', 'updatedAt', 'lastLogin'];

    /** @var array<int, array{entity: object, action: string, label: ?string, changes: ?array, school: ?School, type: string}> */
    private array $queue = [];

    private bool $processing = false;

    public function __construct(private ActivityLogger $activityLogger)
    {
    }

    public function getSubscribedEvents(): array
    {
        return [
            Events::onFlush,
            Events::postFlush,
        ];
    }

    public function onFlush(OnFlushEventArgs $args): void
    {
        if ($this->processing) {
            return;
        }

        $em = $args->getObjectManager();
        $uow = $em->getUnitOfWork();

        foreach ($uow->getScheduledEntityInsertions() as $entity) {
            $this->enqueue($em, $entity, ActivityLog::ACTION_CREATE);
        }

        foreach ($uow->getScheduledEntityUpdates() as $entity) {
            $changes = $this->extractChanges($uow->getEntityChangeSet($entity));
            // Une mise à jour sans champ « significatif » (ex. seul updatedAt a bougé)
            // n'est pas journalisée.
            if ($changes === []) {
                continue;
            }
            $this->enqueue($em, $entity, ActivityLog::ACTION_UPDATE, $changes);
        }

        foreach ($uow->getScheduledEntityDeletions() as $entity) {
            $this->enqueue($em, $entity, ActivityLog::ACTION_DELETE);
        }
    }

    public function postFlush(PostFlushEventArgs $args): void
    {
        if ($this->processing || $this->queue === []) {
            return;
        }

        $items = $this->queue;
        $this->queue = [];
        $this->processing = true;

        $em = $args->getObjectManager();

        try {
            foreach ($items as $item) {
                $em->persist($this->buildLog($item));
            }
            $em->flush();
        } finally {
            $this->processing = false;
        }
    }

    private function enqueue(object $em, object $entity, string $action, ?array $changes = null): void
    {
        if (!$this->isLoggable($em, $entity)) {
            return;
        }

        $this->queue[spl_object_id($entity)] = [
            'entity' => $entity,
            'action' => $action,
            'type' => $this->resolveShortName($em, $entity),
            'label' => $this->resolveLabel($entity),
            'changes' => $changes,
            'school' => $this->resolveSchool($entity),
        ];
    }

    /**
     * @param array{entity: object, action: string, label: ?string, changes: ?array, school: ?School, type: string} $item
     */
    private function buildLog(array $item): ActivityLog
    {
        $entity = $item['entity'];
        $entityId = method_exists($entity, 'getId') ? $entity->getId() : null;

        $log = $this->activityLogger->createContextualLog($item['action'])
            ->setEntityType($item['type'])
            ->setEntityId(is_int($entityId) ? $entityId : null)
            ->setEntityLabel($item['label'])
            ->setChanges($item['changes'])
            ->setSchool($item['school']);

        $log->setDescription($this->buildDescription($log));

        return $log;
    }

    private function buildDescription(ActivityLog $log): string
    {
        $entity = $log->getEntityTypeLabel() ?? 'élément';
        $description = sprintf('%s — %s', $log->getActionLabel(), $entity);

        if ($log->getEntityId() !== null) {
            $description .= ' #' . $log->getEntityId();
        }
        if ($log->getEntityLabel()) {
            $description .= ' « ' . $log->getEntityLabel() . ' »';
        }

        return $description;
    }

    private function isLoggable(object $em, object $entity): bool
    {
        $class = $this->resolveClass($em, $entity);

        // On ne trace que les entités applicatives, hors liste d'exclusion.
        if (!str_starts_with($class, 'App\\Entity\\')) {
            return false;
        }

        foreach (self::IGNORED_ENTITIES as $ignored) {
            if ($class === $ignored) {
                return false;
            }
        }

        return true;
    }

    /**
     * Nom de classe réel (résout les proxies Doctrine).
     */
    private function resolveClass(object $em, object $entity): string
    {
        return $em->getClassMetadata($entity::class)->getName();
    }

    private function resolveShortName(object $em, object $entity): string
    {
        $class = $this->resolveClass($em, $entity);
        $pos = strrpos($class, '\\');

        return $pos === false ? $class : substr($class, $pos + 1);
    }

    private function resolveLabel(object $entity): ?string
    {
        if (!method_exists($entity, '__toString')) {
            return null;
        }

        try {
            $label = (string) $entity;
        } catch (\Throwable) {
            return null;
        }

        $label = trim($label);

        return $label === '' ? null : $label;
    }

    private function resolveSchool(object $entity): ?School
    {
        if (!method_exists($entity, 'getSchool')) {
            return null;
        }

        try {
            $school = $entity->getSchool();
        } catch (\Throwable) {
            return null;
        }

        return $school instanceof School ? $school : null;
    }

    /**
     * Réduit un changeset Doctrine à un tableau JSON-compatible ['champ' => [ancien, nouveau]].
     *
     * @param array<string, array{0: mixed, 1: mixed}> $changeSet
     */
    private function extractChanges(array $changeSet): array
    {
        $changes = [];

        foreach ($changeSet as $field => [$old, $new]) {
            if (in_array($field, self::IGNORED_FIELDS, true)) {
                continue;
            }

            $oldValue = $this->normalizeValue($old);
            $newValue = $this->normalizeValue($new);

            // Rien de réellement modifié après normalisation → on ignore.
            if ($oldValue === $newValue) {
                continue;
            }

            $changes[$field] = [$oldValue, $newValue];
        }

        return $changes;
    }

    /**
     * Rend une valeur de champ affichable et sérialisable en JSON.
     */
    private function normalizeValue(mixed $value): mixed
    {
        if ($value === null || is_bool($value) || is_int($value) || is_float($value)) {
            return $value;
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i');
        }

        if ($value instanceof \BackedEnum) {
            return $value->value;
        }

        if (is_object($value)) {
            if (method_exists($value, '__toString')) {
                try {
                    return (string) $value;
                } catch (\Throwable) {
                    // ignore et retombe sur la représentation générique
                }
            }
            $id = method_exists($value, 'getId') ? $value->getId() : null;

            return $id !== null ? '#' . $id : '[objet]';
        }

        if (is_array($value)) {
            return array_map(fn ($v) => $this->normalizeValue($v), $value);
        }

        $value = (string) $value;

        // On borne les longues valeurs texte pour garder le JSON compact.
        return mb_strlen($value) > 500 ? mb_substr($value, 0, 497) . '…' : $value;
    }
}

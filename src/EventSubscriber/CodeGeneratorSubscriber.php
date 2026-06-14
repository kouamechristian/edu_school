<?php

namespace App\EventSubscriber;

use App\Service\CodeGenerator;
use Doctrine\Bundle\DoctrineBundle\EventSubscriber\EventSubscriberInterface;
use Doctrine\ORM\Events;
use Doctrine\Persistence\Event\LifecycleEventArgs;

/**
 * Génère automatiquement le « code » des entités qui en possèdent un, lors de
 * l'enregistrement (prePersist), lorsque celui-ci n'a pas été renseigné.
 *
 * Si un code est déjà fourni (saisie manuelle), il est conservé tel quel.
 */
class CodeGeneratorSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private CodeGenerator $codeGenerator
    ) {
    }

    public function getSubscribedEvents(): array
    {
        return [
            Events::prePersist,
        ];
    }

    public function prePersist(LifecycleEventArgs $args): void
    {
        $entity = $args->getObject();
        $prefix = $this->codeGenerator->getPrefix($entity::class);

        if ($prefix === null) {
            return;
        }

        if (!method_exists($entity, 'getCode') || !method_exists($entity, 'setCode')) {
            return;
        }

        // Code déjà renseigné : on ne l'écrase pas.
        if (!empty($entity->getCode())) {
            return;
        }

        $entity->setCode(
            $this->codeGenerator->generate($args->getObjectManager(), $entity::class, $prefix)
        );
    }
}

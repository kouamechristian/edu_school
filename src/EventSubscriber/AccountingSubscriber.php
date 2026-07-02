<?php

namespace App\EventSubscriber;

use App\Entity\CashDeposit;
use App\Entity\Depense;
use App\Entity\Payment;
use App\Service\AccountingService;
use Doctrine\Bundle\DoctrineBundle\EventSubscriber\EventSubscriberInterface;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Events;
use Doctrine\Persistence\Event\LifecycleEventArgs;

/**
 * Alimente automatiquement le journal comptable à chaque création/modification
 * d'un paiement, d'une dépense ou d'un versement.
 *
 * Les objets concernés sont collectés en postPersist / postUpdate, puis traités
 * en postFlush (les écritures ont alors un identifiant source stable). Un drapeau
 * de ré-entrance évite toute récursion lors du flush des écritures générées.
 */
class AccountingSubscriber implements EventSubscriberInterface
{
    /** @var array<int, object> */
    private array $queue = [];

    private bool $processing = false;

    public function __construct(private AccountingService $accountingService)
    {
    }

    public function getSubscribedEvents(): array
    {
        return [
            Events::postPersist,
            Events::postUpdate,
            Events::postFlush,
        ];
    }

    public function postPersist(LifecycleEventArgs $args): void
    {
        $this->enqueue($args->getObject());
    }

    public function postUpdate(LifecycleEventArgs $args): void
    {
        $this->enqueue($args->getObject());
    }

    public function postFlush(PostFlushEventArgs $args): void
    {
        if ($this->processing || $this->queue === []) {
            return;
        }

        $items = $this->queue;
        $this->queue = [];
        $this->processing = true;

        try {
            foreach ($items as $entity) {
                if ($entity instanceof Payment) {
                    $this->accountingService->syncPayment($entity, $entity->getRecordedBy());
                } elseif ($entity instanceof Depense) {
                    $this->accountingService->syncDepense($entity, $entity->getRecordedBy());
                } elseif ($entity instanceof CashDeposit) {
                    $this->accountingService->syncDeposit($entity, $entity->getRecordedBy());
                }
            }

            $args->getObjectManager()->flush();
        } finally {
            $this->processing = false;
        }
    }

    private function enqueue(object $entity): void
    {
        if ($this->processing) {
            return;
        }

        if ($entity instanceof Payment || $entity instanceof Depense || $entity instanceof CashDeposit) {
            $this->queue[spl_object_id($entity)] = $entity;
        }
    }
}

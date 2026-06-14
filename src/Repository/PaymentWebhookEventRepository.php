<?php

namespace App\Repository;

use App\Entity\PaymentWebhookEvent;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PaymentWebhookEvent>
 */
class PaymentWebhookEventRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PaymentWebhookEvent::class);
    }

    public function findOneByProviderEvent(string $provider, string $eventId): ?PaymentWebhookEvent
    {
        return $this->findOneBy(['provider' => $provider, 'eventId' => $eventId]);
    }
}

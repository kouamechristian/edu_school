<?php

namespace App\Repository;

use App\Entity\MobileMoneyConfig;
use App\Entity\School;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<MobileMoneyConfig>
 */
class MobileMoneyConfigRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MobileMoneyConfig::class);
    }

    public function findOneBySchool(School $school): ?MobileMoneyConfig
    {
        return $this->findOneBy(['school' => $school]);
    }

    /**
     * @return MobileMoneyConfig[]
     */
    public function findActive(): array
    {
        return $this->findBy(['isActive' => true]);
    }
}

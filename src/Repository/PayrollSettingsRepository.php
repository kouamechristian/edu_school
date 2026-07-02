<?php

namespace App\Repository;

use App\Entity\PayrollSettings;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PayrollSettings>
 */
class PayrollSettingsRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PayrollSettings::class);
    }

    public function findForSchool(int $schoolId): ?PayrollSettings
    {
        return $this->findOneBy(['school' => $schoolId]);
    }
}

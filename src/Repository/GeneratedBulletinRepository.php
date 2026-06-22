<?php

namespace App\Repository;

use App\Entity\GeneratedBulletin;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<GeneratedBulletin>
 */
class GeneratedBulletinRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, GeneratedBulletin::class);
    }

    /**
     * Bulletins générés d'un établissement / année, les plus récents d'abord.
     *
     * @return GeneratedBulletin[]
     */
    public function findBySchoolAndYear(int $schoolId, ?int $yearId): array
    {
        $qb = $this->createQueryBuilder('b')
            ->innerJoin('b.classroom', 'c')
            ->addSelect('c')
            ->andWhere('c.school = :school')
            ->setParameter('school', $schoolId)
            ->orderBy('b.generatedAt', 'DESC');

        if ($yearId) {
            $qb->andWhere('c.schoolYear = :year')->setParameter('year', $yearId);
        }

        return $qb->getQuery()->getResult();
    }

    public function findOneByClassroomAndPeriod(int $classroomId, int $periodId): ?GeneratedBulletin
    {
        return $this->findOneBy(['classroom' => $classroomId, 'period' => $periodId]);
    }
}

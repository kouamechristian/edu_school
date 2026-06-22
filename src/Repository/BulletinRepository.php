<?php

namespace App\Repository;

use App\Entity\Bulletin;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Bulletin>
 */
class BulletinRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Bulletin::class);
    }

    /**
     * Bulletins d'un établissement, les plus récents d'abord.
     *
     * @return Bulletin[]
     */
    public function findBySchool(int $schoolId): array
    {
        return $this->findBySchoolAndYear($schoolId, null);
    }

    /**
     * Bulletins d'un établissement (et d'une année scolaire si fournie), récents d'abord.
     *
     * @return Bulletin[]
     */
    public function findBySchoolAndYear(int $schoolId, ?int $yearId): array
    {
        $qb = $this->createQueryBuilder('b')
            ->leftJoin('b.level', 'l')->addSelect('l')
            ->leftJoin('b.period', 'p')->addSelect('p')
            ->andWhere('b.school = :school')
            ->setParameter('school', $schoolId)
            ->orderBy('b.createdAt', 'DESC');

        if ($yearId) {
            $qb->andWhere('b.schoolYear = :year')->setParameter('year', $yearId);
        }

        return $qb->getQuery()->getResult();
    }
}

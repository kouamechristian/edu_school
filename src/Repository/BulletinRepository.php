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

    /**
     * Retourne le bulletin existant pour un (établissement, année, niveau, période)
     * donné, s'il y en a un — quel que soit son statut (brouillon ou validé). Sert
     * à garantir l'unicité : un seul bulletin par niveau et par période.
     */
    public function findOneFor(int $schoolId, int $yearId, int $levelId, int $periodId): ?Bulletin
    {
        return $this->createQueryBuilder('b')
            ->andWhere('b.school = :school')
            ->andWhere('b.schoolYear = :year')
            ->andWhere('b.level = :level')
            ->andWhere('b.period = :period')
            ->setParameter('school', $schoolId)
            ->setParameter('year', $yearId)
            ->setParameter('level', $levelId)
            ->setParameter('period', $periodId)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}

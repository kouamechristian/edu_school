<?php

namespace App\Repository;

use App\Entity\AbsenceType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AbsenceType>
 *
 * @method AbsenceType|null find($id, $lockMode = null, $lockVersion = null)
 * @method AbsenceType|null findOneBy(array $criteria, array $orderBy = null)
 * @method AbsenceType[]    findAll()
 * @method AbsenceType[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class AbsenceTypeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AbsenceType::class);
    }

    public function save(AbsenceType $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(AbsenceType $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Trouve les types d'absence actifs par établissement
     */
    public function findActiveBySchool(int $schoolId): array
    {
        return $this->createQueryBuilder('at')
            ->andWhere('at.school = :schoolId')
            ->andWhere('at.isActive = :active')
            ->setParameter('schoolId', $schoolId)
            ->setParameter('active', true)
            ->orderBy('at.type', 'ASC')
            ->addOrderBy('at.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les types d'absence par type (absence, retard, sortie_anticipee)
     */
    public function findByTypeAndSchool(string $type, int $schoolId): array
    {
        return $this->createQueryBuilder('at')
            ->andWhere('at.type = :type')
            ->andWhere('at.school = :schoolId')
            ->andWhere('at.isActive = :active')
            ->setParameter('type', $type)
            ->setParameter('schoolId', $schoolId)
            ->setParameter('active', true)
            ->orderBy('at.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve un type d'absence par code et établissement
     */
    public function findByCodeAndSchool(string $code, int $schoolId): ?AbsenceType
    {
        return $this->createQueryBuilder('at')
            ->andWhere('at.code = :code')
            ->andWhere('at.school = :schoolId')
            ->andWhere('at.isActive = :active')
            ->setParameter('code', $code)
            ->setParameter('schoolId', $schoolId)
            ->setParameter('active', true)
            ->getQuery()
            ->getOneOrNullResult();
    }
}

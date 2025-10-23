<?php

namespace App\Repository;

use App\Entity\Employee;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Employee>
 *
 * @method Employee|null find($id, $lockMode = null, $lockVersion = null)
 * @method Employee|null findOneBy(array $criteria, array $orderBy = null)
 * @method Employee[]    findAll()
 * @method Employee[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class EmployeeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Employee::class);
    }

    public function save(Employee $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Employee $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Trouve les employés actifs par établissement
     */
    public function findActiveBySchool(int $schoolId): array
    {
        return $this->createQueryBuilder('e')
            ->join('e.schools', 's')
            ->andWhere('e.isActive = :active')
            ->andWhere('s.id = :schoolId')
            ->setParameter('active', true)
            ->setParameter('schoolId', $schoolId)
            ->orderBy('e.lastName', 'ASC')
            ->addOrderBy('e.firstName', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les employés par type et établissement
     */
    public function findByTypeAndSchool(string $employeeType, int $schoolId): array
    {
        return $this->createQueryBuilder('e')
            ->join('e.schools', 's')
            ->andWhere('e.employeeType = :type')
            ->andWhere('e.isActive = :active')
            ->andWhere('s.id = :schoolId')
            ->setParameter('type', $employeeType)
            ->setParameter('active', true)
            ->setParameter('schoolId', $schoolId)
            ->orderBy('e.lastName', 'ASC')
            ->addOrderBy('e.firstName', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve tous les enseignants actifs
     */
    public function findActiveTeachers(): array
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.employeeType = :type')
            ->andWhere('e.isActive = :active')
            ->setParameter('type', 'enseignant')
            ->setParameter('active', true)
            ->orderBy('e.lastName', 'ASC')
            ->addOrderBy('e.firstName', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Compte les employés par type dans un établissement
     */
    public function countByTypeInSchool(int $schoolId): array
    {
        $result = $this->createQueryBuilder('e')
            ->select('e.employeeType as type, COUNT(e.id) as count')
            ->join('e.schools', 's')
            ->andWhere('e.isActive = :active')
            ->andWhere('s.id = :schoolId')
            ->setParameter('active', true)
            ->setParameter('schoolId', $schoolId)
            ->groupBy('e.employeeType')
            ->getQuery()
            ->getResult();

        $counts = [];
        foreach ($result as $row) {
            $counts[$row['type']] = (int) $row['count'];
        }

        return $counts;
    }
}

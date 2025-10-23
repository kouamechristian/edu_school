<?php

namespace App\Repository;

use App\Entity\Teacher;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Teacher>
 *
 * @method Teacher|null find($id, $lockMode = null, $lockVersion = null)
 * @method Teacher|null findOneBy(array $criteria, array $orderBy = null)
 * @method Teacher[]    findAll()
 * @method Teacher[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class TeacherRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Teacher::class);
    }

    public function save(Teacher $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Teacher $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Trouve les enseignants par établissement
     */
    public function findBySchool(int $schoolId): array
    {
        return $this->createQueryBuilder('t')
            ->join('t.employee', 'e')
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
     * Trouve les enseignants par matière
     */
    public function findBySubject(int $subjectId): array
    {
        return $this->createQueryBuilder('t')
            ->join('t.subjects', 's')
            ->join('t.employee', 'e')
            ->andWhere('s.id = :subjectId')
            ->andWhere('e.isActive = :active')
            ->setParameter('subjectId', $subjectId)
            ->setParameter('active', true)
            ->orderBy('e.lastName', 'ASC')
            ->addOrderBy('e.firstName', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les enseignants par niveau
     */
    public function findByLevel(int $levelId): array
    {
        return $this->createQueryBuilder('t')
            ->join('t.levels', 'l')
            ->join('t.employee', 'e')
            ->andWhere('l.id = :levelId')
            ->andWhere('e.isActive = :active')
            ->setParameter('levelId', $levelId)
            ->setParameter('active', true)
            ->orderBy('e.lastName', 'ASC')
            ->addOrderBy('e.firstName', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les professeurs principaux (class teachers)
     */
    public function findClassTeachers(): array
    {
        return $this->createQueryBuilder('t')
            ->join('t.employee', 'e')
            ->andWhere('t.isClassTeacher = :isClassTeacher')
            ->andWhere('e.isActive = :active')
            ->setParameter('isClassTeacher', true)
            ->setParameter('active', true)
            ->orderBy('e.lastName', 'ASC')
            ->addOrderBy('e.firstName', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve un enseignant par son employé
     */
    public function findByEmployee(int $employeeId): ?Teacher
    {
        return $this->createQueryBuilder('t')
            ->join('t.employee', 'e')
            ->andWhere('e.id = :employeeId')
            ->setParameter('employeeId', $employeeId)
            ->getQuery()
            ->getOneOrNullResult();
    }
}

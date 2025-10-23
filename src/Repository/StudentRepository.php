<?php

namespace App\Repository;

use App\Entity\Student;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Student>
 *
 * @method Student|null find($id, $lockMode = null, $lockVersion = null)
 * @method Student|null findOneBy(array $criteria, array $orderBy = null)
 * @method Student[]    findAll()
 * @method Student[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class StudentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Student::class);
    }

    public function save(Student $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Student $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Trouve les élèves par établissement
     */
    public function findBySchool(int $schoolId): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.school = :schoolId')
            ->andWhere('s.isActive = :active')
            ->setParameter('schoolId', $schoolId)
            ->setParameter('active', true)
            ->orderBy('s.lastName', 'ASC')
            ->addOrderBy('s.firstName', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les élèves par classe
     */
    public function findByClassroom(int $classroomId): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.classroom = :classroomId')
            ->andWhere('s.isActive = :active')
            ->setParameter('classroomId', $classroomId)
            ->setParameter('active', true)
            ->orderBy('s.lastName', 'ASC')
            ->addOrderBy('s.firstName', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les élèves par niveau
     */
    public function findByLevel(int $levelId): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.level = :levelId')
            ->andWhere('s.isActive = :active')
            ->setParameter('levelId', $levelId)
            ->setParameter('active', true)
            ->orderBy('s.lastName', 'ASC')
            ->addOrderBy('s.firstName', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les élèves par année scolaire
     */
    public function findBySchoolYear(int $schoolYearId): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.schoolYear = :schoolYearId')
            ->andWhere('s.isActive = :active')
            ->setParameter('schoolYearId', $schoolYearId)
            ->setParameter('active', true)
            ->orderBy('s.lastName', 'ASC')
            ->addOrderBy('s.firstName', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve un élève par son numéro
     */
    public function findByStudentNumber(string $studentNumber): ?Student
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.studentNumber = :studentNumber')
            ->andWhere('s.isActive = :active')
            ->setParameter('studentNumber', $studentNumber)
            ->setParameter('active', true)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Trouve les élèves par nom ou prénom
     */
    public function findByName(string $name): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.firstName LIKE :name OR s.lastName LIKE :name')
            ->andWhere('s.isActive = :active')
            ->setParameter('name', '%' . $name . '%')
            ->setParameter('active', true)
            ->orderBy('s.lastName', 'ASC')
            ->addOrderBy('s.firstName', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Compte les élèves par établissement
     */
    public function countBySchool(int $schoolId): int
    {
        return $this->createQueryBuilder('s')
            ->select('COUNT(s.id)')
            ->andWhere('s.school = :schoolId')
            ->andWhere('s.isActive = :active')
            ->setParameter('schoolId', $schoolId)
            ->setParameter('active', true)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Compte les élèves par classe
     */
    public function countByClassroom(int $classroomId): int
    {
        return $this->createQueryBuilder('s')
            ->select('COUNT(s.id)')
            ->andWhere('s.classroom = :classroomId')
            ->andWhere('s.isActive = :active')
            ->setParameter('classroomId', $classroomId)
            ->setParameter('active', true)
            ->getQuery()
            ->getSingleScalarResult();
    }
}

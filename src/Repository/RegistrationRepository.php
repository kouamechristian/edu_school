<?php

namespace App\Repository;

use App\Entity\Registration;
use App\Entity\Student;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Registration>
 */
class RegistrationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Registration::class);
    }

    /**
     * Inscription d'un élève pour une année scolaire donnée (le cas échéant).
     */
    public function findOneByStudentAndYear(int $studentId, ?int $yearId): ?Registration
    {
        if (!$yearId) {
            return null;
        }

        // L'élève n'est plus porté directement par l'inscription : on le retrouve via la
        // préinscription d'origine. Le lien « nouvel élève » est porté côté Student
        // (st.preRegistration), le lien « ancien élève » côté préinscription (existingStudent).
        return $this->createQueryBuilder('i')
            ->innerJoin('i.preRegistration', 'pr')
            ->leftJoin(Student::class, 'st', 'WITH', 'st.preRegistration = pr')
            ->andWhere('pr.existingStudent = :student OR st.id = :student')
            ->andWhere('i.schoolYear = :year')
            ->setParameter('student', $studentId)
            ->setParameter('year', $yearId)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Inscriptions d'un établissement pour une année (avec filtres optionnels).
     *
     * @return Registration[]
     */
    public function findBySchoolAndYear(?int $schoolId, ?int $yearId, ?int $classroomId = null): array
    {
        if (!$schoolId || !$yearId) {
            return [];
        }

        $qb = $this->createQueryBuilder('i')
            ->innerJoin('i.preRegistration', 'pr')
            ->andWhere('i.school = :school')
            ->andWhere('i.schoolYear = :year')
            ->andWhere('i.isActive = :active')
            ->setParameter('school', $schoolId)
            ->setParameter('year', $yearId)
            ->setParameter('active', true)
            ->orderBy('pr.lastName', 'ASC')
            ->addOrderBy('pr.firstName', 'ASC');

        if ($classroomId) {
            $qb->andWhere('i.classroom = :classroom')->setParameter('classroom', $classroomId);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Nombre d'inscriptions (élèves inscrits) d'un établissement pour une année.
     */
    public function countBySchoolAndYear(?int $schoolId, ?int $yearId): int
    {
        if (!$schoolId || !$yearId) {
            return 0;
        }

        return (int) $this->createQueryBuilder('i')
            ->select('COUNT(i.id)')
            ->andWhere('i.school = :school')
            ->andWhere('i.schoolYear = :year')
            ->andWhere('i.isActive = :active')
            ->setParameter('school', $schoolId)
            ->setParameter('year', $yearId)
            ->setParameter('active', true)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Nombre d'inscriptions actives par classe pour une école/année.
     *
     * @return array<int, int> classroom id => nombre d'inscrits
     */
    public function countActiveByClassroom(?int $schoolId, ?int $yearId): array
    {
        if (!$schoolId || !$yearId) {
            return [];
        }

        $rows = $this->createQueryBuilder('i')
            ->select('IDENTITY(i.classroom) AS cid, COUNT(i.id) AS cnt')
            ->andWhere('i.school = :school')
            ->andWhere('i.schoolYear = :year')
            ->andWhere('i.isActive = :active')
            ->andWhere('i.classroom IS NOT NULL')
            ->setParameter('school', $schoolId)
            ->setParameter('year', $yearId)
            ->setParameter('active', true)
            ->groupBy('i.classroom')
            ->getQuery()
            ->getScalarResult();

        $counts = [];
        foreach ($rows as $row) {
            $counts[(int) $row['cid']] = (int) $row['cnt'];
        }

        return $counts;
    }

    /**
     * Toutes les inscriptions (historique) d'un élève, de la plus récente à la plus ancienne.
     *
     * @return Registration[]
     */
    public function findHistoryForStudent(Student $student): array
    {
        return $this->createQueryBuilder('i')
            ->join('i.schoolYear', 'y')
            ->innerJoin('i.preRegistration', 'pr')
            ->leftJoin(Student::class, 'st', 'WITH', 'st.preRegistration = pr')
            ->andWhere('pr.existingStudent = :student OR st = :student')
            ->setParameter('student', $student)
            ->orderBy('y.startDate', 'DESC')
            ->getQuery()
            ->getResult();
    }
}

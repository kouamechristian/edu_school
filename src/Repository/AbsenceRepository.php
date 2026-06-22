<?php

namespace App\Repository;

use App\Entity\Absence;
use App\Entity\PreRegistration;
use App\Entity\Registration;
use App\Entity\Student;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Absence>
 *
 * @method Absence|null find($id, $lockMode = null, $lockVersion = null)
 * @method Absence|null findOneBy(array $criteria, array $orderBy = null)
 * @method Absence[]    findAll()
 * @method Absence[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class AbsenceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Absence::class);
    }

    public function save(Absence $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Absence $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Trouve les absences par élève
     */
    public function findByStudent(int $studentId): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.student = :studentId')
            ->andWhere('a.isActive = :active')
            ->setParameter('studentId', $studentId)
            ->setParameter('active', true)
            ->orderBy('a.date', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les absences par établissement
     */
    public function findBySchool(int $schoolId): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.school = :schoolId')
            ->andWhere('a.isActive = :active')
            ->setParameter('schoolId', $schoolId)
            ->setParameter('active', true)
            ->orderBy('a.date', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les absences par classe (via l'inscription de l'élève).
     *
     * Une classe appartient à une seule année : filtrer sur la registration
     * rattachée à cette classe cible donc les bons élèves/année.
     */
    public function findByClassroom(int $classroomId): array
    {
        return $this->createQueryBuilder('a')
            ->join('a.student', 's')
            ->join(PreRegistration::class, 'r_pre', 'WITH', 's.preRegistration = r_pre OR r_pre.existingStudent = s')
            ->join(Registration::class, 'r', 'WITH', 'r.preRegistration = r_pre')
            ->andWhere('r.classroom = :classroomId')
            ->andWhere('a.isActive = :active')
            ->setParameter('classroomId', $classroomId)
            ->setParameter('active', true)
            ->orderBy('a.date', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les absences par période
     */
    public function findByPeriod(int $periodId): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.period = :periodId')
            ->andWhere('a.isActive = :active')
            ->setParameter('periodId', $periodId)
            ->setParameter('active', true)
            ->orderBy('a.date', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les absences par période et classe
     */
    public function findByPeriodAndClassroom(int $periodId, int $classroomId): array
    {
        return $this->createQueryBuilder('a')
            ->join('a.student', 's')
            ->join(PreRegistration::class, 'r_pre', 'WITH', 's.preRegistration = r_pre OR r_pre.existingStudent = s')
            ->join(Registration::class, 'r', 'WITH', 'r.preRegistration = r_pre')
            ->andWhere('a.period = :periodId')
            ->andWhere('r.classroom = :classroomId')
            ->andWhere('a.isActive = :active')
            ->setParameter('periodId', $periodId)
            ->setParameter('classroomId', $classroomId)
            ->setParameter('active', true)
            ->orderBy('a.date', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les absences par date
     */
    public function findByDate(\DateTimeInterface $date, int $schoolId): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.date = :date')
            ->andWhere('a.school = :schoolId')
            ->andWhere('a.isActive = :active')
            ->setParameter('date', $date->format('Y-m-d'))
            ->setParameter('schoolId', $schoolId)
            ->setParameter('active', true)
            ->orderBy('a.student', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les absences par statut de justification
     */
    public function findByJustificationStatus(string $status, int $schoolId): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.justificationStatus = :status')
            ->andWhere('a.school = :schoolId')
            ->andWhere('a.isActive = :active')
            ->setParameter('status', $status)
            ->setParameter('schoolId', $schoolId)
            ->setParameter('active', true)
            ->orderBy('a.date', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les absences en attente de justification
     */
    public function findPendingJustification(int $schoolId): array
    {
        return $this->findByJustificationStatus('pending', $schoolId);
    }

    /**
     * Trouve les absences justifiées
     */
    public function findJustified(int $schoolId): array
    {
        return $this->findByJustificationStatus('justified', $schoolId);
    }

    /**
     * Trouve les absences non justifiées
     */
    public function findUnjustified(int $schoolId): array
    {
        return $this->findByJustificationStatus('unjustified', $schoolId);
    }

    /**
     * Compte les absences par élève et période
     */
    public function countByStudentAndPeriod(int $studentId, int $periodId): int
    {
        return $this->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->andWhere('a.student = :studentId')
            ->andWhere('a.period = :periodId')
            ->andWhere('a.isActive = :active')
            ->setParameter('studentId', $studentId)
            ->setParameter('periodId', $periodId)
            ->setParameter('active', true)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Absences actives d'un élève pour une période (avec leur type chargé),
     * pour le calcul des heures et de la pénalité de conduite.
     *
     * @return Absence[]
     */
    public function findActiveByStudentAndPeriod(int $studentId, int $periodId): array
    {
        return $this->createQueryBuilder('a')
            ->leftJoin('a.absenceType', 'at')->addSelect('at')
            ->andWhere('a.student = :studentId')
            ->andWhere('a.period = :periodId')
            ->andWhere('a.isActive = :active')
            ->setParameter('studentId', $studentId)
            ->setParameter('periodId', $periodId)
            ->setParameter('active', true)
            ->getQuery()
            ->getResult();
    }

    /**
     * Compte les absences par élève, période et type
     */
    public function countByStudentPeriodAndType(int $studentId, int $periodId, string $type): int
    {
        return $this->createQueryBuilder('a')
            ->join('a.absenceType', 'at')
            ->select('COUNT(a.id)')
            ->andWhere('a.student = :studentId')
            ->andWhere('a.period = :periodId')
            ->andWhere('at.type = :type')
            ->andWhere('a.isActive = :active')
            ->setParameter('studentId', $studentId)
            ->setParameter('periodId', $periodId)
            ->setParameter('type', $type)
            ->setParameter('active', true)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Trouve les absences par plage de dates
     */
    public function findByDateRange(\DateTimeInterface $startDate, \DateTimeInterface $endDate, int $schoolId): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.date >= :startDate')
            ->andWhere('a.date <= :endDate')
            ->andWhere('a.school = :schoolId')
            ->andWhere('a.isActive = :active')
            ->setParameter('startDate', $startDate->format('Y-m-d'))
            ->setParameter('endDate', $endDate->format('Y-m-d'))
            ->setParameter('schoolId', $schoolId)
            ->setParameter('active', true)
            ->orderBy('a.date', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les élèves avec le plus d'absences
     */
    public function findStudentsWithMostAbsences(int $schoolId, int $limit = 10): array
    {
        return $this->createQueryBuilder('a')
            ->select('s.id, s.firstName, s.lastName, COUNT(a.id) as absenceCount')
            ->join('a.student', 's')
            ->andWhere('a.school = :schoolId')
            ->andWhere('a.isActive = :active')
            ->setParameter('schoolId', $schoolId)
            ->setParameter('active', true)
            ->groupBy('s.id')
            ->orderBy('absenceCount', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}

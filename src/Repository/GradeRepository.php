<?php

namespace App\Repository;

use App\Entity\Grade;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Grade>
 */
class GradeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Grade::class);
    }

    /**
     * Trouve les notes par évaluation
     */
    public function findByEvaluation(int $evaluationId): array
    {
        return $this->createQueryBuilder('g')
            ->leftJoin('g.student', 's')
            ->andWhere('g.evaluation = :evaluation')
            ->setParameter('evaluation', $evaluationId)
            ->orderBy('s.lastName', 'ASC')
            ->addOrderBy('s.firstName', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les notes d'un élève
     */
    public function findByStudent(int $studentId): array
    {
        return $this->createQueryBuilder('g')
            ->leftJoin('g.evaluation', 'e')
            ->andWhere('g.student = :student')
            ->setParameter('student', $studentId)
            ->orderBy('e.date', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les notes d'un élève pour une période
     */
    public function findByStudentAndPeriod(int $studentId, int $periodId): array
    {
        return $this->createQueryBuilder('g')
            ->leftJoin('g.evaluation', 'e')
            ->andWhere('g.student = :student')
            ->andWhere('e.period = :period')
            ->andWhere('e.isActive = :active')
            ->setParameter('student', $studentId)
            ->setParameter('period', $periodId)
            ->setParameter('active', true)
            ->orderBy('e.date', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Calcule la moyenne d'un élève pour une matière et une période
     */
    public function calculateAverageByStudentSubjectAndPeriod(int $studentId, int $subjectId, int $periodId): ?float
    {
        $qb = $this->createQueryBuilder('g')
            ->select('SUM(g.value * e.coefficient) as totalPoints')
            ->addSelect('SUM(e.coefficient) as totalCoef')
            ->leftJoin('g.evaluation', 'e')
            ->andWhere('g.student = :student')
            ->andWhere('e.subject = :subject')
            ->andWhere('e.period = :period')
            ->andWhere('e.isActive = :active')
            ->andWhere('e.isPublished = :published')
            ->andWhere('g.value IS NOT NULL')
            ->andWhere('g.status IS NULL')
            ->setParameter('student', $studentId)
            ->setParameter('subject', $subjectId)
            ->setParameter('period', $periodId)
            ->setParameter('active', true)
            ->setParameter('published', true);

        $result = $qb->getQuery()->getOneOrNullResult();

        if (!$result || !$result['totalCoef'] || $result['totalCoef'] == 0) {
            return null;
        }

        return round($result['totalPoints'] / $result['totalCoef'], 2);
    }

    /**
     * Calcule la moyenne générale d'un élève pour une période
     */
    public function calculateGeneralAverageByStudentAndPeriod(int $studentId, int $periodId): ?float
    {
        $qb = $this->createQueryBuilder('g')
            ->select('SUM(g.value * e.coefficient * s.coefficient) as totalPoints')
            ->addSelect('SUM(e.coefficient * s.coefficient) as totalCoef')
            ->leftJoin('g.evaluation', 'e')
            ->leftJoin('e.subject', 's')
            ->andWhere('g.student = :student')
            ->andWhere('e.period = :period')
            ->andWhere('e.isActive = :active')
            ->andWhere('e.isPublished = :published')
            ->andWhere('g.value IS NOT NULL')
            ->andWhere('g.status IS NULL')
            ->setParameter('student', $studentId)
            ->setParameter('period', $periodId)
            ->setParameter('active', true)
            ->setParameter('published', true);

        $result = $qb->getQuery()->getOneOrNullResult();

        if (!$result || !$result['totalCoef'] || $result['totalCoef'] == 0) {
            return null;
        }

        return round($result['totalPoints'] / $result['totalCoef'], 2);
    }

    /**
     * Statistiques de classe pour une évaluation
     */
    public function getEvaluationStatistics(int $evaluationId): array
    {
        $qb = $this->createQueryBuilder('g')
            ->select('AVG(g.value) as average')
            ->addSelect('MIN(g.value) as min')
            ->addSelect('MAX(g.value) as max')
            ->addSelect('COUNT(g.id) as count')
            ->andWhere('g.evaluation = :evaluation')
            ->andWhere('g.value IS NOT NULL')
            ->andWhere('g.status IS NULL')
            ->setParameter('evaluation', $evaluationId);

        $result = $qb->getQuery()->getOneOrNullResult();

        return [
            'average' => $result['average'] ? round($result['average'], 2) : null,
            'min' => $result['min'],
            'max' => $result['max'],
            'count' => $result['count']
        ];
    }
}


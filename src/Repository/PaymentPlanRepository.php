<?php

namespace App\Repository;

use App\Entity\PaymentPlan;
use App\Entity\Student;
use App\Entity\Fee;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PaymentPlan>
 */
class PaymentPlanRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PaymentPlan::class);
    }

    public function save(PaymentPlan $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(PaymentPlan $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Trouve les plans de paiement par élève
     */
    public function findByStudent(Student $student): array
    {
        return $this->createQueryBuilder('pp')
            ->andWhere('pp.student = :student')
            ->setParameter('student', $student)
            ->orderBy('pp.startDate', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les plans de paiement par frais
     */
    public function findByFee(Fee $fee): array
    {
        return $this->createQueryBuilder('pp')
            ->andWhere('pp.fee = :fee')
            ->setParameter('fee', $fee)
            ->orderBy('pp.startDate', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les plans de paiement par statut
     */
    public function findByStatus(string $status): array
    {
        return $this->createQueryBuilder('pp')
            ->andWhere('pp.status = :status')
            ->setParameter('status', $status)
            ->orderBy('pp.startDate', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les plans de paiement actifs
     */
    public function findActive(): array
    {
        return $this->createQueryBuilder('pp')
            ->andWhere('pp.status = :status')
            ->andWhere('pp.startDate <= :now')
            ->andWhere('pp.endDate >= :now OR pp.endDate IS NULL')
            ->setParameter('status', 'actif')
            ->setParameter('now', new \DateTime())
            ->orderBy('pp.startDate', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les plans de paiement terminés
     */
    public function findCompleted(): array
    {
        return $this->findByStatus('terminé');
    }

    /**
     * Trouve les plans de paiement suspendus
     */
    public function findSuspended(): array
    {
        return $this->findByStatus('suspendu');
    }

    /**
     * Trouve les plans de paiement annulés
     */
    public function findCancelled(): array
    {
        return $this->findByStatus('annulé');
    }

    /**
     * Trouve les plans de paiement par fréquence
     */
    public function findByFrequency(string $frequency): array
    {
        return $this->createQueryBuilder('pp')
            ->andWhere('pp.frequency = :frequency')
            ->setParameter('frequency', $frequency)
            ->orderBy('pp.startDate', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les plans de paiement par période
     */
    public function findByDateRange(\DateTimeInterface $startDate, \DateTimeInterface $endDate): array
    {
        return $this->createQueryBuilder('pp')
            ->andWhere('pp.startDate <= :endDate')
            ->andWhere('pp.endDate >= :startDate OR pp.endDate IS NULL')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->orderBy('pp.startDate', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les plans de paiement qui se terminent bientôt
     */
    public function findEndingSoon(int $days = 30): array
    {
        $date = new \DateTime();
        $date->modify("+{$days} days");

        return $this->createQueryBuilder('pp')
            ->andWhere('pp.endDate <= :date')
            ->andWhere('pp.endDate >= :now')
            ->andWhere('pp.status = :status')
            ->setParameter('date', $date)
            ->setParameter('now', new \DateTime())
            ->setParameter('status', 'actif')
            ->orderBy('pp.endDate', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Calcule le montant total des plans de paiement par élève
     */
    public function getTotalAmountByStudent(Student $student): float
    {
        $result = $this->createQueryBuilder('pp')
            ->select('SUM(pp.totalAmount) as total')
            ->andWhere('pp.student = :student')
            ->andWhere('pp.status != :cancelled')
            ->setParameter('student', $student)
            ->setParameter('cancelled', 'annulé')
            ->getQuery()
            ->getSingleScalarResult();

        return (float) ($result ?? 0);
    }

    /**
     * Calcule le montant total payé par élève via plans de paiement
     */
    public function getTotalPaidByStudent(Student $student): float
    {
        $result = $this->createQueryBuilder('pp')
            ->select('SUM(pp.paidAmount) as total')
            ->andWhere('pp.student = :student')
            ->setParameter('student', $student)
            ->getQuery()
            ->getSingleScalarResult();

        return (float) ($result ?? 0);
    }

    /**
     * Calcule le montant restant par élève via plans de paiement
     */
    public function getTotalRemainingByStudent(Student $student): float
    {
        $result = $this->createQueryBuilder('pp')
            ->select('SUM(pp.remainingAmount) as total')
            ->andWhere('pp.student = :student')
            ->andWhere('pp.status NOT IN (:completedStatuses)')
            ->setParameter('student', $student)
            ->setParameter('completedStatuses', ['terminé', 'annulé'])
            ->getQuery()
            ->getSingleScalarResult();

        return (float) ($result ?? 0);
    }

    /**
     * Compte les plans de paiement par statut
     */
    public function countByStatus(): array
    {
        return $this->createQueryBuilder('pp')
            ->select('pp.status, COUNT(pp.id) as count')
            ->groupBy('pp.status')
            ->orderBy('count', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Compte les plans de paiement par fréquence
     */
    public function countByFrequency(): array
    {
        return $this->createQueryBuilder('pp')
            ->select('pp.frequency, COUNT(pp.id) as count')
            ->groupBy('pp.frequency')
            ->orderBy('count', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les plans de paiement récents
     */
    public function findRecent(int $limit = 10): array
    {
        return $this->createQueryBuilder('pp')
            ->orderBy('pp.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Recherche les plans de paiement par nom
     */
    public function searchByName(string $search): array
    {
        return $this->createQueryBuilder('pp')
            ->andWhere('pp.name LIKE :search')
            ->setParameter('search', '%' . $search . '%')
            ->orderBy('pp.startDate', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les plans de paiement par utilisateur qui les a créés
     */
    public function findByCreatedBy(int $userId): array
    {
        return $this->createQueryBuilder('pp')
            ->andWhere('pp.createdBy = :userId')
            ->setParameter('userId', $userId)
            ->orderBy('pp.startDate', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Calcule les statistiques de plans de paiement pour un élève
     */
    public function getPaymentPlanStatsByStudent(Student $student): array
    {
        $qb = $this->createQueryBuilder('pp')
            ->select([
                'COUNT(pp.id) as total_plans',
                'SUM(pp.totalAmount) as total_amount',
                'SUM(pp.paidAmount) as paid_amount',
                'SUM(pp.remainingAmount) as remaining_amount',
                'SUM(CASE WHEN pp.status = \'terminé\' THEN 1 ELSE 0 END) as completed_count',
                'SUM(CASE WHEN pp.status = \'actif\' THEN 1 ELSE 0 END) as active_count'
            ])
            ->andWhere('pp.student = :student')
            ->andWhere('pp.status != :cancelled')
            ->setParameter('student', $student)
            ->setParameter('cancelled', 'annulé');

        return $qb->getQuery()->getSingleResult();
    }

    /**
     * Trouve les plans de paiement avec un nombre d'échéances spécifique
     */
    public function findByInstallmentCount(int $count, string $operator = '='): array
    {
        return $this->createQueryBuilder('pp')
            ->andWhere("pp.installmentCount {$operator} :count")
            ->setParameter('count', $count)
            ->orderBy('pp.startDate', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les plans de paiement avec un montant d'échéance spécifique
     */
    public function findByInstallmentAmount(float $amount, string $operator = '='): array
    {
        return $this->createQueryBuilder('pp')
            ->andWhere("pp.installmentAmount {$operator} :amount")
            ->setParameter('amount', $amount)
            ->orderBy('pp.startDate', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les plans de paiement non terminés
     */
    public function findIncomplete(): array
    {
        return $this->createQueryBuilder('pp')
            ->andWhere('pp.status NOT IN (:completedStatuses)')
            ->setParameter('completedStatuses', ['terminé', 'annulé'])
            ->orderBy('pp.endDate', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les plans de paiement en retard
     */
    public function findOverdue(): array
    {
        return $this->createQueryBuilder('pp')
            ->andWhere('pp.endDate < :now')
            ->andWhere('pp.status NOT IN (:completedStatuses)')
            ->setParameter('now', new \DateTime())
            ->setParameter('completedStatuses', ['terminé', 'annulé'])
            ->orderBy('pp.endDate', 'ASC')
            ->getQuery()
            ->getResult();
    }
}

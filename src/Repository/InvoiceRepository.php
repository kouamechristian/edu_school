<?php

namespace App\Repository;

use App\Entity\Invoice;
use App\Entity\Student;
use App\Entity\Fee;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Invoice>
 */
class InvoiceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Invoice::class);
    }

    public function save(Invoice $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Invoice $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Trouve les factures par élève
     */
    public function findByStudent(Student $student): array
    {
        return $this->createQueryBuilder('i')
            ->andWhere('i.student = :student')
            ->setParameter('student', $student)
            ->orderBy('i.issueDate', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les factures par frais
     */
    public function findByFee(Fee $fee): array
    {
        return $this->createQueryBuilder('i')
            ->andWhere('i.fee = :fee')
            ->setParameter('fee', $fee)
            ->orderBy('i.issueDate', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les factures par statut
     */
    public function findByStatus(string $status): array
    {
        return $this->createQueryBuilder('i')
            ->andWhere('i.status = :status')
            ->setParameter('status', $status)
            ->orderBy('i.issueDate', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les factures en retard
     */
    public function findOverdue(): array
    {
        return $this->createQueryBuilder('i')
            ->andWhere('i.dueDate < :now')
            ->andWhere('i.status NOT IN (:paidStatuses)')
            ->setParameter('now', new \DateTime())
            ->setParameter('paidStatuses', ['payée', 'annulée'])
            ->orderBy('i.dueDate', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les factures avec échéance proche
     */
    public function findWithDueDateNear(int $days = 7): array
    {
        $date = new \DateTime();
        $date->modify("+{$days} days");

        return $this->createQueryBuilder('i')
            ->andWhere('i.dueDate <= :date')
            ->andWhere('i.dueDate >= :now')
            ->andWhere('i.status NOT IN (:paidStatuses)')
            ->setParameter('date', $date)
            ->setParameter('now', new \DateTime())
            ->setParameter('paidStatuses', ['payée', 'annulée'])
            ->orderBy('i.dueDate', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les factures par période
     */
    public function findByDateRange(\DateTimeInterface $startDate, \DateTimeInterface $endDate): array
    {
        return $this->createQueryBuilder('i')
            ->andWhere('i.issueDate >= :startDate')
            ->andWhere('i.issueDate <= :endDate')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->orderBy('i.issueDate', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les factures en brouillon
     */
    public function findDraft(): array
    {
        return $this->findByStatus('brouillon');
    }

    /**
     * Trouve les factures envoyées
     */
    public function findSent(): array
    {
        return $this->findByStatus('envoyée');
    }

    /**
     * Trouve les factures payées
     */
    public function findPaid(): array
    {
        return $this->findByStatus('payée');
    }

    /**
     * Trouve les factures partiellement payées
     */
    public function findPartiallyPaid(): array
    {
        return $this->findByStatus('partiellement_payée');
    }

    /**
     * Calcule le montant total des factures par élève
     */
    public function getTotalAmountByStudent(Student $student): float
    {
        $result = $this->createQueryBuilder('i')
            ->select('SUM(i.totalAmount) as total')
            ->andWhere('i.student = :student')
            ->andWhere('i.status != :cancelled')
            ->setParameter('student', $student)
            ->setParameter('cancelled', 'annulée')
            ->getQuery()
            ->getSingleScalarResult();

        return (float) ($result ?? 0);
    }

    /**
     * Calcule le montant total payé par élève
     */
    public function getTotalPaidByStudent(Student $student): float
    {
        $result = $this->createQueryBuilder('i')
            ->select('SUM(i.paidAmount) as total')
            ->andWhere('i.student = :student')
            ->setParameter('student', $student)
            ->getQuery()
            ->getSingleScalarResult();

        return (float) ($result ?? 0);
    }

    /**
     * Calcule le montant restant par élève
     */
    public function getTotalRemainingByStudent(Student $student): float
    {
        $result = $this->createQueryBuilder('i')
            ->select('SUM(i.remainingAmount) as total')
            ->andWhere('i.student = :student')
            ->andWhere('i.status NOT IN (:paidStatuses)')
            ->setParameter('student', $student)
            ->setParameter('paidStatuses', ['payée', 'annulée'])
            ->getQuery()
            ->getSingleScalarResult();

        return (float) ($result ?? 0);
    }

    /**
     * Calcule le montant total des factures par période
     */
    public function getTotalAmountByDateRange(\DateTimeInterface $startDate, \DateTimeInterface $endDate): float
    {
        $result = $this->createQueryBuilder('i')
            ->select('SUM(i.totalAmount) as total')
            ->andWhere('i.issueDate >= :startDate')
            ->andWhere('i.issueDate <= :endDate')
            ->andWhere('i.status != :cancelled')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->setParameter('cancelled', 'annulée')
            ->getQuery()
            ->getSingleScalarResult();

        return (float) ($result ?? 0);
    }

    /**
     * Compte les factures par statut
     */
    public function countByStatus(): array
    {
        return $this->createQueryBuilder('i')
            ->select('i.status, COUNT(i.id) as count')
            ->groupBy('i.status')
            ->orderBy('count', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les factures récentes
     */
    public function findRecent(int $limit = 10): array
    {
        return $this->createQueryBuilder('i')
            ->orderBy('i.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Recherche les factures par numéro
     */
    public function searchByNumber(string $search): array
    {
        return $this->createQueryBuilder('i')
            ->andWhere('i.invoiceNumber LIKE :search')
            ->setParameter('search', '%' . $search . '%')
            ->orderBy('i.issueDate', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les factures par utilisateur qui les a créées
     */
    public function findByCreatedBy(int $userId): array
    {
        return $this->createQueryBuilder('i')
            ->andWhere('i.createdBy = :userId')
            ->setParameter('userId', $userId)
            ->orderBy('i.issueDate', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Calcule les statistiques de facturation pour un élève
     */
    public function getInvoiceStatsByStudent(Student $student): array
    {
        $qb = $this->createQueryBuilder('i')
            ->select([
                'COUNT(i.id) as total_invoices',
                'SUM(i.totalAmount) as total_amount',
                'SUM(i.paidAmount) as paid_amount',
                'SUM(i.remainingAmount) as remaining_amount',
                'SUM(CASE WHEN i.status = \'payée\' THEN 1 ELSE 0 END) as paid_count',
                'SUM(CASE WHEN i.status = \'en_retard\' THEN 1 ELSE 0 END) as overdue_count'
            ])
            ->andWhere('i.student = :student')
            ->andWhere('i.status != :cancelled')
            ->setParameter('student', $student)
            ->setParameter('cancelled', 'annulée');

        return $qb->getQuery()->getSingleResult();
    }

    /**
     * Trouve les factures avec un montant restant spécifique
     */
    public function findByRemainingAmount(float $amount, string $operator = '='): array
    {
        $qb = $this->createQueryBuilder('i')
            ->andWhere("i.remainingAmount {$operator} :amount")
            ->andWhere('i.status NOT IN (:paidStatuses)')
            ->setParameter('amount', $amount)
            ->setParameter('paidStatuses', ['payée', 'annulée'])
            ->orderBy('i.dueDate', 'ASC');

        return $qb->getQuery()->getResult();
    }

    /**
     * Trouve les factures non payées
     */
    public function findUnpaid(): array
    {
        return $this->createQueryBuilder('i')
            ->andWhere('i.status NOT IN (:paidStatuses)')
            ->setParameter('paidStatuses', ['payée', 'annulée'])
            ->orderBy('i.dueDate', 'ASC')
            ->getQuery()
            ->getResult();
    }
}

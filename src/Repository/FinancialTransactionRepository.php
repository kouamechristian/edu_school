<?php

namespace App\Repository;

use App\Entity\FinancialTransaction;
use App\Entity\Student;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<FinancialTransaction>
 */
class FinancialTransactionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, FinancialTransaction::class);
    }

    public function save(FinancialTransaction $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(FinancialTransaction $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Trouve les transactions par élève
     */
    public function findByStudent(Student $student): array
    {
        return $this->createQueryBuilder('ft')
            ->andWhere('ft.student = :student')
            ->setParameter('student', $student)
            ->orderBy('ft.transactionDate', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les transactions par type
     */
    public function findByType(string $type): array
    {
        return $this->createQueryBuilder('ft')
            ->andWhere('ft.type = :type')
            ->setParameter('type', $type)
            ->orderBy('ft.transactionDate', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les transactions par catégorie
     */
    public function findByCategory(string $category): array
    {
        return $this->createQueryBuilder('ft')
            ->andWhere('ft.category = :category')
            ->setParameter('category', $category)
            ->orderBy('ft.transactionDate', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les transactions par statut
     */
    public function findByStatus(string $status): array
    {
        return $this->createQueryBuilder('ft')
            ->andWhere('ft.status = :status')
            ->setParameter('status', $status)
            ->orderBy('ft.transactionDate', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les transactions par méthode de paiement
     */
    public function findByPaymentMethod(string $method): array
    {
        return $this->createQueryBuilder('ft')
            ->andWhere('ft.paymentMethod = :method')
            ->setParameter('method', $method)
            ->orderBy('ft.transactionDate', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les transactions par période
     */
    public function findByDateRange(\DateTimeInterface $startDate, \DateTimeInterface $endDate): array
    {
        return $this->createQueryBuilder('ft')
            ->andWhere('ft.transactionDate >= :startDate')
            ->andWhere('ft.transactionDate <= :endDate')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->orderBy('ft.transactionDate', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les transactions en attente
     */
    public function findPending(): array
    {
        return $this->findByStatus('en_attente');
    }

    /**
     * Trouve les transactions confirmées
     */
    public function findConfirmed(): array
    {
        return $this->findByStatus('confirmé');
    }

    /**
     * Trouve les transactions annulées
     */
    public function findCancelled(): array
    {
        return $this->findByStatus('annulé');
    }

    /**
     * Trouve les transactions en erreur
     */
    public function findInError(): array
    {
        return $this->findByStatus('en_erreur');
    }

    /**
     * Trouve les transactions d'entrée
     */
    public function findIncome(): array
    {
        return $this->findByType('entrée');
    }

    /**
     * Trouve les transactions de sortie
     */
    public function findExpense(): array
    {
        return $this->findByType('sortie');
    }

    /**
     * Trouve les transactions de transfert
     */
    public function findTransfer(): array
    {
        return $this->findByType('transfert');
    }

    /**
     * Calcule le montant total des transactions par élève
     */
    public function getTotalAmountByStudent(Student $student): float
    {
        $result = $this->createQueryBuilder('ft')
            ->select('SUM(ft.amount) as total')
            ->andWhere('ft.student = :student')
            ->andWhere('ft.status = :status')
            ->setParameter('student', $student)
            ->setParameter('status', 'confirmé')
            ->getQuery()
            ->getSingleScalarResult();

        return (float) ($result ?? 0);
    }

    /**
     * Calcule le montant total des entrées par élève
     */
    public function getTotalIncomeByStudent(Student $student): float
    {
        $result = $this->createQueryBuilder('ft')
            ->select('SUM(ft.amount) as total')
            ->andWhere('ft.student = :student')
            ->andWhere('ft.type = :type')
            ->andWhere('ft.status = :status')
            ->setParameter('student', $student)
            ->setParameter('type', 'entrée')
            ->setParameter('status', 'confirmé')
            ->getQuery()
            ->getSingleScalarResult();

        return (float) ($result ?? 0);
    }

    /**
     * Calcule le montant total des sorties par élève
     */
    public function getTotalExpenseByStudent(Student $student): float
    {
        $result = $this->createQueryBuilder('ft')
            ->select('SUM(ft.amount) as total')
            ->andWhere('ft.student = :student')
            ->andWhere('ft.type = :type')
            ->andWhere('ft.status = :status')
            ->setParameter('student', $student)
            ->setParameter('type', 'sortie')
            ->setParameter('status', 'confirmé')
            ->getQuery()
            ->getSingleScalarResult();

        return (float) ($result ?? 0);
    }

    /**
     * Calcule le montant total des transactions par période
     */
    public function getTotalAmountByDateRange(\DateTimeInterface $startDate, \DateTimeInterface $endDate): float
    {
        $result = $this->createQueryBuilder('ft')
            ->select('SUM(ft.amount) as total')
            ->andWhere('ft.transactionDate >= :startDate')
            ->andWhere('ft.transactionDate <= :endDate')
            ->andWhere('ft.status = :status')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->setParameter('status', 'confirmé')
            ->getQuery()
            ->getSingleScalarResult();

        return (float) ($result ?? 0);
    }

    /**
     * Calcule le montant total des entrées par période
     */
    public function getTotalIncomeByDateRange(\DateTimeInterface $startDate, \DateTimeInterface $endDate): float
    {
        $result = $this->createQueryBuilder('ft')
            ->select('SUM(ft.amount) as total')
            ->andWhere('ft.transactionDate >= :startDate')
            ->andWhere('ft.transactionDate <= :endDate')
            ->andWhere('ft.type = :type')
            ->andWhere('ft.status = :status')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->setParameter('type', 'entrée')
            ->setParameter('status', 'confirmé')
            ->getQuery()
            ->getSingleScalarResult();

        return (float) ($result ?? 0);
    }

    /**
     * Calcule le montant total des sorties par période
     */
    public function getTotalExpenseByDateRange(\DateTimeInterface $startDate, \DateTimeInterface $endDate): float
    {
        $result = $this->createQueryBuilder('ft')
            ->select('SUM(ft.amount) as total')
            ->andWhere('ft.transactionDate >= :startDate')
            ->andWhere('ft.transactionDate <= :endDate')
            ->andWhere('ft.type = :type')
            ->andWhere('ft.status = :status')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->setParameter('type', 'sortie')
            ->setParameter('status', 'confirmé')
            ->getQuery()
            ->getSingleScalarResult();

        return (float) ($result ?? 0);
    }

    /**
     * Compte les transactions par statut
     */
    public function countByStatus(): array
    {
        return $this->createQueryBuilder('ft')
            ->select('ft.status, COUNT(ft.id) as count')
            ->groupBy('ft.status')
            ->orderBy('count', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Compte les transactions par type
     */
    public function countByType(): array
    {
        return $this->createQueryBuilder('ft')
            ->select('ft.type, COUNT(ft.id) as count')
            ->groupBy('ft.type')
            ->orderBy('count', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Compte les transactions par catégorie
     */
    public function countByCategory(): array
    {
        return $this->createQueryBuilder('ft')
            ->select('ft.category, COUNT(ft.id) as count')
            ->groupBy('ft.category')
            ->orderBy('count', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Compte les transactions par méthode de paiement
     */
    public function countByPaymentMethod(): array
    {
        return $this->createQueryBuilder('ft')
            ->select('ft.paymentMethod, COUNT(ft.id) as count')
            ->groupBy('ft.paymentMethod')
            ->orderBy('count', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les transactions récentes
     */
    public function findRecent(int $limit = 10): array
    {
        return $this->createQueryBuilder('ft')
            ->orderBy('ft.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Recherche les transactions par numéro ou référence
     */
    public function searchByNumberOrReference(string $search): array
    {
        return $this->createQueryBuilder('ft')
            ->andWhere('ft.transactionNumber LIKE :search OR ft.reference LIKE :search')
            ->setParameter('search', '%' . $search . '%')
            ->orderBy('ft.transactionDate', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les transactions par utilisateur qui les a enregistrées
     */
    public function findByRecordedBy(int $userId): array
    {
        return $this->createQueryBuilder('ft')
            ->andWhere('ft.recordedBy = :userId')
            ->setParameter('userId', $userId)
            ->orderBy('ft.transactionDate', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Calcule les statistiques de transactions pour un élève
     */
    public function getTransactionStatsByStudent(Student $student): array
    {
        $qb = $this->createQueryBuilder('ft')
            ->select([
                'COUNT(ft.id) as total_transactions',
                'SUM(CASE WHEN ft.type = \'entrée\' AND ft.status = \'confirmé\' THEN ft.amount ELSE 0 END) as total_income',
                'SUM(CASE WHEN ft.type = \'sortie\' AND ft.status = \'confirmé\' THEN ft.amount ELSE 0 END) as total_expense',
                'SUM(CASE WHEN ft.status = \'en_attente\' THEN 1 ELSE 0 END) as pending_count',
                'SUM(CASE WHEN ft.status = \'annulé\' THEN 1 ELSE 0 END) as cancelled_count'
            ])
            ->andWhere('ft.student = :student')
            ->setParameter('student', $student);

        return $qb->getQuery()->getSingleResult();
    }

    /**
     * Trouve les transactions avec un montant spécifique
     */
    public function findByAmount(float $amount, string $operator = '='): array
    {
        return $this->createQueryBuilder('ft')
            ->andWhere("ft.amount {$operator} :amount")
            ->setParameter('amount', $amount)
            ->orderBy('ft.transactionDate', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les transactions par description
     */
    public function findByDescription(string $description): array
    {
        return $this->createQueryBuilder('ft')
            ->andWhere('ft.description LIKE :description')
            ->setParameter('description', '%' . $description . '%')
            ->orderBy('ft.transactionDate', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Calcule le solde net par élève (entrées - sorties)
     */
    public function getNetBalanceByStudent(Student $student): float
    {
        $income = $this->getTotalIncomeByStudent($student);
        $expense = $this->getTotalExpenseByStudent($student);
        
        return $income - $expense;
    }

    /**
     * Trouve les transactions du jour
     */
    public function findTodayTransactions(): array
    {
        $today = new \DateTime();
        $today->setTime(0, 0, 0);
        $tomorrow = clone $today;
        $tomorrow->modify('+1 day');

        return $this->findByDateRange($today, $tomorrow);
    }

    /**
     * Trouve les transactions de la semaine
     */
    public function findThisWeekTransactions(): array
    {
        $startOfWeek = new \DateTime();
        $startOfWeek->modify('monday this week')->setTime(0, 0, 0);
        $endOfWeek = clone $startOfWeek;
        $endOfWeek->modify('+7 days');

        return $this->findByDateRange($startOfWeek, $endOfWeek);
    }

    /**
     * Trouve les transactions du mois
     */
    public function findThisMonthTransactions(): array
    {
        $startOfMonth = new \DateTime();
        $startOfMonth->modify('first day of this month')->setTime(0, 0, 0);
        $endOfMonth = new \DateTime();
        $endOfMonth->modify('last day of this month')->setTime(23, 59, 59);

        return $this->findByDateRange($startOfMonth, $endOfMonth);
    }
}

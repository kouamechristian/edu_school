<?php

namespace App\Repository;

use App\Entity\Payment;
use App\Entity\Student;
use App\Entity\Fee;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Payment>
 */
class PaymentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Payment::class);
    }

    public function save(Payment $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Payment $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Trouve les paiements par élève
     */
    public function findByStudent(Student $student): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.student = :student')
            ->setParameter('student', $student)
            ->orderBy('p.paymentDate', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les paiements par frais
     */
    public function findByFee(Fee $fee): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.fee = :fee')
            ->setParameter('fee', $fee)
            ->orderBy('p.paymentDate', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les paiements par statut
     */
    public function findByStatus(string $status): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.status = :status')
            ->setParameter('status', $status)
            ->orderBy('p.paymentDate', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les paiements par méthode
     */
    public function findByPaymentMethod(string $method): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.paymentMethod = :method')
            ->setParameter('method', $method)
            ->orderBy('p.paymentDate', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les paiements par période
     */
    public function findByDateRange(\DateTimeInterface $startDate, \DateTimeInterface $endDate): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.paymentDate >= :startDate')
            ->andWhere('p.paymentDate <= :endDate')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->orderBy('p.paymentDate', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les paiements en attente
     */
    public function findPending(): array
    {
        return $this->findByStatus('en_attente');
    }

    /**
     * Trouve les paiements confirmés
     */
    public function findConfirmed(): array
    {
        return $this->findByStatus('payé');
    }

    /**
     * Trouve les paiements partiellement payés
     */
    public function findPartiallyPaid(): array
    {
        return $this->findByStatus('partiellement_payé');
    }

    /**
     * Trouve les paiements annulés
     */
    public function findCancelled(): array
    {
        return $this->findByStatus('annulé');
    }

    /**
     * Calcule le montant total des paiements par élève
     */
    public function getTotalAmountByStudent(Student $student): float
    {
        $result = $this->createQueryBuilder('p')
            ->select('SUM(p.amount) as total')
            ->andWhere('p.student = :student')
            ->andWhere('p.status IN (:statuses)')
            ->setParameter('student', $student)
            ->setParameter('statuses', ['payé', 'partiellement_payé'])
            ->getQuery()
            ->getSingleScalarResult();

        return (float) ($result ?? 0);
    }

    /**
     * Calcule le montant total des paiements par frais
     */
    public function getTotalAmountByFee(Fee $fee): float
    {
        $result = $this->createQueryBuilder('p')
            ->select('SUM(p.amount) as total')
            ->andWhere('p.fee = :fee')
            ->andWhere('p.status IN (:statuses)')
            ->setParameter('fee', $fee)
            ->setParameter('statuses', ['payé', 'partiellement_payé'])
            ->getQuery()
            ->getSingleScalarResult();

        return (float) ($result ?? 0);
    }

    /**
     * Calcule le montant total des paiements par période
     */
    public function getTotalAmountByDateRange(\DateTimeInterface $startDate, \DateTimeInterface $endDate): float
    {
        $result = $this->createQueryBuilder('p')
            ->select('SUM(p.amount) as total')
            ->andWhere('p.paymentDate >= :startDate')
            ->andWhere('p.paymentDate <= :endDate')
            ->andWhere('p.status IN (:statuses)')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->setParameter('statuses', ['payé', 'partiellement_payé'])
            ->getQuery()
            ->getSingleScalarResult();

        return (float) ($result ?? 0);
    }

    public function getTotalAmountByCashRegister(int $cashRegisterId): float
    {
        $result = $this->createQueryBuilder('p')
            ->select('SUM(p.amount) as total')
            ->andWhere('p.cashRegister = :cashRegisterId')
            ->andWhere('p.status != :cancelled')
            ->setParameter('cashRegisterId', $cashRegisterId)
            ->setParameter('cancelled', 'annulé')
            ->getQuery()
            ->getSingleScalarResult();

        return (float) ($result ?? 0);
    }

    /**
     * Compte les paiements par statut
     */
    public function countByStatus(): array
    {
        return $this->createQueryBuilder('p')
            ->select('p.status, COUNT(p.id) as count')
            ->groupBy('p.status')
            ->orderBy('count', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Compte les paiements par méthode
     */
    public function countByPaymentMethod(): array
    {
        return $this->createQueryBuilder('p')
            ->select('p.paymentMethod, COUNT(p.id) as count')
            ->groupBy('p.paymentMethod')
            ->orderBy('count', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les paiements récents
     */
    public function findRecent(int $limit = 10): array
    {
        return $this->createQueryBuilder('p')
            ->orderBy('p.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Recherche les paiements par numéro ou référence
     */
    public function searchByNumberOrReference(string $search): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.paymentNumber LIKE :search OR p.reference LIKE :search')
            ->setParameter('search', '%' . $search . '%')
            ->orderBy('p.paymentDate', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les paiements par utilisateur qui les a enregistrés
     */
    public function findByRecordedBy(int $userId): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.recordedBy = :userId')
            ->setParameter('userId', $userId)
            ->orderBy('p.paymentDate', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Calcule les statistiques de paiement pour un élève
     */
    public function getPaymentStatsByStudent(Student $student): array
    {
        $qb = $this->createQueryBuilder('p')
            ->select([
                'COUNT(p.id) as total_payments',
                'SUM(CASE WHEN p.status = \'payé\' THEN p.amount ELSE 0 END) as paid_amount',
                'SUM(CASE WHEN p.status = \'en_attente\' THEN p.amount ELSE 0 END) as pending_amount',
                'SUM(CASE WHEN p.status = \'annulé\' THEN p.amount ELSE 0 END) as cancelled_amount'
            ])
            ->andWhere('p.student = :student')
            ->setParameter('student', $student);

        return $qb->getQuery()->getSingleResult();
    }
}

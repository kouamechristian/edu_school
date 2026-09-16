<?php

namespace App\Repository;

use App\Entity\Payment;
use App\Entity\SchoolGroup;
use App\Entity\Student;
use App\Entity\Fee;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Payment>
 */
class PaymentRepository extends ServiceEntityRepository
{
    /** Statuts considérés comme effectivement encaissés. */
    private const PAID_STATUSES = ['payé', 'partiellement_payé'];

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Payment::class);
    }

    /**
     * Chiffre d'affaires (encaissements) par établissement d'un groupe.
     * Le lien vers l'école se fait via la caisse (cashRegister → school).
     * « online » isole les paiements encaissés via une caisse en ligne.
     *
     * @return array<int, array{schoolId:int, schoolName:string, revenue:float, online:float}>
     */
    public function getRevenueBySchoolForGroup(SchoolGroup $group): array
    {
        $rows = $this->createQueryBuilder('p')
            ->select(
                's.id AS schoolId',
                's.name AS schoolName',
                'SUM(p.amount) AS revenue',
                'SUM(CASE WHEN cr.isOnline = :online THEN p.amount ELSE 0 END) AS online'
            )
            ->join('p.cashRegister', 'cr')
            ->join('cr.school', 's')
            // Les arriérés antérieurs ne comptent pas dans le chiffre d'affaires de
            // l'année courante ; on écarte les paiements imputés à une telle ligne
            // (les paiements sans ligne rattachée restent comptés).
            ->leftJoin('p.studentFee', 'sf')
            ->andWhere('s.schoolGroup = :group')
            ->andWhere('p.status IN (:paid)')
            ->andWhere('sf.id IS NULL OR sf.isArriereAnterieur = false')
            ->setParameter('group', $group)
            ->setParameter('paid', self::PAID_STATUSES)
            ->setParameter('online', true)
            ->groupBy('s.id')
            ->addGroupBy('s.name')
            ->orderBy('revenue', 'DESC')
            ->getQuery()
            ->getResult();

        return array_map(static fn (array $r): array => [
            'schoolId' => (int) $r['schoolId'],
            'schoolName' => (string) $r['schoolName'],
            'revenue' => (float) $r['revenue'],
            'online' => (float) $r['online'],
        ], $rows);
    }

    /**
     * Chiffre d'affaires encaissé sur le mois en cours pour tout le groupe.
     */
    public function getMonthlyRevenueForGroup(SchoolGroup $group): float
    {
        $start = new \DateTimeImmutable('first day of this month 00:00:00');
        $end = new \DateTimeImmutable('first day of next month 00:00:00');

        $result = $this->createQueryBuilder('p')
            ->select('SUM(p.amount)')
            ->join('p.cashRegister', 'cr')
            ->join('cr.school', 's')
            // Exclut les encaissements d'arriérés antérieurs du CA mensuel (cf. getRevenueBySchoolForGroup).
            ->leftJoin('p.studentFee', 'sf')
            ->andWhere('s.schoolGroup = :group')
            ->andWhere('p.status IN (:paid)')
            ->andWhere('p.paymentDate >= :start')
            ->andWhere('p.paymentDate < :end')
            ->andWhere('sf.id IS NULL OR sf.isArriereAnterieur = false')
            ->setParameter('group', $group)
            ->setParameter('paid', self::PAID_STATUSES)
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->getQuery()
            ->getSingleScalarResult();

        return (float) ($result ?? 0);
    }

    /**
     * Paiements encaissés (payé/partiellement payé) d'un groupe d'établissements sur
     * une période donnée. Sert au rapport « paiements par jour » de l'espace fondateur.
     *
     * @return Payment[]
     */
    public function findByGroupAndDateRange(SchoolGroup $group, \DateTimeInterface $start, \DateTimeInterface $end): array
    {
        return $this->createQueryBuilder('p')
            ->join('p.cashRegister', 'cr')
            ->join('cr.school', 's')
            ->andWhere('s.schoolGroup = :group')
            ->andWhere('p.paymentDate >= :start')
            ->andWhere('p.paymentDate <= :end')
            ->andWhere('p.status IN (:paid)')
            ->setParameter('group', $group)
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->setParameter('paid', self::PAID_STATUSES)
            ->orderBy('p.paymentDate', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Regroupe une liste de paiements par reçu.
     *
     * Une imputation sur plusieurs frais génère plusieurs lignes {@see Payment}
     * partageant le même numéro de reçu (cf. PaymentController::recordImputations).
     * Affichées telles quelles, ces lignes apparaissent comme des paiements distincts
     * et gonflent les compteurs, alors qu'il s'agit d'un seul encaissement : cette
     * méthode les réunit sous une seule entrée (montant total, frais concernés, lignes
     * d'origine conservées pour le détail).
     *
     * @param Payment[] $payments
     *
     * @return list<array{
     *     receipt_number: string, date: ?\DateTimeInterface, student: ?Student, school: ?\App\Entity\School,
     *     amount: float, method_label: string, status: string, status_label: string, status_color: string,
     *     fee_names: string[], lines: Payment[]
     * }>
     */
    public function groupByReceipt(array $payments): array
    {
        $groups = [];
        foreach ($payments as $payment) {
            $key = $payment->getReceiptNumber() ?? 'PN-'.$payment->getPaymentNumber();

            if (!isset($groups[$key])) {
                $groups[$key] = [
                    'receipt_number' => $payment->getReceiptNumber() ?? $payment->getPaymentNumber(),
                    'date' => $payment->getPaymentDate(),
                    'student' => $payment->getStudent(),
                    'school' => $payment->getSchool(),
                    'amount' => (float) ($payment->getReceiptAmount() ?: $payment->getAmount()),
                    'method_label' => $payment->getPaymentMethodLabel(),
                    'status' => $payment->getStatus(),
                    'status_label' => $payment->getStatusLabel(),
                    'status_color' => $payment->getStatusColor(),
                    'fee_names' => [],
                    'lines' => [],
                ];
            }

            $feeName = $payment->getFee()?->getName();
            if ($feeName !== null && !\in_array($feeName, $groups[$key]['fee_names'], true)) {
                $groups[$key]['fee_names'][] = $feeName;
            }
            $groups[$key]['lines'][] = $payment;
        }

        // Les lignes d'un même reçu sont normalement encaissées ensemble (même statut),
        // sauf si une ligne a été annulée/confirmée individuellement depuis : dans ce cas
        // le statut de la première ligne ne représente plus fidèlement le reçu.
        foreach ($groups as &$group) {
            $statuses = array_unique(array_map(static fn (Payment $p): string => (string) $p->getStatus(), $group['lines']));
            if (\count($statuses) > 1) {
                $group['status'] = 'mixte';
                $group['status_label'] = 'Statuts mixtes';
                $group['status_color'] = 'secondary';
            }
        }
        unset($group);

        return array_values($groups);
    }

    /**
     * Restreint une requête aux paiements d'un établissement.
     *
     * Un paiement appartient à l'établissement de son élève : les listes et les
     * statistiques de l'espace caisse ne doivent jamais déborder sur les autres
     * établissements, même pour un utilisateur qui en gère plusieurs.
     *
     * @param string $alias Alias de l'entité Payment dans la requête
     */
    private function restrictToSchool(QueryBuilder $qb, ?int $schoolId, string $alias = 'p'): QueryBuilder
    {
        if ($schoolId !== null) {
            $qb->innerJoin($alias . '.student', 'school_scope_student')
               ->andWhere('school_scope_student.school = :schoolScopeId')
               ->setParameter('schoolScopeId', $schoolId);
        }

        return $qb;
    }

    public function save(Payment $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Journal des paiements par Mobile Money (filtrable par établissement et statut).
     *
     * @return Payment[]
     */
    public function findMobileMoney(?int $schoolId = null, ?string $status = null): array
    {
        $qb = $this->createQueryBuilder('p')
            ->leftJoin('p.student', 's')
            ->addSelect('s')
            ->andWhere('p.paymentMethod = :method')
            ->setParameter('method', 'mobile_money')
            ->orderBy('p.createdAt', 'DESC');

        if ($schoolId) {
            $qb->innerJoin('s.school', 'sc')
               ->andWhere('sc.id = :schoolId')
               ->setParameter('schoolId', $schoolId);
        }

        if ($status) {
            $qb->andWhere('p.status = :status')->setParameter('status', $status);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Historique des paiements d'une liste d'élèves (les enfants d'un parent).
     *
     * @param int[] $studentIds
     *
     * @return Payment[]
     */
    public function findByStudentIds(array $studentIds): array
    {
        if ($studentIds === []) {
            return [];
        }

        return $this->createQueryBuilder('p')
            ->andWhere('p.student IN (:ids)')
            ->setParameter('ids', $studentIds)
            ->orderBy('p.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
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
     * Trouve les paiements par statut (restreints à un établissement si fourni)
     */
    public function findByStatus(string $status, ?int $schoolId = null): array
    {
        $qb = $this->createQueryBuilder('p')
            ->andWhere('p.status = :status')
            ->setParameter('status', $status)
            ->orderBy('p.paymentDate', 'DESC');

        return $this->restrictToSchool($qb, $schoolId)->getQuery()->getResult();
    }

    /**
     * Trouve les paiements par méthode (restreints à un établissement si fourni)
     */
    public function findByPaymentMethod(string $method, ?int $schoolId = null): array
    {
        $qb = $this->createQueryBuilder('p')
            ->andWhere('p.paymentMethod = :method')
            ->setParameter('method', $method)
            ->orderBy('p.paymentDate', 'DESC');

        return $this->restrictToSchool($qb, $schoolId)->getQuery()->getResult();
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
    public function findPending(?int $schoolId = null): array
    {
        return $this->findByStatus('en_attente', $schoolId);
    }

    /**
     * Trouve les paiements confirmés
     */
    public function findConfirmed(?int $schoolId = null): array
    {
        return $this->findByStatus('payé', $schoolId);
    }

    /**
     * Trouve les paiements partiellement payés
     */
    public function findPartiallyPaid(?int $schoolId = null): array
    {
        return $this->findByStatus('partiellement_payé', $schoolId);
    }

    /**
     * Trouve les paiements annulés
     */
    public function findCancelled(?int $schoolId = null): array
    {
        return $this->findByStatus('annulé', $schoolId);
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
    public function getTotalAmountByDateRange(\DateTimeInterface $startDate, \DateTimeInterface $endDate, ?int $schoolId = null): float
    {
        $qb = $this->createQueryBuilder('p')
            ->select('SUM(p.amount) as total')
            ->andWhere('p.paymentDate >= :startDate')
            ->andWhere('p.paymentDate <= :endDate')
            ->andWhere('p.status IN (:statuses)')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->setParameter('statuses', ['payé', 'partiellement_payé']);

        $result = $this->restrictToSchool($qb, $schoolId)->getQuery()->getSingleScalarResult();

        return (float) ($result ?? 0);
    }

    /**
     * Total des encaissements d'une caisse (hors annulés).
     *
     * Représente l'argent PHYSIQUEMENT présent dans la caisse (arriérés antérieurs
     * inclus : ce sont des espèces réellement encaissées). Sert au calcul du solde
     * disponible pour les versements et les dépenses.
     */
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
     * Recettes d'une caisse HORS arriérés antérieurs (et hors annulés).
     *
     * C'est le « chiffre d'affaires » de la caisse : les encaissements d'arriérés
     * d'années précédentes en sont exclus (ils ne gonflent pas le CA courant).
     * À utiliser pour l'affichage, jamais pour le solde physique disponible.
     */
    public function getRevenueTotalByCashRegister(int $cashRegisterId): float
    {
        $result = $this->createQueryBuilder('p')
            ->select('SUM(p.amount) as total')
            ->leftJoin('p.studentFee', 'sf')
            ->andWhere('p.cashRegister = :cashRegisterId')
            ->andWhere('p.status != :cancelled')
            ->andWhere('sf.id IS NULL OR sf.isArriereAnterieur = false')
            ->setParameter('cashRegisterId', $cashRegisterId)
            ->setParameter('cancelled', 'annulé')
            ->getQuery()
            ->getSingleScalarResult();

        return (float) ($result ?? 0);
    }

    /**
     * Total des encaissements d'arriérés antérieurs d'une caisse (hors annulés).
     * Complément de {@see getRevenueTotalByCashRegister()} : total = recettes + arriérés.
     */
    public function getArrieresTotalByCashRegister(int $cashRegisterId): float
    {
        $result = $this->createQueryBuilder('p')
            ->select('SUM(p.amount) as total')
            ->join('p.studentFee', 'sf')
            ->andWhere('p.cashRegister = :cashRegisterId')
            ->andWhere('p.status != :cancelled')
            ->andWhere('sf.isArriereAnterieur = true')
            ->setParameter('cashRegisterId', $cashRegisterId)
            ->setParameter('cancelled', 'annulé')
            ->getQuery()
            ->getSingleScalarResult();

        return (float) ($result ?? 0);
    }

    /**
     * Compte les paiements par statut
     */
    public function countByStatus(?int $schoolId = null): array
    {
        $qb = $this->createQueryBuilder('p')
            ->select('p.status, COUNT(p.id) as count')
            ->groupBy('p.status')
            ->orderBy('count', 'DESC');

        return $this->restrictToSchool($qb, $schoolId)->getQuery()->getResult();
    }

    /**
     * Compte les paiements par méthode
     */
    public function countByPaymentMethod(?int $schoolId = null): array
    {
        $qb = $this->createQueryBuilder('p')
            ->select('p.paymentMethod, COUNT(p.id) as count')
            ->groupBy('p.paymentMethod')
            ->orderBy('count', 'DESC');

        return $this->restrictToSchool($qb, $schoolId)->getQuery()->getResult();
    }

    /**
     * Trouve les paiements récents (restreints à un établissement si fourni)
     */
    public function findRecent(int $limit = 10, ?int $schoolId = null): array
    {
        $qb = $this->createQueryBuilder('p')
            ->orderBy('p.createdAt', 'DESC')
            ->setMaxResults($limit);

        return $this->restrictToSchool($qb, $schoolId)->getQuery()->getResult();
    }

    /**
     * Lignes encaissées du reçu auquel appartient ce paiement (imputations d'un même
     * encaissement). Un paiement sans numéro de reçu forme un reçu à lui seul.
     *
     * @return Payment[]
     */
    public function findReceiptLines(Payment $payment): array
    {
        if ($payment->getReceiptNumber() === null) {
            return [$payment];
        }

        return $this->createQueryBuilder('p')
            ->andWhere('p.receiptNumber = :receipt')
            ->andWhere('p.status = :paid')
            ->setParameter('receipt', $payment->getReceiptNumber())
            ->setParameter('paid', 'payé')
            ->orderBy('p.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Recherche les paiements par numéro (de paiement ou de reçu) ou référence
     */
    public function searchByNumberOrReference(string $search, ?int $schoolId = null): array
    {
        $qb = $this->createQueryBuilder('p')
            ->andWhere('p.paymentNumber LIKE :search OR p.receiptNumber LIKE :search OR p.reference LIKE :search')
            ->setParameter('search', '%' . $search . '%')
            ->orderBy('p.paymentDate', 'DESC');

        return $this->restrictToSchool($qb, $schoolId)->getQuery()->getResult();
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

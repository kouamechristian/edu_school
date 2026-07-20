<?php

namespace App\Repository;

use App\Entity\OnlinePayment;
use App\Entity\School;
use App\Entity\Student;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<OnlinePayment>
 */
class OnlinePaymentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, OnlinePayment::class);
    }

    public function findOneByReference(string $reference): ?OnlinePayment
    {
        return $this->findOneBy(['reference' => $reference]);
    }

    /**
     * Verrouille la ligne le temps de la transaction de confirmation.
     *
     * Webhook et retour navigateur peuvent arriver en même temps : sans ce
     * verrou pessimiste, les deux pourraient créer un encaissement chacun.
     */
    public function findOneByReferenceForUpdate(string $reference): ?OnlinePayment
    {
        return $this->createQueryBuilder('o')
            ->andWhere('o.reference = :reference')
            ->setParameter('reference', $reference)
            ->getQuery()
            ->setLockMode(\Doctrine\DBAL\LockMode::PESSIMISTIC_WRITE)
            ->getOneOrNullResult();
    }

    /**
     * Transactions passerelle indexées par identifiant de paiement comptable.
     *
     * Sert à enrichir le journal Mobile Money : depuis la migration du 29/06,
     * `Payment` ne porte plus les détails de passerelle (opérateur, téléphone,
     * référence) — ils vivent ici.
     *
     * @param int[] $paymentIds
     *
     * @return array<int, OnlinePayment> paymentId => transaction
     */
    public function findIndexedByPaymentIds(array $paymentIds): array
    {
        if ($paymentIds === []) {
            return [];
        }

        $rows = $this->createQueryBuilder('o')
            ->andWhere('IDENTITY(o.payment) IN (:ids)')
            ->setParameter('ids', $paymentIds)
            ->getQuery()
            ->getResult();

        $indexed = [];
        foreach ($rows as $onlinePayment) {
            $paymentId = $onlinePayment->getPayment()?->getId();
            if ($paymentId !== null) {
                $indexed[$paymentId] = $onlinePayment;
            }
        }

        return $indexed;
    }

    /**
     * @return OnlinePayment[]
     */
    public function findByStudent(Student $student): array
    {
        return $this->createQueryBuilder('o')
            ->andWhere('o.student = :student')
            ->setParameter('student', $student)
            ->orderBy('o.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Journal des paiements en ligne d'un établissement, statut facultatif.
     *
     * @return OnlinePayment[]
     */
    public function findForSchool(School $school, ?string $status = null, int $limit = 200): array
    {
        $qb = $this->createQueryBuilder('o')
            ->leftJoin('o.student', 's')
            ->addSelect('s')
            ->andWhere('o.school = :school')
            ->setParameter('school', $school)
            ->orderBy('o.createdAt', 'DESC')
            ->setMaxResults($limit);

        if ($status !== null && $status !== '') {
            $qb->andWhere('o.status = :status')->setParameter('status', $status);
        }

        return $qb->getQuery()->getResult();
    }
}

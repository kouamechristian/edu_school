<?php

namespace App\Repository;

use App\Entity\Contract;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Contract>
 *
 * @method Contract|null find($id, $lockMode = null, $lockVersion = null)
 * @method Contract|null findOneBy(array $criteria, array $orderBy = null)
 * @method Contract[]    findAll()
 * @method Contract[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ContractRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Contract::class);
    }

    /**
     * Liste des contrats des employés rattachés à un établissement.
     */
    public function findBySchool(int $schoolId, ?string $status = null): array
    {
        $qb = $this->createQueryBuilder('c')
            ->join('c.employee', 'e')
            ->join('e.schools', 's')
            ->andWhere('s.id = :schoolId')
            ->setParameter('schoolId', $schoolId)
            ->orderBy('c.startDate', 'DESC');

        if ($status) {
            $qb->andWhere('c.status = :status')->setParameter('status', $status);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Génère la prochaine référence de contrat (CTR-0001, CTR-0002, ...).
     */
    public function getNextReference(): string
    {
        $count = (int) $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->getQuery()
            ->getSingleScalarResult();

        return sprintf('CTR-%04d', $count + 1);
    }
}

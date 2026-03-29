<?php

namespace App\Repository;

use App\Entity\SchoolYear;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SchoolYear>
 */
class SchoolYearRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SchoolYear::class);
    }

    /**
     * Trouver l'année scolaire en cours (globale)
     */
    public function findCurrent(): ?SchoolYear
    {
        return $this->createQueryBuilder('sy')
            ->andWhere('sy.isCurrent = :current')
            ->setParameter('current', true)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Trouver toutes les années scolaires triées par date
     */
    public function findAllOrdered(): array
    {
        return $this->createQueryBuilder('sy')
            ->orderBy('sy.startDate', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Définir une année comme courante (et désactiver les autres)
     */
    public function setAsCurrent(SchoolYear $schoolYear): void
    {
        // Désactiver toutes les années courantes
        $this->createQueryBuilder('sy')
            ->update()
            ->set('sy.isCurrent', ':false')
            ->setParameter('false', false)
            ->getQuery()
            ->execute();

        // Activer l'année sélectionnée
        $schoolYear->setIsCurrent(true);
        $this->getEntityManager()->flush();
    }

    /**
     * Trouver les années scolaires actives (en cours selon les dates)
     */
    public function findActive(): array
    {
        $now = new \DateTime();
        
        return $this->createQueryBuilder('sy')
            ->andWhere('sy.startDate <= :now')
            ->andWhere('sy.endDate >= :now')
            ->setParameter('now', $now)
            ->orderBy('sy.startDate', 'DESC')
            ->getQuery()
            ->getResult();
    }
}


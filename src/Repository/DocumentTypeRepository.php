<?php

namespace App\Repository;

use App\Entity\DocumentType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<DocumentType>
 */
class DocumentTypeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DocumentType::class);
    }

    /**
     * Trouve les types de documents actifs
     */
    public function findActive(): array
    {
        return $this->createQueryBuilder('d')
            ->where('d.isActive = :isActive')
            ->setParameter('isActive', true)
            ->orderBy('d.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les types de documents requis
     */
    public function findRequired(): array
    {
        return $this->createQueryBuilder('d')
            ->where('d.isRequired = :isRequired')
            ->andWhere('d.isActive = :isActive')
            ->setParameter('isRequired', true)
            ->setParameter('isActive', true)
            ->orderBy('d.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les types de documents optionnels
     */
    public function findOptional(): array
    {
        return $this->createQueryBuilder('d')
            ->where('d.isRequired = :isRequired')
            ->andWhere('d.isActive = :isActive')
            ->setParameter('isRequired', false)
            ->setParameter('isActive', true)
            ->orderBy('d.name', 'ASC')
            ->getQuery()
            ->getResult();
    }
}

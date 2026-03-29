<?php

namespace App\Repository;

use App\Entity\Level;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Level>
 */
class LevelRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Level::class);
    }

    /**
     * Trouver tous les niveaux actifs
     */
    public function findActive(): array
    {
        return $this->createQueryBuilder('l')
            ->andWhere('l.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('l.orderNumber', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouver les niveaux par catégorie
     */
    public function findByCategory(string $category): array
    {
        return $this->createQueryBuilder('l')
            ->andWhere('l.category = :category')
            ->andWhere('l.isActive = :active')
            ->setParameter('category', $category)
            ->setParameter('active', true)
            ->orderBy('l.orderNumber', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouver un niveau par catégorie et ordre
     */
    public function findOneByCategoryAndOrder(string $category, int $order): ?Level
    {
        return $this->createQueryBuilder('l')
            ->andWhere('l.category = :category')
            ->andWhere('l.orderNumber = :order')
            ->setParameter('category', $category)
            ->setParameter('order', $order)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Trouver les niveaux par établissement
     * Retourne UNIQUEMENT les niveaux liés à l'établissement spécifié
     */
    public function findBySchool(?int $schoolId): array
    {
        if (!$schoolId) {
            return []; // Si pas d'établissement, retourner liste vide
        }

        return $this->createQueryBuilder('l')
            ->andWhere('l.isActive = :active')
            ->andWhere('l.school = :school')
            ->setParameter('active', true)
            ->setParameter('school', $schoolId)
            ->orderBy('l.orderNumber', 'ASC')
            ->getQuery()
            ->getResult();
    }
}


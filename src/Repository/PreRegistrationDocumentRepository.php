<?php

namespace App\Repository;

use App\Entity\PreRegistrationDocument;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PreRegistrationDocument>
 */
class PreRegistrationDocumentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PreRegistrationDocument::class);
    }

    /**
     * Trouve les documents par préinscription
     */
    public function findByPreRegistration(int $preRegistrationId): array
    {
        return $this->createQueryBuilder('d')
            ->where('d.preRegistration = :preRegistrationId')
            ->setParameter('preRegistrationId', $preRegistrationId)
            ->orderBy('d.uploadedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les documents non validés
     */
    public function findUnvalidated(): array
    {
        return $this->createQueryBuilder('d')
            ->where('d.isValidated = :isValidated')
            ->setParameter('isValidated', false)
            ->orderBy('d.uploadedAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les documents validés
     */
    public function findValidated(): array
    {
        return $this->createQueryBuilder('d')
            ->where('d.isValidated = :isValidated')
            ->setParameter('isValidated', true)
            ->orderBy('d.validatedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les documents par type
     */
    public function findByDocumentType(int $documentTypeId): array
    {
        return $this->createQueryBuilder('d')
            ->where('d.documentType = :documentTypeId')
            ->setParameter('documentTypeId', $documentTypeId)
            ->orderBy('d.uploadedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Compte les documents par statut de validation
     */
    public function countByValidationStatus(): array
    {
        $result = $this->createQueryBuilder('d')
            ->select('d.isValidated, COUNT(d.id) as count')
            ->groupBy('d.isValidated')
            ->getQuery()
            ->getResult();

        $counts = [
            'validated' => 0,
            'unvalidated' => 0
        ];

        foreach ($result as $row) {
            if ($row['isValidated']) {
                $counts['validated'] = $row['count'];
            } else {
                $counts['unvalidated'] = $row['count'];
            }
        }

        return $counts;
    }

    /**
     * Trouve les documents récents
     */
    public function findRecent(int $limit = 10): array
    {
        return $this->createQueryBuilder('d')
            ->orderBy('d.uploadedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}

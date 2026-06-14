<?php

namespace App\Service;

use Doctrine\ORM\EntityManagerInterface;

/**
 * Génère des matricules internes au format AAAA-NNNNN (ex: 2026-00001),
 * séquentiels par année et par type d'entité.
 *
 * Utilisé pour les élèves (Student) et les préinscriptions (PreRegistration).
 */
class MatriculeGenerator
{
    /**
     * Dernier numéro utilisé par couple (classe, année), pour éviter les
     * collisions lors de la génération de plusieurs matricules d'affilée.
     *
     * @var array<string, int>
     */
    private array $counters = [];

    /**
     * Génère le prochain matricule interne disponible.
     */
    public function generate(EntityManagerInterface $em, string $class, string $field = 'matriculeInterne', ?int $year = null): string
    {
        $year ??= (int) date('Y');
        $key = $class . '|' . $year;

        if (!isset($this->counters[$key])) {
            $this->counters[$key] = $this->findLastNumber($em, $class, $field, $year);
        }

        $this->counters[$key]++;

        return sprintf('%d-%05d', $year, $this->counters[$key]);
    }

    private function findLastNumber(EntityManagerInterface $em, string $class, string $field, int $year): int
    {
        $last = $em->createQueryBuilder()
            ->select('e.' . $field)
            ->from($class, 'e')
            ->where('e.' . $field . ' LIKE :pattern')
            ->setParameter('pattern', $year . '-%')
            ->orderBy('e.' . $field, 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if ($last && preg_match('/-(\d+)$/', (string) $last[$field], $matches)) {
            return (int) $matches[1];
        }

        return 0;
    }
}

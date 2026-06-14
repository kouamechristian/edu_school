<?php

namespace App\Service;

use App\Entity\AbsenceType;
use App\Entity\Classroom;
use App\Entity\Fee;
use App\Entity\Period;
use App\Entity\Room;
use App\Entity\School;
use App\Entity\SchoolGroup;
use App\Entity\Subject;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Génère des codes séquentiels (PREFIXE-0001) pour les entités qui en possèdent un.
 *
 * Utilisé par {@see \App\EventSubscriber\CodeGeneratorSubscriber} (génération
 * automatique à l'enregistrement) et par la commande de régénération des codes
 * existants vides.
 */
class CodeGenerator
{
    /**
     * Préfixe de code par entité.
     */
    public const PREFIXES = [
        School::class => 'ETB',
        SchoolGroup::class => 'GRP',
        Subject::class => 'MAT',
        Period::class => 'PER',
        Room::class => 'SAL',
        AbsenceType::class => 'ABS',
        Classroom::class => 'CLA',
        Fee::class => 'FRS',
    ];

    /**
     * Dernier numéro utilisé par préfixe (cache en mémoire pour éviter les
     * collisions lors de la création/régénération de plusieurs entités).
     *
     * @var array<string, int>
     */
    private array $counters = [];

    /**
     * Retourne le préfixe associé à une classe d'entité, ou null si elle n'a pas de code géré.
     */
    public function getPrefix(string $class): ?string
    {
        return self::PREFIXES[$class] ?? null;
    }

    /**
     * Génère le prochain code disponible pour la classe d'entité donnée.
     */
    public function generate(EntityManagerInterface $em, string $class, string $prefix): string
    {
        if (!isset($this->counters[$prefix])) {
            $this->counters[$prefix] = $this->findLastNumber($em, $class, $prefix);
        }

        $this->counters[$prefix]++;

        return sprintf('%s-%04d', $prefix, $this->counters[$prefix]);
    }

    private function findLastNumber(EntityManagerInterface $em, string $class, string $prefix): int
    {
        $lastCode = $em->createQueryBuilder()
            ->select('e.code')
            ->from($class, 'e')
            ->where('e.code LIKE :prefix')
            ->setParameter('prefix', $prefix . '-%')
            ->orderBy('e.code', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if ($lastCode && preg_match('/(\d+)$/', $lastCode['code'], $matches)) {
            return (int) $matches[1];
        }

        return 0;
    }
}

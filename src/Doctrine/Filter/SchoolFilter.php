<?php

namespace App\Doctrine\Filter;

use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Query\Filter\SQLFilter;

class SchoolFilter extends SQLFilter
{
    public function addFilterConstraint(ClassMetadata $targetEntity, $targetTableAlias): string
    {
        // Liste des entités à filtrer par établissement
        // Note: Le filtrage est maintenant géré manuellement dans les repositories
        // pour plus de flexibilité et éviter les problèmes dans les formulaires
        $filteredEntities = [
            // Ajoutez ici les futures entités si nécessaire
            // Ex: Student, Grade, Attendance, etc.
        ];

        if (!in_array($targetEntity->getName(), $filteredEntities)) {
            return '';
        }

        $schoolId = $this->getParameter('school_id');

        // Si pas d'établissement défini, ne pas filtrer
        if (empty($schoolId)) {
            return '';
        }

        return '';
    }
}


<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Convertit les anciennes valeurs de school.type vers les nouvelles catégories
 * du formulaire :
 *   maternelle, primaire -> PRESCOLAIRE-PRIMAIRE
 *   college, lycee       -> SECONDAIRE GENERAL
 *   universite           -> UNIVERSITE
 */
final class Version20260622000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Migre school.type vers les nouvelles valeurs (PRESCOLAIRE-PRIMAIRE, SECONDAIRE GENERAL, UNIVERSITE)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("UPDATE school SET type = 'PRESCOLAIRE-PRIMAIRE' WHERE type IN ('maternelle', 'primaire')");
        $this->addSql("UPDATE school SET type = 'SECONDAIRE GENERAL' WHERE type IN ('college', 'lycee')");
        $this->addSql("UPDATE school SET type = 'UNIVERSITE' WHERE type = 'universite'");
    }

    public function down(Schema $schema): void
    {
        // Rétablissement best effort : les valeurs fusionnées (PRESCOLAIRE-PRIMAIRE,
        // SECONDAIRE GENERAL) sont mappées vers une seule ancienne valeur.
        $this->addSql("UPDATE school SET type = 'primaire' WHERE type = 'PRESCOLAIRE-PRIMAIRE'");
        $this->addSql("UPDATE school SET type = 'college' WHERE type = 'SECONDAIRE GENERAL'");
        $this->addSql("UPDATE school SET type = 'universite' WHERE type = 'UNIVERSITE'");
    }
}

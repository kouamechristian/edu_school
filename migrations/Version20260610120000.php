<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Ajoute les champs cachet de direction et couleur de fond du badge à la table school.
 */
final class Version20260610120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajout des colonnes cachet_direction et badge_background_color à la table school';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE school ADD cachet_direction VARCHAR(255) DEFAULT NULL, ADD badge_background_color VARCHAR(20) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE school DROP cachet_direction, DROP badge_background_color');
    }
}

<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Ajoute le champ sous_tutelle à la table school.
 */
final class Version20260616000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute le champ sous_tutelle (nom de l\'organisme de tutelle) à la table school.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE school ADD sous_tutelle VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE school DROP sous_tutelle');
    }
}

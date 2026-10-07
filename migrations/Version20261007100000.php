<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Établissement : personnalisation graphique (couleurs, polices, arrondis),
 * stockée en JSON. NULL = thème par défaut.
 */
final class Version20261007100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Établissement : thème graphique personnalisable (school.theme)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE school ADD theme JSON DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE school DROP theme');
    }
}

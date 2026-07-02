<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Bulletin : ajoute la moyenne annuelle et le rang annuel aux lignes.
 */
final class Version20260702010000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute annual_average et annual_rank à bulletin_line (moyenne annuelle).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE bulletin_line ADD annual_average NUMERIC(6, 2) DEFAULT NULL, ADD annual_rank INT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE bulletin_line DROP annual_average, DROP annual_rank');
    }
}

<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Rend la colonne gender de pre_registration optionnelle (nullable).
 */
final class Version20260610160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Rend pre_registration.gender nullable';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE pre_registration CHANGE gender gender VARCHAR(1) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE pre_registration CHANGE gender gender VARCHAR(1) NOT NULL');
    }
}

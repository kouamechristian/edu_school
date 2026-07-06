<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260706223002 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Rend la date de naissance de la préinscription optionnelle (nullable).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE pre_registration CHANGE date_of_birth date_of_birth DATE DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE pre_registration CHANGE date_of_birth date_of_birth DATE NOT NULL');
    }
}

<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Ajoute les champs RH au contrat : nombre d'enfants, situation familiale,
 * déclaré, enfants majeurs, régime.
 */
final class Version20260701202500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute number_of_children, marital_status, is_declared, adult_children, regime à contract';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE contract ADD number_of_children INT DEFAULT NULL, ADD marital_status VARCHAR(20) DEFAULT NULL, ADD is_declared TINYINT(1) DEFAULT NULL, ADD adult_children INT DEFAULT NULL, ADD regime VARCHAR(100) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE contract DROP number_of_children, DROP marital_status, DROP is_declared, DROP adult_children, DROP regime');
    }
}

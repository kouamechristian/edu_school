<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Clôtures de période comptable : table accounting_period_closure.
 */
final class Version20260701223000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Crée la table accounting_period_closure (clôtures de période).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("CREATE TABLE accounting_period_closure (id INT AUTO_INCREMENT NOT NULL, school_id INT NOT NULL, closed_by_id INT DEFAULT NULL, label VARCHAR(120) NOT NULL, start_date DATE DEFAULT NULL, end_date DATE NOT NULL, total_recette NUMERIC(14, 2) DEFAULT '0.00' NOT NULL, total_depense NUMERIC(14, 2) DEFAULT '0.00' NOT NULL, total_versement NUMERIC(14, 2) DEFAULT '0.00' NOT NULL, net_result NUMERIC(14, 2) DEFAULT '0.00' NOT NULL, cash_balance NUMERIC(14, 2) DEFAULT '0.00' NOT NULL, entry_count INT DEFAULT 0 NOT NULL, notes LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, INDEX IDX_7B93F5EAC32A47EE (school_id), INDEX IDX_7B93F5EAE1FA7797 (closed_by_id), INDEX idx_closure_end_date (end_date), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");
        $this->addSql('ALTER TABLE accounting_period_closure ADD CONSTRAINT FK_7B93F5EAC32A47EE FOREIGN KEY (school_id) REFERENCES school (id)');
        $this->addSql('ALTER TABLE accounting_period_closure ADD CONSTRAINT FK_7B93F5EAE1FA7797 FOREIGN KEY (closed_by_id) REFERENCES `user` (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE accounting_period_closure DROP FOREIGN KEY FK_7B93F5EAC32A47EE');
        $this->addSql('ALTER TABLE accounting_period_closure DROP FOREIGN KEY FK_7B93F5EAE1FA7797');
        $this->addSql('DROP TABLE accounting_period_closure');
    }
}

<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Rapprochement caisse / banque : table bank_reconciliation.
 */
final class Version20260701230000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Crée la table bank_reconciliation (rapprochement caisse/banque).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("CREATE TABLE bank_reconciliation (id INT AUTO_INCREMENT NOT NULL, school_id INT NOT NULL, reconciled_by_id INT DEFAULT NULL, period_from DATE DEFAULT NULL, statement_date DATE NOT NULL, bank_theoretical NUMERIC(14, 2) DEFAULT '0.00' NOT NULL, statement_balance NUMERIC(14, 2) DEFAULT '0.00' NOT NULL, bank_difference NUMERIC(14, 2) DEFAULT '0.00' NOT NULL, cash_theoretical NUMERIC(14, 2) DEFAULT '0.00' NOT NULL, cash_counted NUMERIC(14, 2) DEFAULT NULL, cash_difference NUMERIC(14, 2) DEFAULT NULL, notes LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, INDEX IDX_8B312F22C32A47EE (school_id), INDEX IDX_8B312F22F5E43AE9 (reconciled_by_id), INDEX idx_reconciliation_date (statement_date), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");
        $this->addSql('ALTER TABLE bank_reconciliation ADD CONSTRAINT FK_8B312F22C32A47EE FOREIGN KEY (school_id) REFERENCES school (id)');
        $this->addSql('ALTER TABLE bank_reconciliation ADD CONSTRAINT FK_8B312F22F5E43AE9 FOREIGN KEY (reconciled_by_id) REFERENCES `user` (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE bank_reconciliation DROP FOREIGN KEY FK_8B312F22C32A47EE');
        $this->addSql('ALTER TABLE bank_reconciliation DROP FOREIGN KEY FK_8B312F22F5E43AE9');
        $this->addSql('DROP TABLE bank_reconciliation');
    }
}

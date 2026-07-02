<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Module de comptabilité (livre de caisse enrichi) :
 * plan comptable (accounting_account) et journal comptable (accounting_entry).
 */
final class Version20260701210000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Crée les tables accounting_account et accounting_entry (module comptabilité).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE accounting_account (id INT AUTO_INCREMENT NOT NULL, school_id INT NOT NULL, code VARCHAR(30) NOT NULL, name VARCHAR(120) NOT NULL, type VARCHAR(20) NOT NULL, description LONGTEXT DEFAULT NULL, is_system TINYINT(1) DEFAULT 0 NOT NULL, is_active TINYINT(1) DEFAULT 1 NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, INDEX IDX_44BEAB93C32A47EE (school_id), UNIQUE INDEX uniq_account_school_code (school_id, code), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE accounting_entry (id INT AUTO_INCREMENT NOT NULL, school_id INT NOT NULL, account_id INT DEFAULT NULL, recorded_by_id INT DEFAULT NULL, reference VARCHAR(50) NOT NULL, entry_date DATE NOT NULL, label VARCHAR(200) NOT NULL, type VARCHAR(20) NOT NULL, amount NUMERIC(12, 2) NOT NULL, payment_method VARCHAR(20) DEFAULT NULL, source_type VARCHAR(20) NOT NULL, source_id INT DEFAULT NULL, notes LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_DB6C942AAEA34913 (reference), INDEX IDX_DB6C942AC32A47EE (school_id), INDEX IDX_DB6C942A9B6B5FBA (account_id), INDEX IDX_DB6C942AD05A957B (recorded_by_id), INDEX idx_entry_date (entry_date), INDEX idx_entry_type (type), UNIQUE INDEX uniq_entry_source (source_type, source_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE accounting_account ADD CONSTRAINT FK_44BEAB93C32A47EE FOREIGN KEY (school_id) REFERENCES school (id)');
        $this->addSql('ALTER TABLE accounting_entry ADD CONSTRAINT FK_DB6C942AC32A47EE FOREIGN KEY (school_id) REFERENCES school (id)');
        $this->addSql('ALTER TABLE accounting_entry ADD CONSTRAINT FK_DB6C942A9B6B5FBA FOREIGN KEY (account_id) REFERENCES accounting_account (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE accounting_entry ADD CONSTRAINT FK_DB6C942AD05A957B FOREIGN KEY (recorded_by_id) REFERENCES `user` (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE accounting_entry DROP FOREIGN KEY FK_DB6C942A9B6B5FBA');
        $this->addSql('ALTER TABLE accounting_entry DROP FOREIGN KEY FK_DB6C942AC32A47EE');
        $this->addSql('ALTER TABLE accounting_entry DROP FOREIGN KEY FK_DB6C942AD05A957B');
        $this->addSql('ALTER TABLE accounting_account DROP FOREIGN KEY FK_44BEAB93C32A47EE');
        $this->addSql('DROP TABLE accounting_entry');
        $this->addSql('DROP TABLE accounting_account');
    }
}

<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260621170855 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE depense (id INT AUTO_INCREMENT NOT NULL, cash_register_id INT NOT NULL, school_id INT DEFAULT NULL, recorded_by_id INT DEFAULT NULL, numero VARCHAR(50) NOT NULL, libelle VARCHAR(150) NOT NULL, category VARCHAR(20) NOT NULL, amount NUMERIC(12, 2) NOT NULL, depense_date DATE NOT NULL, payment_method VARCHAR(20) NOT NULL, beneficiary VARCHAR(150) DEFAULT NULL, reference VARCHAR(100) DEFAULT NULL, description LONGTEXT DEFAULT NULL, notes LONGTEXT DEFAULT NULL, status VARCHAR(20) DEFAULT \'confirmée\' NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_34059757F55AE19E (numero), INDEX IDX_34059757A917CC69 (cash_register_id), INDEX IDX_34059757C32A47EE (school_id), INDEX IDX_34059757D05A957B (recorded_by_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE depense ADD CONSTRAINT FK_34059757A917CC69 FOREIGN KEY (cash_register_id) REFERENCES cash_register (id)');
        $this->addSql('ALTER TABLE depense ADD CONSTRAINT FK_34059757C32A47EE FOREIGN KEY (school_id) REFERENCES school (id)');
        $this->addSql('ALTER TABLE depense ADD CONSTRAINT FK_34059757D05A957B FOREIGN KEY (recorded_by_id) REFERENCES `user` (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE depense DROP FOREIGN KEY FK_34059757A917CC69');
        $this->addSql('ALTER TABLE depense DROP FOREIGN KEY FK_34059757C32A47EE');
        $this->addSql('ALTER TABLE depense DROP FOREIGN KEY FK_34059757D05A957B');
        $this->addSql('DROP TABLE depense');
    }
}

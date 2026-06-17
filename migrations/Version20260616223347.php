<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260616223347 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Crée la table contract (contrats des employés - module Ressources Humaines).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE contract (id INT AUTO_INCREMENT NOT NULL, employee_id INT NOT NULL, reference VARCHAR(50) NOT NULL, contract_type VARCHAR(30) NOT NULL, job_title VARCHAR(255) DEFAULT NULL, start_date DATE NOT NULL, end_date DATE DEFAULT NULL, trial_end_date DATE DEFAULT NULL, base_salary NUMERIC(12, 2) DEFAULT NULL, weekly_hours NUMERIC(5, 2) DEFAULT NULL, status VARCHAR(20) NOT NULL, notes LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_E98F2859AEA34913 (reference), INDEX IDX_E98F28598C03F15C (employee_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE contract ADD CONSTRAINT FK_E98F28598C03F15C FOREIGN KEY (employee_id) REFERENCES employee (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE contract DROP FOREIGN KEY FK_E98F28598C03F15C');
        $this->addSql('DROP TABLE contract');
    }
}

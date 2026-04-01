<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260330151733 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE cash_register (id INT AUTO_INCREMENT NOT NULL, school_id INT NOT NULL, cashier_id INT NOT NULL, status VARCHAR(20) NOT NULL, opening_balance NUMERIC(10, 2) NOT NULL, closing_balance NUMERIC(10, 2) DEFAULT NULL, opened_at DATETIME NOT NULL, closed_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, INDEX IDX_3D7AB1D9C32A47EE (school_id), INDEX IDX_3D7AB1D92EDB0489 (cashier_id), INDEX idx_cash_register_status (status), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE cash_register ADD CONSTRAINT FK_3D7AB1D9C32A47EE FOREIGN KEY (school_id) REFERENCES school (id)');
        $this->addSql('ALTER TABLE cash_register ADD CONSTRAINT FK_3D7AB1D92EDB0489 FOREIGN KEY (cashier_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE fee CHANGE category category VARCHAR(20) NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE cash_register DROP FOREIGN KEY FK_3D7AB1D9C32A47EE');
        $this->addSql('ALTER TABLE cash_register DROP FOREIGN KEY FK_3D7AB1D92EDB0489');
        $this->addSql('DROP TABLE cash_register');
        $this->addSql('ALTER TABLE fee CHANGE category category VARCHAR(20) DEFAULT \'scolarite\' NOT NULL');
    }
}

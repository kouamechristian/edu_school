<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260610234134 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE cash_deposit (id INT AUTO_INCREMENT NOT NULL, cash_register_id INT NOT NULL, recorded_by_id INT DEFAULT NULL, reference VARCHAR(100) NOT NULL, amount NUMERIC(12, 2) NOT NULL, deposit_date DATETIME NOT NULL, notes LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, INDEX IDX_CB378F65A917CC69 (cash_register_id), INDEX IDX_CB378F65D05A957B (recorded_by_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE cash_deposit ADD CONSTRAINT FK_CB378F65A917CC69 FOREIGN KEY (cash_register_id) REFERENCES cash_register (id)');
        $this->addSql('ALTER TABLE cash_deposit ADD CONSTRAINT FK_CB378F65D05A957B FOREIGN KEY (recorded_by_id) REFERENCES `user` (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE cash_deposit DROP FOREIGN KEY FK_CB378F65A917CC69');
        $this->addSql('ALTER TABLE cash_deposit DROP FOREIGN KEY FK_CB378F65D05A957B');
        $this->addSql('DROP TABLE cash_deposit');
    }
}

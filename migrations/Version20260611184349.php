<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260611184349 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE equivalent_subject (id INT AUTO_INCREMENT NOT NULL, parent_subject_id INT NOT NULL, code VARCHAR(50) NOT NULL, order_number INT NOT NULL, label VARCHAR(150) NOT NULL, establishment_type VARCHAR(50) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_539D19E677153098 (code), INDEX IDX_539D19E6457D58D7 (parent_subject_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE equivalent_subject ADD CONSTRAINT FK_539D19E6457D58D7 FOREIGN KEY (parent_subject_id) REFERENCES subject (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE equivalent_subject DROP FOREIGN KEY FK_539D19E6457D58D7');
        $this->addSql('DROP TABLE equivalent_subject');
    }
}

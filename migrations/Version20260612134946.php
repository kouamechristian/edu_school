<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260612134946 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute la table cycle (libellé) et le lien level.cycle_id (un cycle regroupe plusieurs niveaux).';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE cycle (id INT AUTO_INCREMENT NOT NULL, libelle VARCHAR(100) NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE level ADD cycle_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE level ADD CONSTRAINT FK_9AEACC135EC1162 FOREIGN KEY (cycle_id) REFERENCES cycle (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_9AEACC135EC1162 ON level (cycle_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE level DROP FOREIGN KEY FK_9AEACC135EC1162');
        $this->addSql('DROP TABLE cycle');
        $this->addSql('DROP INDEX IDX_9AEACC135EC1162 ON level');
        $this->addSql('ALTER TABLE level DROP cycle_id');
    }
}

<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260612140406 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute la table faculty (libellé) liée au cycle (un cycle a une ou plusieurs facultés).';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE faculty (id INT AUTO_INCREMENT NOT NULL, cycle_id INT NOT NULL, libelle VARCHAR(100) NOT NULL, INDEX IDX_179660435EC1162 (cycle_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE faculty ADD CONSTRAINT FK_179660435EC1162 FOREIGN KEY (cycle_id) REFERENCES cycle (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE faculty DROP FOREIGN KEY FK_179660435EC1162');
        $this->addSql('DROP TABLE faculty');
    }
}

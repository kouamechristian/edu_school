<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260621190713 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE subject_equivalent (id INT AUTO_INCREMENT NOT NULL, school_id INT NOT NULL, subject_id INT NOT NULL, numero_ordre INT DEFAULT NULL, code VARCHAR(50) NOT NULL, libelle VARCHAR(150) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, INDEX IDX_F8297C09C32A47EE (school_id), INDEX IDX_F8297C0923EDC87 (subject_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE subject_equivalent ADD CONSTRAINT FK_F8297C09C32A47EE FOREIGN KEY (school_id) REFERENCES school (id)');
        $this->addSql('ALTER TABLE subject_equivalent ADD CONSTRAINT FK_F8297C0923EDC87 FOREIGN KEY (subject_id) REFERENCES subject (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE subject_equivalent DROP FOREIGN KEY FK_F8297C09C32A47EE');
        $this->addSql('ALTER TABLE subject_equivalent DROP FOREIGN KEY FK_F8297C0923EDC87');
        $this->addSql('DROP TABLE subject_equivalent');
    }
}

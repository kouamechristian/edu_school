<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260612142425 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute la table round (série) liée au cycle et à l\'établissement, et ajoute faculty.school_id.';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE round (id INT AUTO_INCREMENT NOT NULL, cycle_id INT NOT NULL, school_id INT DEFAULT NULL, libelle VARCHAR(100) NOT NULL, INDEX IDX_C5EEEA345EC1162 (cycle_id), INDEX IDX_C5EEEA34C32A47EE (school_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE round ADD CONSTRAINT FK_C5EEEA345EC1162 FOREIGN KEY (cycle_id) REFERENCES cycle (id)');
        $this->addSql('ALTER TABLE round ADD CONSTRAINT FK_C5EEEA34C32A47EE FOREIGN KEY (school_id) REFERENCES school (id)');
        $this->addSql('ALTER TABLE faculty ADD school_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE faculty ADD CONSTRAINT FK_17966043C32A47EE FOREIGN KEY (school_id) REFERENCES school (id)');
        $this->addSql('CREATE INDEX IDX_17966043C32A47EE ON faculty (school_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE round DROP FOREIGN KEY FK_C5EEEA345EC1162');
        $this->addSql('ALTER TABLE round DROP FOREIGN KEY FK_C5EEEA34C32A47EE');
        $this->addSql('DROP TABLE round');
        $this->addSql('ALTER TABLE faculty DROP FOREIGN KEY FK_17966043C32A47EE');
        $this->addSql('DROP INDEX IDX_17966043C32A47EE ON faculty');
        $this->addSql('ALTER TABLE faculty DROP school_id');
    }
}

<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260718224004 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute le module Exercices de maison (table homework) et le compte de connexion élève (student.student_user_id).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE homework (id INT AUTO_INCREMENT NOT NULL, teacher_id INT DEFAULT NULL, classroom_id INT NOT NULL, subject_id INT DEFAULT NULL, school_id INT NOT NULL, school_year_id INT DEFAULT NULL, title VARCHAR(150) NOT NULL, instructions LONGTEXT DEFAULT NULL, due_date DATE NOT NULL, attachment_path VARCHAR(255) DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, INDEX IDX_8C600B4E41807E1D (teacher_id), INDEX IDX_8C600B4E6278D5A8 (classroom_id), INDEX IDX_8C600B4E23EDC87 (subject_id), INDEX IDX_8C600B4EC32A47EE (school_id), INDEX IDX_8C600B4ED2EECC3F (school_year_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE homework ADD CONSTRAINT FK_8C600B4E41807E1D FOREIGN KEY (teacher_id) REFERENCES `user` (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE homework ADD CONSTRAINT FK_8C600B4E6278D5A8 FOREIGN KEY (classroom_id) REFERENCES classroom (id)');
        $this->addSql('ALTER TABLE homework ADD CONSTRAINT FK_8C600B4E23EDC87 FOREIGN KEY (subject_id) REFERENCES subject (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE homework ADD CONSTRAINT FK_8C600B4EC32A47EE FOREIGN KEY (school_id) REFERENCES school (id)');
        $this->addSql('ALTER TABLE homework ADD CONSTRAINT FK_8C600B4ED2EECC3F FOREIGN KEY (school_year_id) REFERENCES school_year (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE student ADD student_user_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE student ADD CONSTRAINT FK_B723AF334A58666D FOREIGN KEY (student_user_id) REFERENCES `user` (id) ON DELETE SET NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_B723AF334A58666D ON student (student_user_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE homework DROP FOREIGN KEY FK_8C600B4E41807E1D');
        $this->addSql('ALTER TABLE homework DROP FOREIGN KEY FK_8C600B4E6278D5A8');
        $this->addSql('ALTER TABLE homework DROP FOREIGN KEY FK_8C600B4E23EDC87');
        $this->addSql('ALTER TABLE homework DROP FOREIGN KEY FK_8C600B4EC32A47EE');
        $this->addSql('ALTER TABLE homework DROP FOREIGN KEY FK_8C600B4ED2EECC3F');
        $this->addSql('DROP TABLE homework');
        $this->addSql('ALTER TABLE student DROP FOREIGN KEY FK_B723AF334A58666D');
        $this->addSql('DROP INDEX UNIQ_B723AF334A58666D ON student');
        $this->addSql('ALTER TABLE student DROP student_user_id');
    }
}

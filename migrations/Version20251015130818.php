<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251015130818 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE absence (id INT AUTO_INCREMENT NOT NULL, student_id INT NOT NULL, absence_type_id INT NOT NULL, recorded_by_id INT DEFAULT NULL, justified_by_id INT DEFAULT NULL, school_id INT NOT NULL, period_id INT DEFAULT NULL, date DATE NOT NULL, start_time TIME DEFAULT NULL, end_time TIME DEFAULT NULL, reason LONGTEXT DEFAULT NULL, justification LONGTEXT DEFAULT NULL, justification_status VARCHAR(50) DEFAULT NULL, justification_document VARCHAR(255) DEFAULT NULL, justification_date DATETIME DEFAULT NULL, justification_submitted_at DATETIME DEFAULT NULL, notes LONGTEXT DEFAULT NULL, is_active TINYINT(1) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, INDEX IDX_765AE0C9CB944F1A (student_id), INDEX IDX_765AE0C9CCAA91B (absence_type_id), INDEX IDX_765AE0C9D05A957B (recorded_by_id), INDEX IDX_765AE0C9CD130C9C (justified_by_id), INDEX IDX_765AE0C9C32A47EE (school_id), INDEX IDX_765AE0C9EC8B7ADE (period_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE absence_type (id INT AUTO_INCREMENT NOT NULL, school_id INT NOT NULL, name VARCHAR(100) NOT NULL, description VARCHAR(255) DEFAULT NULL, code VARCHAR(20) NOT NULL, type VARCHAR(20) NOT NULL, requires_justification TINYINT(1) NOT NULL, counts_as_absence TINYINT(1) NOT NULL, penalty_points NUMERIC(5, 2) DEFAULT NULL, color VARCHAR(7) DEFAULT NULL, is_active TINYINT(1) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, INDEX IDX_FBCF99B6C32A47EE (school_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE employee (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, first_name VARCHAR(100) NOT NULL, last_name VARCHAR(100) NOT NULL, phone VARCHAR(20) DEFAULT NULL, address LONGTEXT DEFAULT NULL, date_of_birth DATE DEFAULT NULL, gender VARCHAR(1) DEFAULT NULL, employee_type VARCHAR(50) NOT NULL, position VARCHAR(255) DEFAULT NULL, department VARCHAR(255) DEFAULT NULL, salary NUMERIC(10, 2) DEFAULT NULL, hire_date DATE DEFAULT NULL, termination_date DATE DEFAULT NULL, is_active TINYINT(1) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_5D9F75A1A76ED395 (user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE employee_school (employee_id INT NOT NULL, school_id INT NOT NULL, INDEX IDX_D0AF1E2E8C03F15C (employee_id), INDEX IDX_D0AF1E2EC32A47EE (school_id), PRIMARY KEY(employee_id, school_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE student (id INT AUTO_INCREMENT NOT NULL, school_id INT NOT NULL, level_id INT DEFAULT NULL, classroom_id INT DEFAULT NULL, school_year_id INT DEFAULT NULL, first_name VARCHAR(100) NOT NULL, last_name VARCHAR(100) NOT NULL, phone VARCHAR(20) DEFAULT NULL, address LONGTEXT DEFAULT NULL, date_of_birth DATE DEFAULT NULL, gender VARCHAR(1) DEFAULT NULL, student_number VARCHAR(50) DEFAULT NULL, parent_name VARCHAR(255) DEFAULT NULL, parent_phone VARCHAR(20) DEFAULT NULL, parent_email VARCHAR(255) DEFAULT NULL, emergency_contact VARCHAR(255) DEFAULT NULL, emergency_phone VARCHAR(20) DEFAULT NULL, medical_info VARCHAR(255) DEFAULT NULL, notes LONGTEXT DEFAULT NULL, is_active TINYINT(1) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, INDEX IDX_B723AF33C32A47EE (school_id), INDEX IDX_B723AF335FB14BA7 (level_id), INDEX IDX_B723AF336278D5A8 (classroom_id), INDEX IDX_B723AF33D2EECC3F (school_year_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE teacher (id INT AUTO_INCREMENT NOT NULL, employee_id INT NOT NULL, specialization VARCHAR(255) DEFAULT NULL, education LONGTEXT DEFAULT NULL, experience LONGTEXT DEFAULT NULL, certification VARCHAR(255) DEFAULT NULL, teaching_hours NUMERIC(5, 2) DEFAULT NULL, is_class_teacher TINYINT(1) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_B0F6A6D58C03F15C (employee_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE teacher_subject (teacher_id INT NOT NULL, subject_id INT NOT NULL, INDEX IDX_360CB33B41807E1D (teacher_id), INDEX IDX_360CB33B23EDC87 (subject_id), PRIMARY KEY(teacher_id, subject_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE teacher_level (teacher_id INT NOT NULL, level_id INT NOT NULL, INDEX IDX_CB15704E41807E1D (teacher_id), INDEX IDX_CB15704E5FB14BA7 (level_id), PRIMARY KEY(teacher_id, level_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE absence ADD CONSTRAINT FK_765AE0C9CB944F1A FOREIGN KEY (student_id) REFERENCES student (id)');
        $this->addSql('ALTER TABLE absence ADD CONSTRAINT FK_765AE0C9CCAA91B FOREIGN KEY (absence_type_id) REFERENCES absence_type (id)');
        $this->addSql('ALTER TABLE absence ADD CONSTRAINT FK_765AE0C9D05A957B FOREIGN KEY (recorded_by_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE absence ADD CONSTRAINT FK_765AE0C9CD130C9C FOREIGN KEY (justified_by_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE absence ADD CONSTRAINT FK_765AE0C9C32A47EE FOREIGN KEY (school_id) REFERENCES school (id)');
        $this->addSql('ALTER TABLE absence ADD CONSTRAINT FK_765AE0C9EC8B7ADE FOREIGN KEY (period_id) REFERENCES period (id)');
        $this->addSql('ALTER TABLE absence_type ADD CONSTRAINT FK_FBCF99B6C32A47EE FOREIGN KEY (school_id) REFERENCES school (id)');
        $this->addSql('ALTER TABLE employee ADD CONSTRAINT FK_5D9F75A1A76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE employee_school ADD CONSTRAINT FK_D0AF1E2E8C03F15C FOREIGN KEY (employee_id) REFERENCES employee (id)');
        $this->addSql('ALTER TABLE employee_school ADD CONSTRAINT FK_D0AF1E2EC32A47EE FOREIGN KEY (school_id) REFERENCES school (id)');
        $this->addSql('ALTER TABLE student ADD CONSTRAINT FK_B723AF33C32A47EE FOREIGN KEY (school_id) REFERENCES school (id)');
        $this->addSql('ALTER TABLE student ADD CONSTRAINT FK_B723AF335FB14BA7 FOREIGN KEY (level_id) REFERENCES level (id)');
        $this->addSql('ALTER TABLE student ADD CONSTRAINT FK_B723AF336278D5A8 FOREIGN KEY (classroom_id) REFERENCES classroom (id)');
        $this->addSql('ALTER TABLE student ADD CONSTRAINT FK_B723AF33D2EECC3F FOREIGN KEY (school_year_id) REFERENCES school_year (id)');
        $this->addSql('ALTER TABLE teacher ADD CONSTRAINT FK_B0F6A6D58C03F15C FOREIGN KEY (employee_id) REFERENCES employee (id)');
        $this->addSql('ALTER TABLE teacher_subject ADD CONSTRAINT FK_360CB33B41807E1D FOREIGN KEY (teacher_id) REFERENCES teacher (id)');
        $this->addSql('ALTER TABLE teacher_subject ADD CONSTRAINT FK_360CB33B23EDC87 FOREIGN KEY (subject_id) REFERENCES subject (id)');
        $this->addSql('ALTER TABLE teacher_level ADD CONSTRAINT FK_CB15704E41807E1D FOREIGN KEY (teacher_id) REFERENCES teacher (id)');
        $this->addSql('ALTER TABLE teacher_level ADD CONSTRAINT FK_CB15704E5FB14BA7 FOREIGN KEY (level_id) REFERENCES level (id)');
        $this->addSql('ALTER TABLE period ADD CONSTRAINT FK_C5B81ECEC32A47EE FOREIGN KEY (school_id) REFERENCES school (id)');
        $this->addSql('CREATE INDEX IDX_C5B81ECEC32A47EE ON period (school_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE absence DROP FOREIGN KEY FK_765AE0C9CB944F1A');
        $this->addSql('ALTER TABLE absence DROP FOREIGN KEY FK_765AE0C9CCAA91B');
        $this->addSql('ALTER TABLE absence DROP FOREIGN KEY FK_765AE0C9D05A957B');
        $this->addSql('ALTER TABLE absence DROP FOREIGN KEY FK_765AE0C9CD130C9C');
        $this->addSql('ALTER TABLE absence DROP FOREIGN KEY FK_765AE0C9C32A47EE');
        $this->addSql('ALTER TABLE absence DROP FOREIGN KEY FK_765AE0C9EC8B7ADE');
        $this->addSql('ALTER TABLE absence_type DROP FOREIGN KEY FK_FBCF99B6C32A47EE');
        $this->addSql('ALTER TABLE employee DROP FOREIGN KEY FK_5D9F75A1A76ED395');
        $this->addSql('ALTER TABLE employee_school DROP FOREIGN KEY FK_D0AF1E2E8C03F15C');
        $this->addSql('ALTER TABLE employee_school DROP FOREIGN KEY FK_D0AF1E2EC32A47EE');
        $this->addSql('ALTER TABLE student DROP FOREIGN KEY FK_B723AF33C32A47EE');
        $this->addSql('ALTER TABLE student DROP FOREIGN KEY FK_B723AF335FB14BA7');
        $this->addSql('ALTER TABLE student DROP FOREIGN KEY FK_B723AF336278D5A8');
        $this->addSql('ALTER TABLE student DROP FOREIGN KEY FK_B723AF33D2EECC3F');
        $this->addSql('ALTER TABLE teacher DROP FOREIGN KEY FK_B0F6A6D58C03F15C');
        $this->addSql('ALTER TABLE teacher_subject DROP FOREIGN KEY FK_360CB33B41807E1D');
        $this->addSql('ALTER TABLE teacher_subject DROP FOREIGN KEY FK_360CB33B23EDC87');
        $this->addSql('ALTER TABLE teacher_level DROP FOREIGN KEY FK_CB15704E41807E1D');
        $this->addSql('ALTER TABLE teacher_level DROP FOREIGN KEY FK_CB15704E5FB14BA7');
        $this->addSql('DROP TABLE absence');
        $this->addSql('DROP TABLE absence_type');
        $this->addSql('DROP TABLE employee');
        $this->addSql('DROP TABLE employee_school');
        $this->addSql('DROP TABLE student');
        $this->addSql('DROP TABLE teacher');
        $this->addSql('DROP TABLE teacher_subject');
        $this->addSql('DROP TABLE teacher_level');
        $this->addSql('ALTER TABLE period DROP FOREIGN KEY FK_C5B81ECEC32A47EE');
        $this->addSql('DROP INDEX IDX_C5B81ECEC32A47EE ON period');
    }
}

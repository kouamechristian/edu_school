<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260326212404 extends AbstractMigration
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
        $this->addSql('CREATE TABLE classroom (id INT AUTO_INCREMENT NOT NULL, school_id INT NOT NULL, school_year_id INT NOT NULL, level_id INT NOT NULL, main_teacher_id INT DEFAULT NULL, room_id INT DEFAULT NULL, name VARCHAR(100) NOT NULL, code VARCHAR(50) NOT NULL, capacity INT DEFAULT NULL, description LONGTEXT DEFAULT NULL, is_active TINYINT(1) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_497D309D77153098 (code), INDEX IDX_497D309DC32A47EE (school_id), INDEX IDX_497D309DD2EECC3F (school_year_id), INDEX IDX_497D309D5FB14BA7 (level_id), INDEX IDX_497D309D79780A7E (main_teacher_id), INDEX IDX_497D309D54177093 (room_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE course (id INT AUTO_INCREMENT NOT NULL, classroom_id INT NOT NULL, subject_id INT NOT NULL, teacher_id INT DEFAULT NULL, time_slot_id INT NOT NULL, room_id INT DEFAULT NULL, day_of_week VARCHAR(20) NOT NULL, notes LONGTEXT DEFAULT NULL, is_active TINYINT(1) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, INDEX IDX_169E6FB96278D5A8 (classroom_id), INDEX IDX_169E6FB923EDC87 (subject_id), INDEX IDX_169E6FB941807E1D (teacher_id), INDEX IDX_169E6FB9D62B0FA (time_slot_id), INDEX IDX_169E6FB954177093 (room_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE document_type (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(100) NOT NULL, description LONGTEXT DEFAULT NULL, is_required TINYINT(1) NOT NULL, is_active TINYINT(1) NOT NULL, allowed_mime_types JSON NOT NULL COMMENT \'(DC2Type:json)\', max_file_size INT NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE employee (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, first_name VARCHAR(100) NOT NULL, last_name VARCHAR(100) NOT NULL, phone VARCHAR(20) DEFAULT NULL, address LONGTEXT DEFAULT NULL, date_of_birth DATE DEFAULT NULL, gender VARCHAR(1) DEFAULT NULL, employee_type VARCHAR(50) NOT NULL, position VARCHAR(255) DEFAULT NULL, department VARCHAR(255) DEFAULT NULL, salary NUMERIC(10, 2) DEFAULT NULL, hire_date DATE DEFAULT NULL, termination_date DATE DEFAULT NULL, is_active TINYINT(1) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_5D9F75A1A76ED395 (user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE employee_school (employee_id INT NOT NULL, school_id INT NOT NULL, INDEX IDX_D0AF1E2E8C03F15C (employee_id), INDEX IDX_D0AF1E2EC32A47EE (school_id), PRIMARY KEY(employee_id, school_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE evaluation (id INT AUTO_INCREMENT NOT NULL, classroom_id INT NOT NULL, subject_id INT NOT NULL, period_id INT NOT NULL, teacher_id INT DEFAULT NULL, name VARCHAR(150) NOT NULL, type VARCHAR(50) NOT NULL, date DATE NOT NULL, max_grade NUMERIC(5, 2) NOT NULL, coefficient NUMERIC(5, 2) NOT NULL, description LONGTEXT DEFAULT NULL, is_published TINYINT(1) NOT NULL, is_active TINYINT(1) NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_1323A5756278D5A8 (classroom_id), INDEX IDX_1323A57523EDC87 (subject_id), INDEX IDX_1323A575EC8B7ADE (period_id), INDEX IDX_1323A57541807E1D (teacher_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE fee (id INT AUTO_INCREMENT NOT NULL, school_id INT NOT NULL, level_id INT DEFAULT NULL, name VARCHAR(100) NOT NULL, code VARCHAR(50) NOT NULL, amount NUMERIC(10, 2) NOT NULL, description LONGTEXT DEFAULT NULL, type VARCHAR(20) NOT NULL, frequency VARCHAR(20) NOT NULL, due_date DATE DEFAULT NULL, start_date DATE DEFAULT NULL, end_date DATE DEFAULT NULL, discount_percentage NUMERIC(5, 2) DEFAULT NULL, discount_amount NUMERIC(10, 2) DEFAULT NULL, is_active TINYINT(1) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_964964B577153098 (code), INDEX IDX_964964B5C32A47EE (school_id), INDEX IDX_964964B55FB14BA7 (level_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE financial_transaction (id INT AUTO_INCREMENT NOT NULL, student_id INT DEFAULT NULL, payment_id INT DEFAULT NULL, invoice_id INT DEFAULT NULL, scholarship_id INT DEFAULT NULL, recorded_by_id INT DEFAULT NULL, transaction_number VARCHAR(50) NOT NULL, type VARCHAR(20) NOT NULL, category VARCHAR(20) NOT NULL, amount NUMERIC(10, 2) NOT NULL, transaction_date DATE NOT NULL, description LONGTEXT DEFAULT NULL, payment_method VARCHAR(20) NOT NULL, status VARCHAR(20) NOT NULL, reference VARCHAR(100) DEFAULT NULL, notes LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_3000FF4DE0ED6D14 (transaction_number), INDEX IDX_3000FF4DCB944F1A (student_id), INDEX IDX_3000FF4D4C3A3BB (payment_id), INDEX IDX_3000FF4D2989F1FD (invoice_id), INDEX IDX_3000FF4D28722836 (scholarship_id), INDEX IDX_3000FF4DD05A957B (recorded_by_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE `grade` (id INT AUTO_INCREMENT NOT NULL, evaluation_id INT NOT NULL, student_id INT NOT NULL, entered_by_id INT DEFAULT NULL, value NUMERIC(5, 2) DEFAULT NULL, status VARCHAR(20) DEFAULT NULL, comment LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_595AAE34456C5646 (evaluation_id), INDEX IDX_595AAE34CB944F1A (student_id), INDEX IDX_595AAE34C443EDD (entered_by_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE invoice (id INT AUTO_INCREMENT NOT NULL, student_id INT NOT NULL, fee_id INT NOT NULL, created_by_id INT DEFAULT NULL, invoice_number VARCHAR(50) NOT NULL, total_amount NUMERIC(10, 2) NOT NULL, paid_amount NUMERIC(10, 2) DEFAULT NULL, remaining_amount NUMERIC(10, 2) DEFAULT NULL, issue_date DATE NOT NULL, due_date DATE NOT NULL, status VARCHAR(20) NOT NULL, notes LONGTEXT DEFAULT NULL, discount_percentage NUMERIC(5, 2) DEFAULT NULL, discount_amount NUMERIC(10, 2) DEFAULT NULL, tax_amount NUMERIC(10, 2) DEFAULT NULL, pdf_path VARCHAR(255) DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_906517442DA68207 (invoice_number), INDEX IDX_90651744CB944F1A (student_id), INDEX IDX_90651744AB45AECA (fee_id), INDEX IDX_90651744B03A8386 (created_by_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE level (id INT AUTO_INCREMENT NOT NULL, school_id INT DEFAULT NULL, name VARCHAR(100) NOT NULL, category VARCHAR(20) NOT NULL, order_number INT NOT NULL, description LONGTEXT DEFAULT NULL, is_active TINYINT(1) NOT NULL, INDEX IDX_9AEACC13C32A47EE (school_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE payment (id INT AUTO_INCREMENT NOT NULL, student_id INT NOT NULL, fee_id INT NOT NULL, recorded_by_id INT DEFAULT NULL, payment_number VARCHAR(50) NOT NULL, amount NUMERIC(10, 2) NOT NULL, payment_date DATE NOT NULL, payment_method VARCHAR(20) NOT NULL, status VARCHAR(20) NOT NULL, notes LONGTEXT DEFAULT NULL, reference VARCHAR(100) DEFAULT NULL, receipt_path VARCHAR(255) DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_6D28840DB3A884C2 (payment_number), INDEX IDX_6D28840DCB944F1A (student_id), INDEX IDX_6D28840DAB45AECA (fee_id), INDEX IDX_6D28840DD05A957B (recorded_by_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE payment_plan (id INT AUTO_INCREMENT NOT NULL, student_id INT NOT NULL, fee_id INT NOT NULL, created_by_id INT DEFAULT NULL, name VARCHAR(100) NOT NULL, total_amount NUMERIC(10, 2) NOT NULL, paid_amount NUMERIC(10, 2) DEFAULT NULL, remaining_amount NUMERIC(10, 2) DEFAULT NULL, start_date DATE NOT NULL, end_date DATE NOT NULL, installment_count INT DEFAULT NULL, installment_amount NUMERIC(10, 2) DEFAULT NULL, frequency VARCHAR(20) NOT NULL, status VARCHAR(20) NOT NULL, notes LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, INDEX IDX_FCD9CC09CB944F1A (student_id), INDEX IDX_FCD9CC09AB45AECA (fee_id), INDEX IDX_FCD9CC09B03A8386 (created_by_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE period (id INT AUTO_INCREMENT NOT NULL, school_id INT NOT NULL, school_year_id INT NOT NULL, name VARCHAR(100) NOT NULL, code VARCHAR(50) NOT NULL, order_number INT NOT NULL, start_date DATE NOT NULL, end_date DATE NOT NULL, is_active TINYINT(1) NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_C5B81ECEC32A47EE (school_id), INDEX IDX_C5B81ECED2EECC3F (school_year_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE pre_registration (id INT AUTO_INCREMENT NOT NULL, school_id INT NOT NULL, requested_level_id INT DEFAULT NULL, school_year_id INT DEFAULT NULL, validated_by_id INT DEFAULT NULL, first_name VARCHAR(100) NOT NULL, last_name VARCHAR(100) NOT NULL, date_of_birth DATE NOT NULL, gender VARCHAR(1) NOT NULL, phone VARCHAR(20) DEFAULT NULL, email VARCHAR(255) DEFAULT NULL, address LONGTEXT DEFAULT NULL, parent_name VARCHAR(255) DEFAULT NULL, parent_phone VARCHAR(20) DEFAULT NULL, parent_email VARCHAR(255) DEFAULT NULL, emergency_contact VARCHAR(255) DEFAULT NULL, emergency_phone VARCHAR(20) DEFAULT NULL, medical_info VARCHAR(255) DEFAULT NULL, notes LONGTEXT DEFAULT NULL, status VARCHAR(50) NOT NULL, rejection_reason LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, validated_at DATETIME DEFAULT NULL, enrolled_at DATETIME DEFAULT NULL, INDEX IDX_A2FEF1B9C32A47EE (school_id), INDEX IDX_A2FEF1B92EC20237 (requested_level_id), INDEX IDX_A2FEF1B9D2EECC3F (school_year_id), INDEX IDX_A2FEF1B9C69DE5E5 (validated_by_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE pre_registration_document (id INT AUTO_INCREMENT NOT NULL, pre_registration_id INT NOT NULL, document_type_id INT NOT NULL, validated_by_id INT DEFAULT NULL, file_name VARCHAR(255) NOT NULL, original_file_name VARCHAR(255) NOT NULL, mime_type VARCHAR(100) NOT NULL, file_size INT NOT NULL, file_path VARCHAR(500) NOT NULL, description LONGTEXT DEFAULT NULL, is_validated TINYINT(1) NOT NULL, validation_notes LONGTEXT DEFAULT NULL, uploaded_at DATETIME NOT NULL, validated_at DATETIME DEFAULT NULL, INDEX IDX_413A5C65FB6C0FEA (pre_registration_id), INDEX IDX_413A5C6561232A4F (document_type_id), INDEX IDX_413A5C65C69DE5E5 (validated_by_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE room (id INT AUTO_INCREMENT NOT NULL, school_id INT NOT NULL, name VARCHAR(100) NOT NULL, code VARCHAR(50) NOT NULL, capacity INT DEFAULT NULL, type VARCHAR(50) DEFAULT NULL, floor VARCHAR(50) DEFAULT NULL, building VARCHAR(50) DEFAULT NULL, equipment LONGTEXT DEFAULT NULL, description LONGTEXT DEFAULT NULL, is_active TINYINT(1) NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_729F519BC32A47EE (school_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE scholarship (id INT AUTO_INCREMENT NOT NULL, student_id INT NOT NULL, granted_by_id INT DEFAULT NULL, name VARCHAR(100) NOT NULL, amount NUMERIC(10, 2) DEFAULT NULL, percentage NUMERIC(5, 2) DEFAULT NULL, type VARCHAR(20) NOT NULL, status VARCHAR(20) NOT NULL, start_date DATE NOT NULL, end_date DATE DEFAULT NULL, description LONGTEXT DEFAULT NULL, conditions LONGTEXT DEFAULT NULL, sponsor VARCHAR(255) DEFAULT NULL, notes LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, INDEX IDX_F3FD63FCB944F1A (student_id), INDEX IDX_F3FD63F3151C11F (granted_by_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE school (id INT AUTO_INCREMENT NOT NULL, school_group_id INT DEFAULT NULL, name VARCHAR(255) NOT NULL, code VARCHAR(50) NOT NULL, type VARCHAR(50) NOT NULL, address LONGTEXT DEFAULT NULL, phone VARCHAR(20) DEFAULT NULL, email VARCHAR(100) DEFAULT NULL, director VARCHAR(100) DEFAULT NULL, logo VARCHAR(255) DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, is_active TINYINT(1) NOT NULL, UNIQUE INDEX UNIQ_F99EDABB77153098 (code), INDEX IDX_F99EDABB12ED03 (school_group_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE school_group (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, code VARCHAR(50) NOT NULL, description LONGTEXT DEFAULT NULL, is_active TINYINT(1) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_B33AF2DF77153098 (code), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE school_year (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(50) NOT NULL, start_date DATE NOT NULL, end_date DATE NOT NULL, is_current TINYINT(1) NOT NULL, created_at DATETIME NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE student (id INT AUTO_INCREMENT NOT NULL, school_id INT NOT NULL, level_id INT DEFAULT NULL, classroom_id INT DEFAULT NULL, school_year_id INT DEFAULT NULL, pre_registration_id INT DEFAULT NULL, first_name VARCHAR(100) NOT NULL, last_name VARCHAR(100) NOT NULL, phone VARCHAR(20) DEFAULT NULL, email VARCHAR(255) DEFAULT NULL, address LONGTEXT DEFAULT NULL, date_of_birth DATE DEFAULT NULL, gender VARCHAR(1) DEFAULT NULL, student_number VARCHAR(50) DEFAULT NULL, parent_name VARCHAR(255) DEFAULT NULL, parent_phone VARCHAR(20) DEFAULT NULL, parent_email VARCHAR(255) DEFAULT NULL, emergency_contact VARCHAR(255) DEFAULT NULL, emergency_phone VARCHAR(20) DEFAULT NULL, medical_info VARCHAR(255) DEFAULT NULL, notes LONGTEXT DEFAULT NULL, is_active TINYINT(1) NOT NULL, status VARCHAR(20) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, INDEX IDX_B723AF33C32A47EE (school_id), INDEX IDX_B723AF335FB14BA7 (level_id), INDEX IDX_B723AF336278D5A8 (classroom_id), INDEX IDX_B723AF33D2EECC3F (school_year_id), UNIQUE INDEX UNIQ_B723AF33FB6C0FEA (pre_registration_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE subject (id INT AUTO_INCREMENT NOT NULL, school_id INT DEFAULT NULL, level_id INT DEFAULT NULL, name VARCHAR(100) NOT NULL, code VARCHAR(50) NOT NULL, coefficient NUMERIC(5, 2) DEFAULT NULL, description LONGTEXT DEFAULT NULL, type VARCHAR(50) DEFAULT NULL, hours_per_week INT DEFAULT NULL, color VARCHAR(7) DEFAULT NULL, is_active TINYINT(1) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_FBCE3E7A77153098 (code), INDEX IDX_FBCE3E7AC32A47EE (school_id), INDEX IDX_FBCE3E7A5FB14BA7 (level_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE teacher (id INT AUTO_INCREMENT NOT NULL, employee_id INT NOT NULL, specialization VARCHAR(255) DEFAULT NULL, education LONGTEXT DEFAULT NULL, experience LONGTEXT DEFAULT NULL, certification VARCHAR(255) DEFAULT NULL, teaching_hours NUMERIC(5, 2) DEFAULT NULL, is_class_teacher TINYINT(1) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_B0F6A6D58C03F15C (employee_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE teacher_subject (teacher_id INT NOT NULL, subject_id INT NOT NULL, INDEX IDX_360CB33B41807E1D (teacher_id), INDEX IDX_360CB33B23EDC87 (subject_id), PRIMARY KEY(teacher_id, subject_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE teacher_level (teacher_id INT NOT NULL, level_id INT NOT NULL, INDEX IDX_CB15704E41807E1D (teacher_id), INDEX IDX_CB15704E5FB14BA7 (level_id), PRIMARY KEY(teacher_id, level_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE time_slot (id INT AUTO_INCREMENT NOT NULL, school_id INT NOT NULL, name VARCHAR(100) NOT NULL, start_time TIME NOT NULL, end_time TIME NOT NULL, type VARCHAR(20) NOT NULL, order_number INT NOT NULL, color VARCHAR(50) DEFAULT NULL, is_active TINYINT(1) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, INDEX IDX_1B3294AC32A47EE (school_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE `user` (id INT AUTO_INCREMENT NOT NULL, school_group_id INT DEFAULT NULL, username VARCHAR(180) NOT NULL, email VARCHAR(180) NOT NULL, roles JSON NOT NULL COMMENT \'(DC2Type:json)\', password VARCHAR(255) NOT NULL, is_active TINYINT(1) NOT NULL, last_login DATETIME DEFAULT NULL, avatar VARCHAR(255) DEFAULT NULL, first_name VARCHAR(100) DEFAULT NULL, last_name VARCHAR(100) DEFAULT NULL, phone VARCHAR(20) DEFAULT NULL, address LONGTEXT DEFAULT NULL, date_of_birth DATE DEFAULT NULL, gender VARCHAR(1) DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, user_type VARCHAR(50) DEFAULT NULL, UNIQUE INDEX UNIQ_8D93D649F85E0677 (username), UNIQUE INDEX UNIQ_8D93D649E7927C74 (email), INDEX IDX_8D93D64912ED03 (school_group_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE user_school (user_id INT NOT NULL, school_id INT NOT NULL, INDEX IDX_9CCCC186A76ED395 (user_id), INDEX IDX_9CCCC186C32A47EE (school_id), PRIMARY KEY(user_id, school_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE messenger_messages (id BIGINT AUTO_INCREMENT NOT NULL, body LONGTEXT NOT NULL, headers LONGTEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', available_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', delivered_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_75EA56E0FB7336F0 (queue_name), INDEX IDX_75EA56E0E3BD61CE (available_at), INDEX IDX_75EA56E016BA31DB (delivered_at), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE absence ADD CONSTRAINT FK_765AE0C9CB944F1A FOREIGN KEY (student_id) REFERENCES student (id)');
        $this->addSql('ALTER TABLE absence ADD CONSTRAINT FK_765AE0C9CCAA91B FOREIGN KEY (absence_type_id) REFERENCES absence_type (id)');
        $this->addSql('ALTER TABLE absence ADD CONSTRAINT FK_765AE0C9D05A957B FOREIGN KEY (recorded_by_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE absence ADD CONSTRAINT FK_765AE0C9CD130C9C FOREIGN KEY (justified_by_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE absence ADD CONSTRAINT FK_765AE0C9C32A47EE FOREIGN KEY (school_id) REFERENCES school (id)');
        $this->addSql('ALTER TABLE absence ADD CONSTRAINT FK_765AE0C9EC8B7ADE FOREIGN KEY (period_id) REFERENCES period (id)');
        $this->addSql('ALTER TABLE absence_type ADD CONSTRAINT FK_FBCF99B6C32A47EE FOREIGN KEY (school_id) REFERENCES school (id)');
        $this->addSql('ALTER TABLE classroom ADD CONSTRAINT FK_497D309DC32A47EE FOREIGN KEY (school_id) REFERENCES school (id)');
        $this->addSql('ALTER TABLE classroom ADD CONSTRAINT FK_497D309DD2EECC3F FOREIGN KEY (school_year_id) REFERENCES school_year (id)');
        $this->addSql('ALTER TABLE classroom ADD CONSTRAINT FK_497D309D5FB14BA7 FOREIGN KEY (level_id) REFERENCES level (id)');
        $this->addSql('ALTER TABLE classroom ADD CONSTRAINT FK_497D309D79780A7E FOREIGN KEY (main_teacher_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE classroom ADD CONSTRAINT FK_497D309D54177093 FOREIGN KEY (room_id) REFERENCES room (id)');
        $this->addSql('ALTER TABLE course ADD CONSTRAINT FK_169E6FB96278D5A8 FOREIGN KEY (classroom_id) REFERENCES classroom (id)');
        $this->addSql('ALTER TABLE course ADD CONSTRAINT FK_169E6FB923EDC87 FOREIGN KEY (subject_id) REFERENCES subject (id)');
        $this->addSql('ALTER TABLE course ADD CONSTRAINT FK_169E6FB941807E1D FOREIGN KEY (teacher_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE course ADD CONSTRAINT FK_169E6FB9D62B0FA FOREIGN KEY (time_slot_id) REFERENCES time_slot (id)');
        $this->addSql('ALTER TABLE course ADD CONSTRAINT FK_169E6FB954177093 FOREIGN KEY (room_id) REFERENCES room (id)');
        $this->addSql('ALTER TABLE employee ADD CONSTRAINT FK_5D9F75A1A76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE employee_school ADD CONSTRAINT FK_D0AF1E2E8C03F15C FOREIGN KEY (employee_id) REFERENCES employee (id)');
        $this->addSql('ALTER TABLE employee_school ADD CONSTRAINT FK_D0AF1E2EC32A47EE FOREIGN KEY (school_id) REFERENCES school (id)');
        $this->addSql('ALTER TABLE evaluation ADD CONSTRAINT FK_1323A5756278D5A8 FOREIGN KEY (classroom_id) REFERENCES classroom (id)');
        $this->addSql('ALTER TABLE evaluation ADD CONSTRAINT FK_1323A57523EDC87 FOREIGN KEY (subject_id) REFERENCES subject (id)');
        $this->addSql('ALTER TABLE evaluation ADD CONSTRAINT FK_1323A575EC8B7ADE FOREIGN KEY (period_id) REFERENCES period (id)');
        $this->addSql('ALTER TABLE evaluation ADD CONSTRAINT FK_1323A57541807E1D FOREIGN KEY (teacher_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE fee ADD CONSTRAINT FK_964964B5C32A47EE FOREIGN KEY (school_id) REFERENCES school (id)');
        $this->addSql('ALTER TABLE fee ADD CONSTRAINT FK_964964B55FB14BA7 FOREIGN KEY (level_id) REFERENCES level (id)');
        $this->addSql('ALTER TABLE financial_transaction ADD CONSTRAINT FK_3000FF4DCB944F1A FOREIGN KEY (student_id) REFERENCES student (id)');
        $this->addSql('ALTER TABLE financial_transaction ADD CONSTRAINT FK_3000FF4D4C3A3BB FOREIGN KEY (payment_id) REFERENCES payment (id)');
        $this->addSql('ALTER TABLE financial_transaction ADD CONSTRAINT FK_3000FF4D2989F1FD FOREIGN KEY (invoice_id) REFERENCES invoice (id)');
        $this->addSql('ALTER TABLE financial_transaction ADD CONSTRAINT FK_3000FF4D28722836 FOREIGN KEY (scholarship_id) REFERENCES scholarship (id)');
        $this->addSql('ALTER TABLE financial_transaction ADD CONSTRAINT FK_3000FF4DD05A957B FOREIGN KEY (recorded_by_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE `grade` ADD CONSTRAINT FK_595AAE34456C5646 FOREIGN KEY (evaluation_id) REFERENCES evaluation (id)');
        $this->addSql('ALTER TABLE `grade` ADD CONSTRAINT FK_595AAE34CB944F1A FOREIGN KEY (student_id) REFERENCES student (id)');
        $this->addSql('ALTER TABLE `grade` ADD CONSTRAINT FK_595AAE34C443EDD FOREIGN KEY (entered_by_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE invoice ADD CONSTRAINT FK_90651744CB944F1A FOREIGN KEY (student_id) REFERENCES student (id)');
        $this->addSql('ALTER TABLE invoice ADD CONSTRAINT FK_90651744AB45AECA FOREIGN KEY (fee_id) REFERENCES fee (id)');
        $this->addSql('ALTER TABLE invoice ADD CONSTRAINT FK_90651744B03A8386 FOREIGN KEY (created_by_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE level ADD CONSTRAINT FK_9AEACC13C32A47EE FOREIGN KEY (school_id) REFERENCES school (id)');
        $this->addSql('ALTER TABLE payment ADD CONSTRAINT FK_6D28840DCB944F1A FOREIGN KEY (student_id) REFERENCES student (id)');
        $this->addSql('ALTER TABLE payment ADD CONSTRAINT FK_6D28840DAB45AECA FOREIGN KEY (fee_id) REFERENCES fee (id)');
        $this->addSql('ALTER TABLE payment ADD CONSTRAINT FK_6D28840DD05A957B FOREIGN KEY (recorded_by_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE payment_plan ADD CONSTRAINT FK_FCD9CC09CB944F1A FOREIGN KEY (student_id) REFERENCES student (id)');
        $this->addSql('ALTER TABLE payment_plan ADD CONSTRAINT FK_FCD9CC09AB45AECA FOREIGN KEY (fee_id) REFERENCES fee (id)');
        $this->addSql('ALTER TABLE payment_plan ADD CONSTRAINT FK_FCD9CC09B03A8386 FOREIGN KEY (created_by_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE period ADD CONSTRAINT FK_C5B81ECEC32A47EE FOREIGN KEY (school_id) REFERENCES school (id)');
        $this->addSql('ALTER TABLE period ADD CONSTRAINT FK_C5B81ECED2EECC3F FOREIGN KEY (school_year_id) REFERENCES school_year (id)');
        $this->addSql('ALTER TABLE pre_registration ADD CONSTRAINT FK_A2FEF1B9C32A47EE FOREIGN KEY (school_id) REFERENCES school (id)');
        $this->addSql('ALTER TABLE pre_registration ADD CONSTRAINT FK_A2FEF1B92EC20237 FOREIGN KEY (requested_level_id) REFERENCES level (id)');
        $this->addSql('ALTER TABLE pre_registration ADD CONSTRAINT FK_A2FEF1B9D2EECC3F FOREIGN KEY (school_year_id) REFERENCES school_year (id)');
        $this->addSql('ALTER TABLE pre_registration ADD CONSTRAINT FK_A2FEF1B9C69DE5E5 FOREIGN KEY (validated_by_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE pre_registration_document ADD CONSTRAINT FK_413A5C65FB6C0FEA FOREIGN KEY (pre_registration_id) REFERENCES pre_registration (id)');
        $this->addSql('ALTER TABLE pre_registration_document ADD CONSTRAINT FK_413A5C6561232A4F FOREIGN KEY (document_type_id) REFERENCES document_type (id)');
        $this->addSql('ALTER TABLE pre_registration_document ADD CONSTRAINT FK_413A5C65C69DE5E5 FOREIGN KEY (validated_by_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE room ADD CONSTRAINT FK_729F519BC32A47EE FOREIGN KEY (school_id) REFERENCES school (id)');
        $this->addSql('ALTER TABLE scholarship ADD CONSTRAINT FK_F3FD63FCB944F1A FOREIGN KEY (student_id) REFERENCES student (id)');
        $this->addSql('ALTER TABLE scholarship ADD CONSTRAINT FK_F3FD63F3151C11F FOREIGN KEY (granted_by_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE school ADD CONSTRAINT FK_F99EDABB12ED03 FOREIGN KEY (school_group_id) REFERENCES school_group (id)');
        $this->addSql('ALTER TABLE student ADD CONSTRAINT FK_B723AF33C32A47EE FOREIGN KEY (school_id) REFERENCES school (id)');
        $this->addSql('ALTER TABLE student ADD CONSTRAINT FK_B723AF335FB14BA7 FOREIGN KEY (level_id) REFERENCES level (id)');
        $this->addSql('ALTER TABLE student ADD CONSTRAINT FK_B723AF336278D5A8 FOREIGN KEY (classroom_id) REFERENCES classroom (id)');
        $this->addSql('ALTER TABLE student ADD CONSTRAINT FK_B723AF33D2EECC3F FOREIGN KEY (school_year_id) REFERENCES school_year (id)');
        $this->addSql('ALTER TABLE student ADD CONSTRAINT FK_B723AF33FB6C0FEA FOREIGN KEY (pre_registration_id) REFERENCES pre_registration (id)');
        $this->addSql('ALTER TABLE subject ADD CONSTRAINT FK_FBCE3E7AC32A47EE FOREIGN KEY (school_id) REFERENCES school (id)');
        $this->addSql('ALTER TABLE subject ADD CONSTRAINT FK_FBCE3E7A5FB14BA7 FOREIGN KEY (level_id) REFERENCES level (id)');
        $this->addSql('ALTER TABLE teacher ADD CONSTRAINT FK_B0F6A6D58C03F15C FOREIGN KEY (employee_id) REFERENCES employee (id)');
        $this->addSql('ALTER TABLE teacher_subject ADD CONSTRAINT FK_360CB33B41807E1D FOREIGN KEY (teacher_id) REFERENCES teacher (id)');
        $this->addSql('ALTER TABLE teacher_subject ADD CONSTRAINT FK_360CB33B23EDC87 FOREIGN KEY (subject_id) REFERENCES subject (id)');
        $this->addSql('ALTER TABLE teacher_level ADD CONSTRAINT FK_CB15704E41807E1D FOREIGN KEY (teacher_id) REFERENCES teacher (id)');
        $this->addSql('ALTER TABLE teacher_level ADD CONSTRAINT FK_CB15704E5FB14BA7 FOREIGN KEY (level_id) REFERENCES level (id)');
        $this->addSql('ALTER TABLE time_slot ADD CONSTRAINT FK_1B3294AC32A47EE FOREIGN KEY (school_id) REFERENCES school (id)');
        $this->addSql('ALTER TABLE `user` ADD CONSTRAINT FK_8D93D64912ED03 FOREIGN KEY (school_group_id) REFERENCES school_group (id)');
        $this->addSql('ALTER TABLE user_school ADD CONSTRAINT FK_9CCCC186A76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE user_school ADD CONSTRAINT FK_9CCCC186C32A47EE FOREIGN KEY (school_id) REFERENCES school (id)');
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
        $this->addSql('ALTER TABLE classroom DROP FOREIGN KEY FK_497D309DC32A47EE');
        $this->addSql('ALTER TABLE classroom DROP FOREIGN KEY FK_497D309DD2EECC3F');
        $this->addSql('ALTER TABLE classroom DROP FOREIGN KEY FK_497D309D5FB14BA7');
        $this->addSql('ALTER TABLE classroom DROP FOREIGN KEY FK_497D309D79780A7E');
        $this->addSql('ALTER TABLE classroom DROP FOREIGN KEY FK_497D309D54177093');
        $this->addSql('ALTER TABLE course DROP FOREIGN KEY FK_169E6FB96278D5A8');
        $this->addSql('ALTER TABLE course DROP FOREIGN KEY FK_169E6FB923EDC87');
        $this->addSql('ALTER TABLE course DROP FOREIGN KEY FK_169E6FB941807E1D');
        $this->addSql('ALTER TABLE course DROP FOREIGN KEY FK_169E6FB9D62B0FA');
        $this->addSql('ALTER TABLE course DROP FOREIGN KEY FK_169E6FB954177093');
        $this->addSql('ALTER TABLE employee DROP FOREIGN KEY FK_5D9F75A1A76ED395');
        $this->addSql('ALTER TABLE employee_school DROP FOREIGN KEY FK_D0AF1E2E8C03F15C');
        $this->addSql('ALTER TABLE employee_school DROP FOREIGN KEY FK_D0AF1E2EC32A47EE');
        $this->addSql('ALTER TABLE evaluation DROP FOREIGN KEY FK_1323A5756278D5A8');
        $this->addSql('ALTER TABLE evaluation DROP FOREIGN KEY FK_1323A57523EDC87');
        $this->addSql('ALTER TABLE evaluation DROP FOREIGN KEY FK_1323A575EC8B7ADE');
        $this->addSql('ALTER TABLE evaluation DROP FOREIGN KEY FK_1323A57541807E1D');
        $this->addSql('ALTER TABLE fee DROP FOREIGN KEY FK_964964B5C32A47EE');
        $this->addSql('ALTER TABLE fee DROP FOREIGN KEY FK_964964B55FB14BA7');
        $this->addSql('ALTER TABLE financial_transaction DROP FOREIGN KEY FK_3000FF4DCB944F1A');
        $this->addSql('ALTER TABLE financial_transaction DROP FOREIGN KEY FK_3000FF4D4C3A3BB');
        $this->addSql('ALTER TABLE financial_transaction DROP FOREIGN KEY FK_3000FF4D2989F1FD');
        $this->addSql('ALTER TABLE financial_transaction DROP FOREIGN KEY FK_3000FF4D28722836');
        $this->addSql('ALTER TABLE financial_transaction DROP FOREIGN KEY FK_3000FF4DD05A957B');
        $this->addSql('ALTER TABLE `grade` DROP FOREIGN KEY FK_595AAE34456C5646');
        $this->addSql('ALTER TABLE `grade` DROP FOREIGN KEY FK_595AAE34CB944F1A');
        $this->addSql('ALTER TABLE `grade` DROP FOREIGN KEY FK_595AAE34C443EDD');
        $this->addSql('ALTER TABLE invoice DROP FOREIGN KEY FK_90651744CB944F1A');
        $this->addSql('ALTER TABLE invoice DROP FOREIGN KEY FK_90651744AB45AECA');
        $this->addSql('ALTER TABLE invoice DROP FOREIGN KEY FK_90651744B03A8386');
        $this->addSql('ALTER TABLE level DROP FOREIGN KEY FK_9AEACC13C32A47EE');
        $this->addSql('ALTER TABLE payment DROP FOREIGN KEY FK_6D28840DCB944F1A');
        $this->addSql('ALTER TABLE payment DROP FOREIGN KEY FK_6D28840DAB45AECA');
        $this->addSql('ALTER TABLE payment DROP FOREIGN KEY FK_6D28840DD05A957B');
        $this->addSql('ALTER TABLE payment_plan DROP FOREIGN KEY FK_FCD9CC09CB944F1A');
        $this->addSql('ALTER TABLE payment_plan DROP FOREIGN KEY FK_FCD9CC09AB45AECA');
        $this->addSql('ALTER TABLE payment_plan DROP FOREIGN KEY FK_FCD9CC09B03A8386');
        $this->addSql('ALTER TABLE period DROP FOREIGN KEY FK_C5B81ECEC32A47EE');
        $this->addSql('ALTER TABLE period DROP FOREIGN KEY FK_C5B81ECED2EECC3F');
        $this->addSql('ALTER TABLE pre_registration DROP FOREIGN KEY FK_A2FEF1B9C32A47EE');
        $this->addSql('ALTER TABLE pre_registration DROP FOREIGN KEY FK_A2FEF1B92EC20237');
        $this->addSql('ALTER TABLE pre_registration DROP FOREIGN KEY FK_A2FEF1B9D2EECC3F');
        $this->addSql('ALTER TABLE pre_registration DROP FOREIGN KEY FK_A2FEF1B9C69DE5E5');
        $this->addSql('ALTER TABLE pre_registration_document DROP FOREIGN KEY FK_413A5C65FB6C0FEA');
        $this->addSql('ALTER TABLE pre_registration_document DROP FOREIGN KEY FK_413A5C6561232A4F');
        $this->addSql('ALTER TABLE pre_registration_document DROP FOREIGN KEY FK_413A5C65C69DE5E5');
        $this->addSql('ALTER TABLE room DROP FOREIGN KEY FK_729F519BC32A47EE');
        $this->addSql('ALTER TABLE scholarship DROP FOREIGN KEY FK_F3FD63FCB944F1A');
        $this->addSql('ALTER TABLE scholarship DROP FOREIGN KEY FK_F3FD63F3151C11F');
        $this->addSql('ALTER TABLE school DROP FOREIGN KEY FK_F99EDABB12ED03');
        $this->addSql('ALTER TABLE student DROP FOREIGN KEY FK_B723AF33C32A47EE');
        $this->addSql('ALTER TABLE student DROP FOREIGN KEY FK_B723AF335FB14BA7');
        $this->addSql('ALTER TABLE student DROP FOREIGN KEY FK_B723AF336278D5A8');
        $this->addSql('ALTER TABLE student DROP FOREIGN KEY FK_B723AF33D2EECC3F');
        $this->addSql('ALTER TABLE student DROP FOREIGN KEY FK_B723AF33FB6C0FEA');
        $this->addSql('ALTER TABLE subject DROP FOREIGN KEY FK_FBCE3E7AC32A47EE');
        $this->addSql('ALTER TABLE subject DROP FOREIGN KEY FK_FBCE3E7A5FB14BA7');
        $this->addSql('ALTER TABLE teacher DROP FOREIGN KEY FK_B0F6A6D58C03F15C');
        $this->addSql('ALTER TABLE teacher_subject DROP FOREIGN KEY FK_360CB33B41807E1D');
        $this->addSql('ALTER TABLE teacher_subject DROP FOREIGN KEY FK_360CB33B23EDC87');
        $this->addSql('ALTER TABLE teacher_level DROP FOREIGN KEY FK_CB15704E41807E1D');
        $this->addSql('ALTER TABLE teacher_level DROP FOREIGN KEY FK_CB15704E5FB14BA7');
        $this->addSql('ALTER TABLE time_slot DROP FOREIGN KEY FK_1B3294AC32A47EE');
        $this->addSql('ALTER TABLE `user` DROP FOREIGN KEY FK_8D93D64912ED03');
        $this->addSql('ALTER TABLE user_school DROP FOREIGN KEY FK_9CCCC186A76ED395');
        $this->addSql('ALTER TABLE user_school DROP FOREIGN KEY FK_9CCCC186C32A47EE');
        $this->addSql('DROP TABLE absence');
        $this->addSql('DROP TABLE absence_type');
        $this->addSql('DROP TABLE classroom');
        $this->addSql('DROP TABLE course');
        $this->addSql('DROP TABLE document_type');
        $this->addSql('DROP TABLE employee');
        $this->addSql('DROP TABLE employee_school');
        $this->addSql('DROP TABLE evaluation');
        $this->addSql('DROP TABLE fee');
        $this->addSql('DROP TABLE financial_transaction');
        $this->addSql('DROP TABLE `grade`');
        $this->addSql('DROP TABLE invoice');
        $this->addSql('DROP TABLE level');
        $this->addSql('DROP TABLE payment');
        $this->addSql('DROP TABLE payment_plan');
        $this->addSql('DROP TABLE period');
        $this->addSql('DROP TABLE pre_registration');
        $this->addSql('DROP TABLE pre_registration_document');
        $this->addSql('DROP TABLE room');
        $this->addSql('DROP TABLE scholarship');
        $this->addSql('DROP TABLE school');
        $this->addSql('DROP TABLE school_group');
        $this->addSql('DROP TABLE school_year');
        $this->addSql('DROP TABLE student');
        $this->addSql('DROP TABLE subject');
        $this->addSql('DROP TABLE teacher');
        $this->addSql('DROP TABLE teacher_subject');
        $this->addSql('DROP TABLE teacher_level');
        $this->addSql('DROP TABLE time_slot');
        $this->addSql('DROP TABLE `user`');
        $this->addSql('DROP TABLE user_school');
        $this->addSql('DROP TABLE messenger_messages');
    }
}

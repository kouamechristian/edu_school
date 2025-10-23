<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251021114245 extends AbstractMigration
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
        $this->addSql('CREATE TABLE fee (id INT AUTO_INCREMENT NOT NULL, school_id INT NOT NULL, level_id INT DEFAULT NULL, name VARCHAR(100) NOT NULL, code VARCHAR(50) NOT NULL, amount NUMERIC(10, 2) NOT NULL, description LONGTEXT DEFAULT NULL, type VARCHAR(20) NOT NULL, frequency VARCHAR(20) NOT NULL, due_date DATE DEFAULT NULL, start_date DATE DEFAULT NULL, end_date DATE DEFAULT NULL, discount_percentage NUMERIC(5, 2) DEFAULT NULL, discount_amount NUMERIC(10, 2) DEFAULT NULL, is_active TINYINT(1) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_964964B577153098 (code), INDEX IDX_964964B5C32A47EE (school_id), INDEX IDX_964964B55FB14BA7 (level_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE financial_transaction (id INT AUTO_INCREMENT NOT NULL, student_id INT DEFAULT NULL, payment_id INT DEFAULT NULL, invoice_id INT DEFAULT NULL, scholarship_id INT DEFAULT NULL, recorded_by_id INT DEFAULT NULL, transaction_number VARCHAR(50) NOT NULL, type VARCHAR(20) NOT NULL, category VARCHAR(20) NOT NULL, amount NUMERIC(10, 2) NOT NULL, transaction_date DATE NOT NULL, description LONGTEXT DEFAULT NULL, payment_method VARCHAR(20) NOT NULL, status VARCHAR(20) NOT NULL, reference VARCHAR(100) DEFAULT NULL, notes LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_3000FF4DE0ED6D14 (transaction_number), INDEX IDX_3000FF4DCB944F1A (student_id), INDEX IDX_3000FF4D4C3A3BB (payment_id), INDEX IDX_3000FF4D2989F1FD (invoice_id), INDEX IDX_3000FF4D28722836 (scholarship_id), INDEX IDX_3000FF4DD05A957B (recorded_by_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE invoice (id INT AUTO_INCREMENT NOT NULL, student_id INT NOT NULL, fee_id INT NOT NULL, created_by_id INT DEFAULT NULL, invoice_number VARCHAR(50) NOT NULL, total_amount NUMERIC(10, 2) NOT NULL, paid_amount NUMERIC(10, 2) DEFAULT NULL, remaining_amount NUMERIC(10, 2) DEFAULT NULL, issue_date DATE NOT NULL, due_date DATE NOT NULL, status VARCHAR(20) NOT NULL, notes LONGTEXT DEFAULT NULL, discount_percentage NUMERIC(5, 2) DEFAULT NULL, discount_amount NUMERIC(10, 2) DEFAULT NULL, tax_amount NUMERIC(10, 2) DEFAULT NULL, pdf_path VARCHAR(255) DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_906517442DA68207 (invoice_number), INDEX IDX_90651744CB944F1A (student_id), INDEX IDX_90651744AB45AECA (fee_id), INDEX IDX_90651744B03A8386 (created_by_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE payment (id INT AUTO_INCREMENT NOT NULL, student_id INT NOT NULL, fee_id INT NOT NULL, recorded_by_id INT DEFAULT NULL, payment_number VARCHAR(50) NOT NULL, amount NUMERIC(10, 2) NOT NULL, payment_date DATE NOT NULL, payment_method VARCHAR(20) NOT NULL, status VARCHAR(20) NOT NULL, notes LONGTEXT DEFAULT NULL, reference VARCHAR(100) DEFAULT NULL, receipt_path VARCHAR(255) DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_6D28840DB3A884C2 (payment_number), INDEX IDX_6D28840DCB944F1A (student_id), INDEX IDX_6D28840DAB45AECA (fee_id), INDEX IDX_6D28840DD05A957B (recorded_by_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE payment_plan (id INT AUTO_INCREMENT NOT NULL, student_id INT NOT NULL, fee_id INT NOT NULL, created_by_id INT DEFAULT NULL, name VARCHAR(100) NOT NULL, total_amount NUMERIC(10, 2) NOT NULL, paid_amount NUMERIC(10, 2) DEFAULT NULL, remaining_amount NUMERIC(10, 2) DEFAULT NULL, start_date DATE NOT NULL, end_date DATE NOT NULL, installment_count INT DEFAULT NULL, installment_amount NUMERIC(10, 2) DEFAULT NULL, frequency VARCHAR(20) NOT NULL, status VARCHAR(20) NOT NULL, notes LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, INDEX IDX_FCD9CC09CB944F1A (student_id), INDEX IDX_FCD9CC09AB45AECA (fee_id), INDEX IDX_FCD9CC09B03A8386 (created_by_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE scholarship (id INT AUTO_INCREMENT NOT NULL, student_id INT NOT NULL, granted_by_id INT DEFAULT NULL, name VARCHAR(100) NOT NULL, amount NUMERIC(10, 2) DEFAULT NULL, percentage NUMERIC(5, 2) DEFAULT NULL, type VARCHAR(20) NOT NULL, status VARCHAR(20) NOT NULL, start_date DATE NOT NULL, end_date DATE DEFAULT NULL, description LONGTEXT DEFAULT NULL, conditions LONGTEXT DEFAULT NULL, sponsor VARCHAR(255) DEFAULT NULL, notes LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, INDEX IDX_F3FD63FCB944F1A (student_id), INDEX IDX_F3FD63F3151C11F (granted_by_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE absence ADD CONSTRAINT FK_765AE0C9CB944F1A FOREIGN KEY (student_id) REFERENCES student (id)');
        $this->addSql('ALTER TABLE absence ADD CONSTRAINT FK_765AE0C9CCAA91B FOREIGN KEY (absence_type_id) REFERENCES absence_type (id)');
        $this->addSql('ALTER TABLE absence ADD CONSTRAINT FK_765AE0C9D05A957B FOREIGN KEY (recorded_by_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE absence ADD CONSTRAINT FK_765AE0C9CD130C9C FOREIGN KEY (justified_by_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE absence ADD CONSTRAINT FK_765AE0C9C32A47EE FOREIGN KEY (school_id) REFERENCES school (id)');
        $this->addSql('ALTER TABLE absence ADD CONSTRAINT FK_765AE0C9EC8B7ADE FOREIGN KEY (period_id) REFERENCES period (id)');
        $this->addSql('ALTER TABLE absence_type ADD CONSTRAINT FK_FBCF99B6C32A47EE FOREIGN KEY (school_id) REFERENCES school (id)');
        $this->addSql('ALTER TABLE fee ADD CONSTRAINT FK_964964B5C32A47EE FOREIGN KEY (school_id) REFERENCES school (id)');
        $this->addSql('ALTER TABLE fee ADD CONSTRAINT FK_964964B55FB14BA7 FOREIGN KEY (level_id) REFERENCES level (id)');
        $this->addSql('ALTER TABLE financial_transaction ADD CONSTRAINT FK_3000FF4DCB944F1A FOREIGN KEY (student_id) REFERENCES student (id)');
        $this->addSql('ALTER TABLE financial_transaction ADD CONSTRAINT FK_3000FF4D4C3A3BB FOREIGN KEY (payment_id) REFERENCES payment (id)');
        $this->addSql('ALTER TABLE financial_transaction ADD CONSTRAINT FK_3000FF4D2989F1FD FOREIGN KEY (invoice_id) REFERENCES invoice (id)');
        $this->addSql('ALTER TABLE financial_transaction ADD CONSTRAINT FK_3000FF4D28722836 FOREIGN KEY (scholarship_id) REFERENCES scholarship (id)');
        $this->addSql('ALTER TABLE financial_transaction ADD CONSTRAINT FK_3000FF4DD05A957B FOREIGN KEY (recorded_by_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE invoice ADD CONSTRAINT FK_90651744CB944F1A FOREIGN KEY (student_id) REFERENCES student (id)');
        $this->addSql('ALTER TABLE invoice ADD CONSTRAINT FK_90651744AB45AECA FOREIGN KEY (fee_id) REFERENCES fee (id)');
        $this->addSql('ALTER TABLE invoice ADD CONSTRAINT FK_90651744B03A8386 FOREIGN KEY (created_by_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE payment ADD CONSTRAINT FK_6D28840DCB944F1A FOREIGN KEY (student_id) REFERENCES student (id)');
        $this->addSql('ALTER TABLE payment ADD CONSTRAINT FK_6D28840DAB45AECA FOREIGN KEY (fee_id) REFERENCES fee (id)');
        $this->addSql('ALTER TABLE payment ADD CONSTRAINT FK_6D28840DD05A957B FOREIGN KEY (recorded_by_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE payment_plan ADD CONSTRAINT FK_FCD9CC09CB944F1A FOREIGN KEY (student_id) REFERENCES student (id)');
        $this->addSql('ALTER TABLE payment_plan ADD CONSTRAINT FK_FCD9CC09AB45AECA FOREIGN KEY (fee_id) REFERENCES fee (id)');
        $this->addSql('ALTER TABLE payment_plan ADD CONSTRAINT FK_FCD9CC09B03A8386 FOREIGN KEY (created_by_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE scholarship ADD CONSTRAINT FK_F3FD63FCB944F1A FOREIGN KEY (student_id) REFERENCES student (id)');
        $this->addSql('ALTER TABLE scholarship ADD CONSTRAINT FK_F3FD63F3151C11F FOREIGN KEY (granted_by_id) REFERENCES `user` (id)');
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
        $this->addSql('ALTER TABLE fee DROP FOREIGN KEY FK_964964B5C32A47EE');
        $this->addSql('ALTER TABLE fee DROP FOREIGN KEY FK_964964B55FB14BA7');
        $this->addSql('ALTER TABLE financial_transaction DROP FOREIGN KEY FK_3000FF4DCB944F1A');
        $this->addSql('ALTER TABLE financial_transaction DROP FOREIGN KEY FK_3000FF4D4C3A3BB');
        $this->addSql('ALTER TABLE financial_transaction DROP FOREIGN KEY FK_3000FF4D2989F1FD');
        $this->addSql('ALTER TABLE financial_transaction DROP FOREIGN KEY FK_3000FF4D28722836');
        $this->addSql('ALTER TABLE financial_transaction DROP FOREIGN KEY FK_3000FF4DD05A957B');
        $this->addSql('ALTER TABLE invoice DROP FOREIGN KEY FK_90651744CB944F1A');
        $this->addSql('ALTER TABLE invoice DROP FOREIGN KEY FK_90651744AB45AECA');
        $this->addSql('ALTER TABLE invoice DROP FOREIGN KEY FK_90651744B03A8386');
        $this->addSql('ALTER TABLE payment DROP FOREIGN KEY FK_6D28840DCB944F1A');
        $this->addSql('ALTER TABLE payment DROP FOREIGN KEY FK_6D28840DAB45AECA');
        $this->addSql('ALTER TABLE payment DROP FOREIGN KEY FK_6D28840DD05A957B');
        $this->addSql('ALTER TABLE payment_plan DROP FOREIGN KEY FK_FCD9CC09CB944F1A');
        $this->addSql('ALTER TABLE payment_plan DROP FOREIGN KEY FK_FCD9CC09AB45AECA');
        $this->addSql('ALTER TABLE payment_plan DROP FOREIGN KEY FK_FCD9CC09B03A8386');
        $this->addSql('ALTER TABLE scholarship DROP FOREIGN KEY FK_F3FD63FCB944F1A');
        $this->addSql('ALTER TABLE scholarship DROP FOREIGN KEY FK_F3FD63F3151C11F');
        $this->addSql('DROP TABLE absence');
        $this->addSql('DROP TABLE absence_type');
        $this->addSql('DROP TABLE fee');
        $this->addSql('DROP TABLE financial_transaction');
        $this->addSql('DROP TABLE invoice');
        $this->addSql('DROP TABLE payment');
        $this->addSql('DROP TABLE payment_plan');
        $this->addSql('DROP TABLE scholarship');
        $this->addSql('ALTER TABLE period DROP FOREIGN KEY FK_C5B81ECEC32A47EE');
        $this->addSql('DROP INDEX IDX_C5B81ECEC32A47EE ON period');
    }
}

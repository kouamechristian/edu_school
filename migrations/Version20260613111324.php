<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260613111324 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Suppression des modules facture, plan de paiement et bourse (tables + liens).';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE financial_transaction DROP FOREIGN KEY FK_3000FF4D2989F1FD');
        $this->addSql('ALTER TABLE financial_transaction DROP FOREIGN KEY FK_3000FF4D28722836');
        $this->addSql('ALTER TABLE invoice DROP FOREIGN KEY FK_90651744AB45AECA');
        $this->addSql('ALTER TABLE invoice DROP FOREIGN KEY FK_90651744B03A8386');
        $this->addSql('ALTER TABLE invoice DROP FOREIGN KEY FK_90651744CB944F1A');
        $this->addSql('ALTER TABLE payment_plan DROP FOREIGN KEY FK_FCD9CC09CB944F1A');
        $this->addSql('ALTER TABLE payment_plan DROP FOREIGN KEY FK_FCD9CC09AB45AECA');
        $this->addSql('ALTER TABLE payment_plan DROP FOREIGN KEY FK_FCD9CC09B03A8386');
        $this->addSql('ALTER TABLE scholarship DROP FOREIGN KEY FK_F3FD63F3151C11F');
        $this->addSql('ALTER TABLE scholarship DROP FOREIGN KEY FK_F3FD63FCB944F1A');
        $this->addSql('DROP TABLE invoice');
        $this->addSql('DROP TABLE payment_plan');
        $this->addSql('DROP TABLE scholarship');
        $this->addSql('DROP INDEX IDX_3000FF4D2989F1FD ON financial_transaction');
        $this->addSql('DROP INDEX IDX_3000FF4D28722836 ON financial_transaction');
        $this->addSql('ALTER TABLE financial_transaction DROP invoice_id, DROP scholarship_id');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE invoice (id INT AUTO_INCREMENT NOT NULL, student_id INT NOT NULL, fee_id INT NOT NULL, created_by_id INT DEFAULT NULL, invoice_number VARCHAR(50) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, total_amount NUMERIC(10, 2) NOT NULL, paid_amount NUMERIC(10, 2) DEFAULT NULL, remaining_amount NUMERIC(10, 2) DEFAULT NULL, issue_date DATE NOT NULL, due_date DATE NOT NULL, status VARCHAR(20) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, notes LONGTEXT CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, discount_percentage NUMERIC(5, 2) DEFAULT NULL, discount_amount NUMERIC(10, 2) DEFAULT NULL, tax_amount NUMERIC(10, 2) DEFAULT NULL, pdf_path VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, INDEX IDX_90651744B03A8386 (created_by_id), UNIQUE INDEX UNIQ_906517442DA68207 (invoice_number), INDEX IDX_90651744CB944F1A (student_id), INDEX IDX_90651744AB45AECA (fee_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE payment_plan (id INT AUTO_INCREMENT NOT NULL, student_id INT NOT NULL, fee_id INT NOT NULL, created_by_id INT DEFAULT NULL, name VARCHAR(100) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, total_amount NUMERIC(10, 2) NOT NULL, paid_amount NUMERIC(10, 2) DEFAULT NULL, remaining_amount NUMERIC(10, 2) DEFAULT NULL, start_date DATE NOT NULL, end_date DATE NOT NULL, installment_count INT DEFAULT NULL, installment_amount NUMERIC(10, 2) DEFAULT NULL, frequency VARCHAR(20) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, status VARCHAR(20) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, notes LONGTEXT CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, INDEX IDX_FCD9CC09AB45AECA (fee_id), INDEX IDX_FCD9CC09B03A8386 (created_by_id), INDEX IDX_FCD9CC09CB944F1A (student_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE scholarship (id INT AUTO_INCREMENT NOT NULL, student_id INT NOT NULL, granted_by_id INT DEFAULT NULL, name VARCHAR(100) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, amount NUMERIC(10, 2) DEFAULT NULL, percentage NUMERIC(5, 2) DEFAULT NULL, type VARCHAR(20) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, status VARCHAR(20) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, start_date DATE NOT NULL, end_date DATE DEFAULT NULL, description LONGTEXT CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, conditions LONGTEXT CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, sponsor VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, notes LONGTEXT CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, INDEX IDX_F3FD63FCB944F1A (student_id), INDEX IDX_F3FD63F3151C11F (granted_by_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('ALTER TABLE invoice ADD CONSTRAINT FK_90651744AB45AECA FOREIGN KEY (fee_id) REFERENCES fee (id)');
        $this->addSql('ALTER TABLE invoice ADD CONSTRAINT FK_90651744B03A8386 FOREIGN KEY (created_by_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE invoice ADD CONSTRAINT FK_90651744CB944F1A FOREIGN KEY (student_id) REFERENCES student (id)');
        $this->addSql('ALTER TABLE payment_plan ADD CONSTRAINT FK_FCD9CC09CB944F1A FOREIGN KEY (student_id) REFERENCES student (id)');
        $this->addSql('ALTER TABLE payment_plan ADD CONSTRAINT FK_FCD9CC09AB45AECA FOREIGN KEY (fee_id) REFERENCES fee (id)');
        $this->addSql('ALTER TABLE payment_plan ADD CONSTRAINT FK_FCD9CC09B03A8386 FOREIGN KEY (created_by_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE scholarship ADD CONSTRAINT FK_F3FD63F3151C11F FOREIGN KEY (granted_by_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE scholarship ADD CONSTRAINT FK_F3FD63FCB944F1A FOREIGN KEY (student_id) REFERENCES student (id)');
        $this->addSql('ALTER TABLE financial_transaction ADD invoice_id INT DEFAULT NULL, ADD scholarship_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE financial_transaction ADD CONSTRAINT FK_3000FF4D28722836 FOREIGN KEY (scholarship_id) REFERENCES scholarship (id)');
        $this->addSql('ALTER TABLE financial_transaction ADD CONSTRAINT FK_3000FF4D2989F1FD FOREIGN KEY (invoice_id) REFERENCES invoice (id)');
        $this->addSql('CREATE INDEX IDX_3000FF4D2989F1FD ON financial_transaction (invoice_id)');
        $this->addSql('CREATE INDEX IDX_3000FF4D28722836 ON financial_transaction (scholarship_id)');
    }
}

<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260621171732 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE financial_transaction DROP FOREIGN KEY FK_3000FF4DC32A47EE');
        $this->addSql('ALTER TABLE financial_transaction DROP FOREIGN KEY FK_3000FF4D4C3A3BB');
        $this->addSql('ALTER TABLE financial_transaction DROP FOREIGN KEY FK_3000FF4DCB944F1A');
        $this->addSql('ALTER TABLE financial_transaction DROP FOREIGN KEY FK_3000FF4DB3E6B071');
        $this->addSql('ALTER TABLE financial_transaction DROP FOREIGN KEY FK_3000FF4DD05A957B');
        $this->addSql('DROP TABLE financial_transaction');
        $this->addSql('DROP TABLE transaction_type');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE financial_transaction (id INT AUTO_INCREMENT NOT NULL, student_id INT DEFAULT NULL, payment_id INT DEFAULT NULL, recorded_by_id INT DEFAULT NULL, transaction_type_id INT DEFAULT NULL, school_id INT DEFAULT NULL, transaction_number VARCHAR(50) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, type VARCHAR(20) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, category VARCHAR(20) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, amount NUMERIC(10, 2) NOT NULL, transaction_date DATE NOT NULL, description LONGTEXT CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, payment_method VARCHAR(20) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, status VARCHAR(20) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, reference VARCHAR(100) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, notes LONGTEXT CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, INDEX IDX_3000FF4DD05A957B (recorded_by_id), UNIQUE INDEX UNIQ_3000FF4DE0ED6D14 (transaction_number), INDEX IDX_3000FF4DB3E6B071 (transaction_type_id), INDEX IDX_3000FF4DCB944F1A (student_id), INDEX IDX_3000FF4DC32A47EE (school_id), INDEX IDX_3000FF4D4C3A3BB (payment_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE transaction_type (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(100) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, direction VARCHAR(20) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, description LONGTEXT CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, is_active TINYINT(1) DEFAULT 1 NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_6E9D69885E237E06 (name), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('ALTER TABLE financial_transaction ADD CONSTRAINT FK_3000FF4DC32A47EE FOREIGN KEY (school_id) REFERENCES school (id)');
        $this->addSql('ALTER TABLE financial_transaction ADD CONSTRAINT FK_3000FF4D4C3A3BB FOREIGN KEY (payment_id) REFERENCES payment (id)');
        $this->addSql('ALTER TABLE financial_transaction ADD CONSTRAINT FK_3000FF4DCB944F1A FOREIGN KEY (student_id) REFERENCES student (id)');
        $this->addSql('ALTER TABLE financial_transaction ADD CONSTRAINT FK_3000FF4DB3E6B071 FOREIGN KEY (transaction_type_id) REFERENCES transaction_type (id)');
        $this->addSql('ALTER TABLE financial_transaction ADD CONSTRAINT FK_3000FF4DD05A957B FOREIGN KEY (recorded_by_id) REFERENCES user (id)');
    }
}

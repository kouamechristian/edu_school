<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260611175459 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE student_transfer (id INT AUTO_INCREMENT NOT NULL, student_id INT NOT NULL, from_classroom_id INT DEFAULT NULL, to_classroom_id INT NOT NULL, school_year_id INT DEFAULT NULL, recorded_by_id INT DEFAULT NULL, motif LONGTEXT NOT NULL, document_path VARCHAR(500) DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, INDEX IDX_F3BFEF5BCB944F1A (student_id), INDEX IDX_F3BFEF5B5F3E0F4E (from_classroom_id), INDEX IDX_F3BFEF5BB6ED7674 (to_classroom_id), INDEX IDX_F3BFEF5BD2EECC3F (school_year_id), INDEX IDX_F3BFEF5BD05A957B (recorded_by_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE student_transfer ADD CONSTRAINT FK_F3BFEF5BCB944F1A FOREIGN KEY (student_id) REFERENCES student (id)');
        $this->addSql('ALTER TABLE student_transfer ADD CONSTRAINT FK_F3BFEF5B5F3E0F4E FOREIGN KEY (from_classroom_id) REFERENCES classroom (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE student_transfer ADD CONSTRAINT FK_F3BFEF5BB6ED7674 FOREIGN KEY (to_classroom_id) REFERENCES classroom (id)');
        $this->addSql('ALTER TABLE student_transfer ADD CONSTRAINT FK_F3BFEF5BD2EECC3F FOREIGN KEY (school_year_id) REFERENCES school_year (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE student_transfer ADD CONSTRAINT FK_F3BFEF5BD05A957B FOREIGN KEY (recorded_by_id) REFERENCES `user` (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE student_transfer DROP FOREIGN KEY FK_F3BFEF5BCB944F1A');
        $this->addSql('ALTER TABLE student_transfer DROP FOREIGN KEY FK_F3BFEF5B5F3E0F4E');
        $this->addSql('ALTER TABLE student_transfer DROP FOREIGN KEY FK_F3BFEF5BB6ED7674');
        $this->addSql('ALTER TABLE student_transfer DROP FOREIGN KEY FK_F3BFEF5BD2EECC3F');
        $this->addSql('ALTER TABLE student_transfer DROP FOREIGN KEY FK_F3BFEF5BD05A957B');
        $this->addSql('DROP TABLE student_transfer');
    }
}

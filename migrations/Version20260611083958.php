<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260611083958 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE student_dropout (id INT AUTO_INCREMENT NOT NULL, student_id INT NOT NULL, recorded_by_id INT DEFAULT NULL, validated_by_id INT DEFAULT NULL, reason LONGTEXT NOT NULL, dropout_date DATE NOT NULL, status VARCHAR(20) DEFAULT \'enregistré\' NOT NULL, validated_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, INDEX IDX_7FBF5A31CB944F1A (student_id), INDEX IDX_7FBF5A31D05A957B (recorded_by_id), INDEX IDX_7FBF5A31C69DE5E5 (validated_by_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE student_dropout ADD CONSTRAINT FK_7FBF5A31CB944F1A FOREIGN KEY (student_id) REFERENCES student (id)');
        $this->addSql('ALTER TABLE student_dropout ADD CONSTRAINT FK_7FBF5A31D05A957B FOREIGN KEY (recorded_by_id) REFERENCES `user` (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE student_dropout ADD CONSTRAINT FK_7FBF5A31C69DE5E5 FOREIGN KEY (validated_by_id) REFERENCES `user` (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE student_dropout DROP FOREIGN KEY FK_7FBF5A31CB944F1A');
        $this->addSql('ALTER TABLE student_dropout DROP FOREIGN KEY FK_7FBF5A31D05A957B');
        $this->addSql('ALTER TABLE student_dropout DROP FOREIGN KEY FK_7FBF5A31C69DE5E5');
        $this->addSql('DROP TABLE student_dropout');
    }
}

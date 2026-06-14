<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260612114147 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Rattachement parent ↔ enfant en 1—N (student.parent_user_id) : un enfant n\'a qu\'un seul parent. Remplace la table parent_child.';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE parent_child DROP FOREIGN KEY FK_EE82C08ACB944F1A');
        $this->addSql('ALTER TABLE parent_child DROP FOREIGN KEY FK_EE82C08A727ACA70');
        $this->addSql('DROP TABLE parent_child');
        $this->addSql('ALTER TABLE student ADD parent_user_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE student ADD CONSTRAINT FK_B723AF33D526A7D3 FOREIGN KEY (parent_user_id) REFERENCES `user` (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_B723AF33D526A7D3 ON student (parent_user_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE parent_child (parent_id INT NOT NULL, student_id INT NOT NULL, INDEX IDX_EE82C08A727ACA70 (parent_id), INDEX IDX_EE82C08ACB944F1A (student_id), PRIMARY KEY(parent_id, student_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('ALTER TABLE parent_child ADD CONSTRAINT FK_EE82C08ACB944F1A FOREIGN KEY (student_id) REFERENCES student (id)');
        $this->addSql('ALTER TABLE parent_child ADD CONSTRAINT FK_EE82C08A727ACA70 FOREIGN KEY (parent_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE student DROP FOREIGN KEY FK_B723AF33D526A7D3');
        $this->addSql('DROP INDEX IDX_B723AF33D526A7D3 ON student');
        $this->addSql('ALTER TABLE student DROP parent_user_id');
    }
}

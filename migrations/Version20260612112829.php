<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260612112829 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Table de jointure parent_child : auto-association parent ↔ enfant (portail parent).';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE parent_child (parent_id INT NOT NULL, student_id INT NOT NULL, INDEX IDX_EE82C08A727ACA70 (parent_id), INDEX IDX_EE82C08ACB944F1A (student_id), PRIMARY KEY(parent_id, student_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE parent_child ADD CONSTRAINT FK_EE82C08A727ACA70 FOREIGN KEY (parent_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE parent_child ADD CONSTRAINT FK_EE82C08ACB944F1A FOREIGN KEY (student_id) REFERENCES student (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE parent_child DROP FOREIGN KEY FK_EE82C08A727ACA70');
        $this->addSql('ALTER TABLE parent_child DROP FOREIGN KEY FK_EE82C08ACB944F1A');
        $this->addSql('DROP TABLE parent_child');
    }
}

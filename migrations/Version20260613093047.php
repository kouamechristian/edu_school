<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260613093047 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Matières enseignées par les enseignants (table user_teaching_subject).';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE user_teaching_subject (user_id INT NOT NULL, subject_id INT NOT NULL, INDEX IDX_51E4BF3AA76ED395 (user_id), INDEX IDX_51E4BF3A23EDC87 (subject_id), PRIMARY KEY(user_id, subject_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE user_teaching_subject ADD CONSTRAINT FK_51E4BF3AA76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE user_teaching_subject ADD CONSTRAINT FK_51E4BF3A23EDC87 FOREIGN KEY (subject_id) REFERENCES subject (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE user_teaching_subject DROP FOREIGN KEY FK_51E4BF3AA76ED395');
        $this->addSql('ALTER TABLE user_teaching_subject DROP FOREIGN KEY FK_51E4BF3A23EDC87');
        $this->addSql('DROP TABLE user_teaching_subject');
    }
}

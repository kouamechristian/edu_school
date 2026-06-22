<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260621191353 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE subject_equivalent_subject (subject_equivalent_id INT NOT NULL, subject_id INT NOT NULL, INDEX IDX_E3E63111A8A850D1 (subject_equivalent_id), INDEX IDX_E3E6311123EDC87 (subject_id), PRIMARY KEY(subject_equivalent_id, subject_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE subject_equivalent_subject ADD CONSTRAINT FK_E3E63111A8A850D1 FOREIGN KEY (subject_equivalent_id) REFERENCES subject_equivalent (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE subject_equivalent_subject ADD CONSTRAINT FK_E3E6311123EDC87 FOREIGN KEY (subject_id) REFERENCES subject (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE subject_equivalent DROP FOREIGN KEY FK_F8297C0923EDC87');
        $this->addSql('DROP INDEX IDX_F8297C0923EDC87 ON subject_equivalent');
        $this->addSql('ALTER TABLE subject_equivalent DROP subject_id');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE subject_equivalent_subject DROP FOREIGN KEY FK_E3E63111A8A850D1');
        $this->addSql('ALTER TABLE subject_equivalent_subject DROP FOREIGN KEY FK_E3E6311123EDC87');
        $this->addSql('DROP TABLE subject_equivalent_subject');
        $this->addSql('ALTER TABLE subject_equivalent ADD subject_id INT NOT NULL');
        $this->addSql('ALTER TABLE subject_equivalent ADD CONSTRAINT FK_F8297C0923EDC87 FOREIGN KEY (subject_id) REFERENCES subject (id) ON DELETE CASCADE');
        $this->addSql('CREATE INDEX IDX_F8297C0923EDC87 ON subject_equivalent (subject_id)');
    }
}

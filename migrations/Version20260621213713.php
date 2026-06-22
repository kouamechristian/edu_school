<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260621213713 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE subject ADD subject_equivalent_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE subject ADD CONSTRAINT FK_FBCE3E7AA8A850D1 FOREIGN KEY (subject_equivalent_id) REFERENCES subject_equivalent (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_FBCE3E7AA8A850D1 ON subject (subject_equivalent_id)');
        $this->addSql('ALTER TABLE subject_equivalent DROP FOREIGN KEY FK_F8297C0923EDC87');
        $this->addSql('DROP INDEX IDX_F8297C0923EDC87 ON subject_equivalent');
        $this->addSql('ALTER TABLE subject_equivalent DROP subject_id');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE subject DROP FOREIGN KEY FK_FBCE3E7AA8A850D1');
        $this->addSql('DROP INDEX IDX_FBCE3E7AA8A850D1 ON subject');
        $this->addSql('ALTER TABLE subject DROP subject_equivalent_id');
        $this->addSql('ALTER TABLE subject_equivalent ADD subject_id INT NOT NULL');
        $this->addSql('ALTER TABLE subject_equivalent ADD CONSTRAINT FK_F8297C0923EDC87 FOREIGN KEY (subject_id) REFERENCES subject (id) ON DELETE CASCADE');
        $this->addSql('CREATE INDEX IDX_F8297C0923EDC87 ON subject_equivalent (subject_id)');
    }
}

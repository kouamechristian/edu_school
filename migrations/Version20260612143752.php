<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260612143752 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Lie la classe à une filière (faculty_id) et une série (round_id).';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE classroom ADD faculty_id INT DEFAULT NULL, ADD round_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE classroom ADD CONSTRAINT FK_497D309D680CAB68 FOREIGN KEY (faculty_id) REFERENCES faculty (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE classroom ADD CONSTRAINT FK_497D309DA6005CA0 FOREIGN KEY (round_id) REFERENCES round (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_497D309D680CAB68 ON classroom (faculty_id)');
        $this->addSql('CREATE INDEX IDX_497D309DA6005CA0 ON classroom (round_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE classroom DROP FOREIGN KEY FK_497D309D680CAB68');
        $this->addSql('ALTER TABLE classroom DROP FOREIGN KEY FK_497D309DA6005CA0');
        $this->addSql('DROP INDEX IDX_497D309D680CAB68 ON classroom');
        $this->addSql('DROP INDEX IDX_497D309DA6005CA0 ON classroom');
        $this->addSql('ALTER TABLE classroom DROP faculty_id, DROP round_id');
    }
}

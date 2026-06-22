<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260620004029 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Phase finale : suppression des colonnes legacy de student (level_id, classroom_id, school_year_id, status, is_repeating) — désormais portées par Registration.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE student DROP FOREIGN KEY FK_B723AF335FB14BA7');
        $this->addSql('ALTER TABLE student DROP FOREIGN KEY FK_B723AF33D2EECC3F');
        $this->addSql('ALTER TABLE student DROP FOREIGN KEY FK_B723AF336278D5A8');
        $this->addSql('DROP INDEX IDX_B723AF335FB14BA7 ON student');
        $this->addSql('DROP INDEX IDX_B723AF336278D5A8 ON student');
        $this->addSql('DROP INDEX IDX_B723AF33D2EECC3F ON student');
        $this->addSql('ALTER TABLE student DROP level_id, DROP classroom_id, DROP school_year_id, DROP is_repeating, DROP status');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE student ADD level_id INT DEFAULT NULL, ADD classroom_id INT DEFAULT NULL, ADD school_year_id INT DEFAULT NULL, ADD is_repeating TINYINT(1) DEFAULT 0 NOT NULL, ADD status VARCHAR(20) NOT NULL');
        $this->addSql('ALTER TABLE student ADD CONSTRAINT FK_B723AF335FB14BA7 FOREIGN KEY (level_id) REFERENCES level (id)');
        $this->addSql('ALTER TABLE student ADD CONSTRAINT FK_B723AF33D2EECC3F FOREIGN KEY (school_year_id) REFERENCES school_year (id)');
        $this->addSql('ALTER TABLE student ADD CONSTRAINT FK_B723AF336278D5A8 FOREIGN KEY (classroom_id) REFERENCES classroom (id)');
        $this->addSql('CREATE INDEX IDX_B723AF335FB14BA7 ON student (level_id)');
        $this->addSql('CREATE INDEX IDX_B723AF336278D5A8 ON student (classroom_id)');
        $this->addSql('CREATE INDEX IDX_B723AF33D2EECC3F ON student (school_year_id)');
    }
}

<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Lie le bulletin à l'année scolaire (school_year_id), renseignée depuis la période.
 */
final class Version20260622190000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute bulletin.school_year_id (lien vers l\'année scolaire)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE bulletin ADD school_year_id INT DEFAULT NULL');
        // Backfill : l'année du bulletin est celle de sa période.
        $this->addSql('UPDATE bulletin b JOIN period p ON p.id = b.period_id SET b.school_year_id = p.school_year_id WHERE b.school_year_id IS NULL');
        $this->addSql('ALTER TABLE bulletin MODIFY school_year_id INT NOT NULL');
        $this->addSql('ALTER TABLE bulletin ADD CONSTRAINT FK_BULLETIN_SCHOOL_YEAR FOREIGN KEY (school_year_id) REFERENCES school_year (id)');
        $this->addSql('CREATE INDEX IDX_BULLETIN_SCHOOL_YEAR ON bulletin (school_year_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE bulletin DROP FOREIGN KEY FK_BULLETIN_SCHOOL_YEAR');
        $this->addSql('DROP INDEX IDX_BULLETIN_SCHOOL_YEAR ON bulletin');
        $this->addSql('ALTER TABLE bulletin DROP school_year_id');
    }
}

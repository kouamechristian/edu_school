<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Crée la table bulletin (libellé, moyenne sur, niveau, période, établissement).
 */
final class Version20260622170000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Crée la table bulletin (libellé, moyenne sur, niveau, période)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE bulletin (
                id INT AUTO_INCREMENT NOT NULL,
                libelle VARCHAR(150) NOT NULL,
                moyenne_sur INT NOT NULL,
                level_id INT NOT NULL,
                period_id INT NOT NULL,
                school_id INT NOT NULL,
                created_by_id INT DEFAULT NULL,
                created_at DATETIME NOT NULL,
                INDEX IDX_BULLETIN_LEVEL (level_id),
                INDEX IDX_BULLETIN_PERIOD (period_id),
                INDEX IDX_BULLETIN_SCHOOL (school_id),
                INDEX IDX_BULLETIN_CREATED_BY (created_by_id),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql('ALTER TABLE bulletin ADD CONSTRAINT FK_BULLETIN_LEVEL FOREIGN KEY (level_id) REFERENCES level (id)');
        $this->addSql('ALTER TABLE bulletin ADD CONSTRAINT FK_BULLETIN_PERIOD FOREIGN KEY (period_id) REFERENCES period (id)');
        $this->addSql('ALTER TABLE bulletin ADD CONSTRAINT FK_BULLETIN_SCHOOL FOREIGN KEY (school_id) REFERENCES school (id)');
        $this->addSql('ALTER TABLE bulletin ADD CONSTRAINT FK_BULLETIN_CREATED_BY FOREIGN KEY (created_by_id) REFERENCES user (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE bulletin');
    }
}

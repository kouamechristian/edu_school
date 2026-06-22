<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Supprime l'ancien système de bulletin : table generated_bulletin (remplacée par
 * l'entité Bulletin + bulletin_line).
 */
final class Version20260622200000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Supprime la table generated_bulletin (ancien système de bulletin)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS generated_bulletin');
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE generated_bulletin (
                id INT AUTO_INCREMENT NOT NULL,
                classroom_id INT DEFAULT NULL,
                period_id INT DEFAULT NULL,
                school_year_id INT DEFAULT NULL,
                generated_by_id INT DEFAULT NULL,
                student_count INT NOT NULL,
                generated_at DATETIME NOT NULL,
                INDEX IDX_GENBUL_CLASSROOM (classroom_id),
                INDEX IDX_GENBUL_PERIOD (period_id),
                INDEX IDX_GENBUL_YEAR (school_year_id),
                INDEX IDX_GENBUL_BY (generated_by_id),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
    }
}

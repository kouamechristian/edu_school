<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Renomme student.student_number en matricule_interne et ajoute matricule_national.
 */
final class Version20260610130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Renomme student_number en matricule_interne et ajoute matricule_national à la table student';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE student CHANGE student_number matricule_interne VARCHAR(50) DEFAULT NULL, ADD matricule_national VARCHAR(50) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE student CHANGE matricule_interne student_number VARCHAR(50) DEFAULT NULL, DROP matricule_national');
    }
}

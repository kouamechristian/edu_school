<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Élève : matricule national unique (empêche les doublons dans le référentiel).
 * Les valeurs vides sont ramenées à NULL (plusieurs élèves sans matricule restent permis).
 */
final class Version20261012100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Élève : index unique sur student.matricule_national';
    }

    public function up(Schema $schema): void
    {
        $duplicates = $this->connection->fetchFirstColumn(
            "SELECT LOWER(TRIM(matricule_national)) AS mn FROM student
             WHERE TRIM(COALESCE(matricule_national, '')) <> ''
             GROUP BY mn HAVING COUNT(*) > 1"
        );
        $this->abortIf(
            $duplicates !== [],
            'Doublons de matricule national à corriger avant la migration : ' . implode(', ', $duplicates)
        );

        $this->addSql('UPDATE student SET matricule_national = TRIM(matricule_national) WHERE matricule_national IS NOT NULL');
        $this->addSql("UPDATE student SET matricule_national = NULL WHERE matricule_national = ''");
        $this->addSql('CREATE UNIQUE INDEX uniq_student_matricule_national ON student (matricule_national)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX uniq_student_matricule_national ON student');
    }
}

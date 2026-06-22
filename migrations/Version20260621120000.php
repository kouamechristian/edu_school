<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Supprime le lien direct registration.student : l'élève d'une inscription est
 * désormais atteint via sa préinscription d'origine (pre_registration → student
 * pour un nouvel élève, ou existing_student pour un ancien élève).
 *
 * 1. Backfill de pre_registration_id à partir de student_id (quand il manque) ;
 * 2. Suppression de la FK, des index (dont l'unicité élève/année) et de la colonne student_id.
 */
final class Version20260621120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Retire registration.student_id ; l\'élève est porté par la préinscription liée';
    }

    public function up(Schema $schema): void
    {
        // 1. Backfill : relie chaque inscription encore sans préinscription à la
        //    préinscription correspondante (même élève + même année scolaire). Le lien
        //    « nouvel élève » est porté par student.pre_registration_id, le lien
        //    « ancien élève » par pre_registration.existing_student_id.
        $this->addSql(<<<'SQL'
            UPDATE registration r
            JOIN pre_registration pr
              ON pr.school_year_id = r.school_year_id
             AND (
                   pr.existing_student_id = r.student_id
                OR pr.id = (SELECT s.pre_registration_id FROM student s WHERE s.id = r.student_id)
             )
            SET r.pre_registration_id = pr.id
            WHERE r.pre_registration_id IS NULL
        SQL);

        // 2. Suppression du lien direct élève → inscription.
        $this->addSql('ALTER TABLE registration DROP FOREIGN KEY FK_62A8A7A7CB944F1A');
        $this->addSql('DROP INDEX unique_student_school_year ON registration');
        $this->addSql('DROP INDEX IDX_62A8A7A7CB944F1A ON registration');
        $this->addSql('ALTER TABLE registration DROP student_id');
    }

    public function down(Schema $schema): void
    {
        // Recrée la colonne et ses contraintes, puis re-renseigne student_id depuis la
        // préinscription liée (best effort : les inscriptions orphelines restent NULL).
        $this->addSql('ALTER TABLE registration ADD student_id INT DEFAULT NULL');
        $this->addSql(<<<'SQL'
            UPDATE registration r
            JOIN pre_registration pr ON pr.id = r.pre_registration_id
            LEFT JOIN student s ON s.pre_registration_id = pr.id
            SET r.student_id = COALESCE(pr.existing_student_id, s.id)
        SQL);
        $this->addSql('ALTER TABLE registration ADD CONSTRAINT FK_62A8A7A7CB944F1A FOREIGN KEY (student_id) REFERENCES student (id) ON DELETE CASCADE');
        $this->addSql('CREATE INDEX IDX_62A8A7A7CB944F1A ON registration (student_id)');
        $this->addSql('CREATE UNIQUE INDEX unique_student_school_year ON registration (student_id, school_year_id)');
    }
}

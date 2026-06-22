<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260619164915 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Phase 1 refonte Student/Registration : table registration (année+classe+redoublant+boursier), lien student_fee.registration_id, reprise des données.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE registration (id INT AUTO_INCREMENT NOT NULL, student_id INT NOT NULL, school_year_id INT NOT NULL, classroom_id INT DEFAULT NULL, is_repeating TINYINT(1) NOT NULL, boursier TINYINT(1) NOT NULL, is_active TINYINT(1) NOT NULL, enrolled_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, INDEX IDX_62A8A7A7CB944F1A (student_id), INDEX IDX_62A8A7A7D2EECC3F (school_year_id), INDEX IDX_62A8A7A76278D5A8 (classroom_id), UNIQUE INDEX unique_student_school_year (student_id, school_year_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE registration ADD CONSTRAINT FK_62A8A7A7CB944F1A FOREIGN KEY (student_id) REFERENCES student (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE registration ADD CONSTRAINT FK_62A8A7A7D2EECC3F FOREIGN KEY (school_year_id) REFERENCES school_year (id)');
        $this->addSql('ALTER TABLE registration ADD CONSTRAINT FK_62A8A7A76278D5A8 FOREIGN KEY (classroom_id) REFERENCES classroom (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE student_fee ADD registration_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE student_fee ADD CONSTRAINT FK_346113833D8F43 FOREIGN KEY (registration_id) REFERENCES registration (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_346113833D8F43 ON student_fee (registration_id)');

        // Reprise des données : une inscription par élève à partir de ses colonnes
        // actuelles (année + classe + redoublant). Année = celle de l'élève, à défaut
        // l'année courante. Boursier inconnu sur l'existant → 0.
        $this->addSql('INSERT INTO registration
                (student_id, school_year_id, classroom_id, is_repeating, boursier, is_active, enrolled_at, created_at, updated_at)
            SELECT
                s.id,
                COALESCE(s.school_year_id, (SELECT sy.id FROM school_year sy WHERE sy.is_current = 1 ORDER BY sy.start_date DESC LIMIT 1)),
                s.classroom_id,
                s.is_repeating,
                0,
                s.is_active,
                s.created_at,
                s.created_at,
                s.updated_at
            FROM student s
            WHERE COALESCE(s.school_year_id, (SELECT sy.id FROM school_year sy WHERE sy.is_current = 1 ORDER BY sy.start_date DESC LIMIT 1)) IS NOT NULL');

        // Rattachement des frais existants à la registration de l\'élève (1:1 à ce stade).
        $this->addSql('UPDATE student_fee sf
            INNER JOIN registration r ON r.student_id = sf.student_id
            SET sf.registration_id = r.id');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE student_fee DROP FOREIGN KEY FK_346113833D8F43');
        $this->addSql('ALTER TABLE registration DROP FOREIGN KEY FK_62A8A7A7CB944F1A');
        $this->addSql('ALTER TABLE registration DROP FOREIGN KEY FK_62A8A7A7D2EECC3F');
        $this->addSql('ALTER TABLE registration DROP FOREIGN KEY FK_62A8A7A76278D5A8');
        $this->addSql('DROP TABLE registration');
        $this->addSql('DROP INDEX IDX_346113833D8F43 ON student_fee');
        $this->addSql('ALTER TABLE student_fee DROP registration_id');
    }
}

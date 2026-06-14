<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Extrait le champ « type » de la table subject vers une table dédiée subject_type
 * (numéro d'ordre sur le bulletin + libellé), en préservant les données existantes.
 */
final class Version20260611193651 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Externalise le type de matière dans la table subject_type.';
    }

    public function up(Schema $schema): void
    {
        // 1. Table de référence des types de matière.
        $this->addSql('CREATE TABLE subject_type (id INT AUTO_INCREMENT NOT NULL, order_number INT NOT NULL, label VARCHAR(100) NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        // 2. Types par défaut (issus des anciennes valeurs).
        $this->addSql("INSERT INTO subject_type (order_number, label) VALUES (1, 'Obligatoire'), (2, 'Optionnelle'), (3, 'Facultative')");

        // 3. Nouvelle colonne FK sur subject.
        $this->addSql('ALTER TABLE subject ADD type_id INT DEFAULT NULL');

        // 4. Migration des valeurs existantes vers la FK.
        $this->addSql("UPDATE subject SET type_id = (SELECT id FROM subject_type WHERE label = 'Obligatoire') WHERE type = 'obligatoire'");
        $this->addSql("UPDATE subject SET type_id = (SELECT id FROM subject_type WHERE label = 'Optionnelle') WHERE type = 'optionnelle'");
        $this->addSql("UPDATE subject SET type_id = (SELECT id FROM subject_type WHERE label = 'Facultative') WHERE type = 'facultative'");

        // 5. Suppression de l'ancienne colonne texte.
        $this->addSql('ALTER TABLE subject DROP type');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE subject ADD type VARCHAR(50) DEFAULT NULL');
        $this->addSql("UPDATE subject s JOIN subject_type st ON st.id = s.type_id SET s.type = CASE st.label WHEN 'Obligatoire' THEN 'obligatoire' WHEN 'Optionnelle' THEN 'optionnelle' WHEN 'Facultative' THEN 'facultative' ELSE NULL END");
        $this->addSql('ALTER TABLE subject DROP type_id');
        $this->addSql('DROP TABLE subject_type');
    }
}

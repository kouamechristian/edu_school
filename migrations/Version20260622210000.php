<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Ajoute à subject : lv (LV), matiere_conduite (OUI/NON), art_musique
 * (MUSIQUE/ART PLASTIQUE) et note_sur_bulletin (barème).
 */
final class Version20260622210000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute subject.lv, matiere_conduite, art_musique, note_sur_bulletin';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE subject ADD lv VARCHAR(20) DEFAULT 'AUCUN', ADD matiere_conduite VARCHAR(5) DEFAULT NULL, ADD art_musique VARCHAR(20) DEFAULT NULL, ADD note_sur_bulletin INT DEFAULT NULL");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE subject DROP lv, DROP matiere_conduite, DROP art_musique, DROP note_sur_bulletin');
    }
}

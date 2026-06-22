<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260619200854 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Registration : statut administratif stocké (affecté/non affecté), repris depuis le statut legacy de l\'élève.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE registration ADD status VARCHAR(20) DEFAULT \'non_affecte\' NOT NULL');

        // Reprise du statut depuis la colonne legacy de l'élève correspondant.
        $this->addSql('UPDATE registration r
            INNER JOIN student s ON s.id = r.student_id
            SET r.status = s.status
            WHERE s.status IN (\'affecte\', \'non_affecte\')');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE registration DROP status');
    }
}

<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Ajoute matricule_interne et matricule_national à la table pre_registration.
 */
final class Version20260610140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajout des colonnes matricule_interne et matricule_national à la table pre_registration';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE pre_registration ADD matricule_interne VARCHAR(50) DEFAULT NULL, ADD matricule_national VARCHAR(50) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE pre_registration DROP matricule_interne, DROP matricule_national');
    }
}

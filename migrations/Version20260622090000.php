<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Ajoute pre_registration.submitted_by_id : le parent ayant soumis la préinscription
 * depuis l'espace parent (déclenche frais + notification à la validation).
 */
final class Version20260622090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute pre_registration.submitted_by_id (parent à l\'origine de la préinscription en ligne)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE pre_registration ADD submitted_by_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE pre_registration ADD CONSTRAINT FK_PREREG_SUBMITTED_BY FOREIGN KEY (submitted_by_id) REFERENCES user (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_PREREG_SUBMITTED_BY ON pre_registration (submitted_by_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE pre_registration DROP FOREIGN KEY FK_PREREG_SUBMITTED_BY');
        $this->addSql('DROP INDEX IDX_PREREG_SUBMITTED_BY ON pre_registration');
        $this->addSql('ALTER TABLE pre_registration DROP submitted_by_id');
    }
}

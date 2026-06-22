<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Rend pre_registration_document.document_type_id nullable : les pièces téléversées
 * librement par le parent lors d'une préinscription en ligne n'ont pas forcément de
 * type de document prédéfini.
 */
final class Version20260622140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'pre_registration_document.document_type_id devient nullable (uploads parent sans type)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE pre_registration_document MODIFY document_type_id INT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE pre_registration_document MODIFY document_type_id INT NOT NULL');
    }
}

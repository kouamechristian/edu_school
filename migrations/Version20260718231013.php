<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260718231013 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Dépenses : approbation individuelle par le fondateur (statut en_attente par défaut + approvedBy/approvedAt/rejectionReason).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE depense ADD approved_by_id INT DEFAULT NULL, ADD approved_at DATETIME DEFAULT NULL, ADD rejection_reason LONGTEXT DEFAULT NULL, CHANGE status status VARCHAR(20) DEFAULT \'en_attente\' NOT NULL');
        $this->addSql('ALTER TABLE depense ADD CONSTRAINT FK_340597572D234F6A FOREIGN KEY (approved_by_id) REFERENCES `user` (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_340597572D234F6A ON depense (approved_by_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE depense DROP FOREIGN KEY FK_340597572D234F6A');
        $this->addSql('DROP INDEX IDX_340597572D234F6A ON depense');
        $this->addSql('ALTER TABLE depense DROP approved_by_id, DROP approved_at, DROP rejection_reason, CHANGE status status VARCHAR(20) DEFAULT \'confirmée\' NOT NULL');
    }
}

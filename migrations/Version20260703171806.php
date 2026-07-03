<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Mouchard : table du journal d'activité (audit trail).
 *
 * Trace les créations / modifications / suppressions d'entités et les évènements
 * de sécurité. Les clés étrangères vers user et school sont en ON DELETE SET NULL
 * afin que les traces survivent à la suppression de leur auteur ou établissement.
 */
final class Version20260703171806 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Crée la table activity_log (Mouchard — journal d\'activité).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE activity_log (
            id INT AUTO_INCREMENT NOT NULL,
            user_id INT DEFAULT NULL,
            school_id INT DEFAULT NULL,
            username VARCHAR(180) NOT NULL,
            action VARCHAR(30) NOT NULL,
            entity_type VARCHAR(100) DEFAULT NULL,
            entity_id INT DEFAULT NULL,
            entity_label VARCHAR(255) DEFAULT NULL,
            description LONGTEXT DEFAULT NULL,
            changes JSON DEFAULT NULL,
            ip_address VARCHAR(45) DEFAULT NULL,
            route VARCHAR(255) DEFAULT NULL,
            method VARCHAR(10) DEFAULT NULL,
            created_at DATETIME NOT NULL,
            INDEX IDX_activity_user (user_id),
            INDEX IDX_activity_school (school_id),
            INDEX idx_activity_created_at (created_at),
            INDEX idx_activity_action (action),
            INDEX idx_activity_entity_type (entity_type),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('ALTER TABLE activity_log ADD CONSTRAINT FK_activity_user FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE activity_log ADD CONSTRAINT FK_activity_school FOREIGN KEY (school_id) REFERENCES school (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE activity_log DROP FOREIGN KEY FK_activity_user');
        $this->addSql('ALTER TABLE activity_log DROP FOREIGN KEY FK_activity_school');
        $this->addSql('DROP TABLE activity_log');
    }
}

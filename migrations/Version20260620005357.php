<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260620005357 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Statut affecté/non affecté déplacé de registration vers student (défaut non_affecte).';
    }

    public function up(Schema $schema): void
    {
        // 1. Nouvelle colonne sur student, 2. reprise depuis la dernière inscription,
        // 3. suppression de la colonne sur registration.
        $this->addSql('ALTER TABLE student ADD status VARCHAR(20) DEFAULT \'non_affecte\' NOT NULL');
        $this->addSql('UPDATE student s
            INNER JOIN (
                SELECT r.student_id, r.status
                FROM registration r
                INNER JOIN (SELECT student_id, MAX(id) AS max_id FROM registration GROUP BY student_id) m
                    ON m.max_id = r.id
            ) latest ON latest.student_id = s.id
            SET s.status = latest.status');
        $this->addSql('ALTER TABLE registration DROP status');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE registration ADD status VARCHAR(20) DEFAULT \'non_affecte\' NOT NULL');
        $this->addSql('ALTER TABLE student DROP status');
    }
}

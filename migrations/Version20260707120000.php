<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Transforme la relation Frais <-> Niveau de ManyToOne en ManyToMany :
 * un frais peut désormais concerner plusieurs niveaux. Les données existantes
 * (fee.level_id) sont recopiées dans la table de jointure fee_level.
 */
final class Version20260707120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Relation Frais/Niveau en ManyToMany (table de jointure fee_level).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE fee_level (fee_id INT NOT NULL, level_id INT NOT NULL, INDEX IDX_29E5A031AB45AECA (fee_id), INDEX IDX_29E5A0315FB14BA7 (level_id), PRIMARY KEY(fee_id, level_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE fee_level ADD CONSTRAINT FK_29E5A031AB45AECA FOREIGN KEY (fee_id) REFERENCES fee (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE fee_level ADD CONSTRAINT FK_29E5A0315FB14BA7 FOREIGN KEY (level_id) REFERENCES level (id) ON DELETE CASCADE');

        // Reprise des affectations existantes (frais rattaché à un niveau unique).
        $this->addSql('INSERT INTO fee_level (fee_id, level_id) SELECT id, level_id FROM fee WHERE level_id IS NOT NULL');

        // Suppression de l'ancienne colonne ManyToOne.
        $this->addSql('ALTER TABLE fee DROP FOREIGN KEY FK_964964B55FB14BA7');
        $this->addSql('DROP INDEX IDX_964964B55FB14BA7 ON fee');
        $this->addSql('ALTER TABLE fee DROP level_id');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE fee ADD level_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE fee ADD CONSTRAINT FK_964964B55FB14BA7 FOREIGN KEY (level_id) REFERENCES level (id)');
        $this->addSql('CREATE INDEX IDX_964964B55FB14BA7 ON fee (level_id)');

        // Restaure au mieux un niveau unique (le plus petit id) par frais.
        $this->addSql('UPDATE fee f SET level_id = (SELECT MIN(fl.level_id) FROM fee_level fl WHERE fl.fee_id = f.id)');

        $this->addSql('DROP TABLE fee_level');
    }
}

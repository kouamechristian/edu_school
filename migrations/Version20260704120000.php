<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Arriérés antérieurs : deux colonnes sur student_fee.
 *
 * - is_arriere_anterieur : marque une ligne de frais comme impayé d'une année
 *   antérieure (repris d'avant l'utilisation du logiciel). Ces lignes restent dues
 *   et recouvrables mais sont exclues du chiffre d'affaires de l'année courante.
 * - annee_origine : année scolaire d'origine de l'arriéré (ex. "2024-2025").
 */
final class Version20260704120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute is_arriere_anterieur et annee_origine sur student_fee (gestion des arriérés antérieurs).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE student_fee ADD is_arriere_anterieur TINYINT(1) DEFAULT 0 NOT NULL, ADD annee_origine VARCHAR(20) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE student_fee DROP is_arriere_anterieur, DROP annee_origine');
    }
}

<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Ajoute subject_equivalent.subject_paren : matière parente choisie dans une liste
 * fixe de matières (FRANÇAIS, MATHÉMATIQUE, …).
 */
final class Version20260622160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute subject_equivalent.subject_paren (matière parente)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE subject_equivalent ADD subject_paren VARCHAR(100) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE subject_equivalent DROP subject_paren');
    }
}

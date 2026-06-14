<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Insère les matières parentes par défaut (globales, school = null) utilisées
 * comme « matière parente » des matières équivalentes. Idempotent : n'insère
 * une matière que si son code n'existe pas déjà.
 */
final class Version20260611190000 extends AbstractMigration
{
    /**
     * @var array<string, string> code => libellé
     */
    private const DEFAULT_SUBJECTS = [
        'MP-FR' => 'FRANÇAIS',
        'MP-MATH' => 'MATHÉMATIQUES',
        'MP-HG' => 'HISTOIRE-GÉOGRAPHIE',
        'MP-PC' => 'PHYSIQUE-CHIMIE',
        'MP-SVT' => 'SVT',
        'MP-PHILO' => 'PHILOSOPHIE',
        'MP-ANG' => 'ANGLAIS',
        'MP-ESP' => 'ESPAGNOL',
        'MP-EPS' => 'EPS',
        'MP-COND' => 'CONDUITE',
        'MP-MUS' => 'MUSIQUE',
        'MP-EDHC' => 'EDHC',
        'MP-ARTP' => 'ARTS PLASTIQUES',
        'MP-DICT' => 'DICTÉE',
        'MP-AEM' => "ACTIVITÉ D'ÉVEIL AU MILIEU",
        'MP-EXPT' => 'EXPLOITATION DE TEXTE',
        'MP-COMPF' => 'COMPOSITION FRANÇAISE',
        'MP-EXPO' => 'EXPRESSION ORALE',
        'MP-ORTH' => 'ORTHOGRAPHE',
    ];

    public function getDescription(): string
    {
        return 'Insertion des matières parentes par défaut (globales).';
    }

    public function up(Schema $schema): void
    {
        foreach (self::DEFAULT_SUBJECTS as $code => $name) {
            $codeSql = $this->connection->quote($code);
            $nameSql = $this->connection->quote($name);
            $this->addSql(
                "INSERT INTO subject (name, code, school_id, level_id, coefficient, description, type, hours_per_week, color, is_active, created_at, updated_at) "
                . "SELECT $nameSql, $codeSql, NULL, NULL, NULL, NULL, 'obligatoire', NULL, NULL, 1, NOW(), NOW() "
                . "WHERE NOT EXISTS (SELECT 1 FROM (SELECT id FROM subject WHERE code = $codeSql) AS existing)"
            );
        }
    }

    public function down(Schema $schema): void
    {
        $codes = array_map(fn ($c) => $this->connection->quote($c), array_keys(self::DEFAULT_SUBJECTS));
        $this->addSql('DELETE FROM subject WHERE code IN (' . implode(', ', $codes) . ')');
    }
}

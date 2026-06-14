<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Ajoute les champs scolaires/civils supplémentaires (lieu de naissance, nationalité,
 * extrait de naissance, CMU, dernière école, doublant, photo, fonction et domicile du
 * parent) aux tables student et pre_registration.
 */
final class Version20260610150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajout des champs civils/scolaires supplémentaires à student et pre_registration';
    }

    public function up(Schema $schema): void
    {
        $columns = 'ADD place_of_birth VARCHAR(255) DEFAULT NULL'
            . ', ADD nationality VARCHAR(100) DEFAULT NULL'
            . ', ADD birth_certificate_number VARCHAR(100) DEFAULT NULL'
            . ', ADD cmu_number VARCHAR(50) DEFAULT NULL'
            . ', ADD last_school_attended VARCHAR(255) DEFAULT NULL'
            . ', ADD is_repeating TINYINT(1) NOT NULL DEFAULT 0'
            . ', ADD photo VARCHAR(255) DEFAULT NULL'
            . ', ADD parent_function VARCHAR(255) DEFAULT NULL'
            . ', ADD parent_address LONGTEXT DEFAULT NULL';

        $this->addSql('ALTER TABLE student ' . $columns);
        $this->addSql('ALTER TABLE pre_registration ' . $columns);
    }

    public function down(Schema $schema): void
    {
        $columns = 'DROP place_of_birth'
            . ', DROP nationality'
            . ', DROP birth_certificate_number'
            . ', DROP cmu_number'
            . ', DROP last_school_attended'
            . ', DROP is_repeating'
            . ', DROP photo'
            . ', DROP parent_function'
            . ', DROP parent_address';

        $this->addSql('ALTER TABLE student ' . $columns);
        $this->addSql('ALTER TABLE pre_registration ' . $columns);
    }
}

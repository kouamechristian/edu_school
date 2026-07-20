<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260718220153 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute les informations détaillées Père/Mère et l\'autorité parentale sur les préinscriptions et les élèves.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE pre_registration ADD father_last_name VARCHAR(100) DEFAULT NULL, ADD father_first_name VARCHAR(100) DEFAULT NULL, ADD father_phone VARCHAR(20) DEFAULT NULL, ADD father_function VARCHAR(255) DEFAULT NULL, ADD father_address LONGTEXT DEFAULT NULL, ADD mother_last_name VARCHAR(100) DEFAULT NULL, ADD mother_first_name VARCHAR(100) DEFAULT NULL, ADD mother_phone VARCHAR(20) DEFAULT NULL, ADD mother_function VARCHAR(255) DEFAULT NULL, ADD mother_address LONGTEXT DEFAULT NULL, ADD parental_authority VARCHAR(10) DEFAULT NULL');
        $this->addSql('ALTER TABLE student ADD father_last_name VARCHAR(100) DEFAULT NULL, ADD father_first_name VARCHAR(100) DEFAULT NULL, ADD father_phone VARCHAR(20) DEFAULT NULL, ADD father_function VARCHAR(255) DEFAULT NULL, ADD father_address LONGTEXT DEFAULT NULL, ADD mother_last_name VARCHAR(100) DEFAULT NULL, ADD mother_first_name VARCHAR(100) DEFAULT NULL, ADD mother_phone VARCHAR(20) DEFAULT NULL, ADD mother_function VARCHAR(255) DEFAULT NULL, ADD mother_address LONGTEXT DEFAULT NULL, ADD parental_authority VARCHAR(10) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE pre_registration DROP father_last_name, DROP father_first_name, DROP father_phone, DROP father_function, DROP father_address, DROP mother_last_name, DROP mother_first_name, DROP mother_phone, DROP mother_function, DROP mother_address, DROP parental_authority');
        $this->addSql('ALTER TABLE student DROP father_last_name, DROP father_first_name, DROP father_phone, DROP father_function, DROP father_address, DROP mother_last_name, DROP mother_first_name, DROP mother_phone, DROP mother_function, DROP mother_address, DROP parental_authority');
    }
}

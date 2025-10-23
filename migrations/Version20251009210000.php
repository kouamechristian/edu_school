<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migration complète pour tous les modules
 */
final class Version20251009210000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Création de toutes les tables pour Module 1 et Module 2';
    }

    public function up(Schema $schema): void
    {
        // Table: school
        $this->addSql('CREATE TABLE IF NOT EXISTS school (
            id INT AUTO_INCREMENT NOT NULL,
            name VARCHAR(255) NOT NULL,
            code VARCHAR(50) NOT NULL,
            type VARCHAR(50) NOT NULL,
            address LONGTEXT DEFAULT NULL,
            phone VARCHAR(20) DEFAULT NULL,
            email VARCHAR(100) DEFAULT NULL,
            director VARCHAR(100) DEFAULT NULL,
            logo VARCHAR(255) DEFAULT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            is_active TINYINT(1) NOT NULL,
            UNIQUE INDEX UNIQ_F99EDABB77153098 (code),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        // Table: school_year
        $this->addSql('CREATE TABLE IF NOT EXISTS school_year (
            id INT AUTO_INCREMENT NOT NULL,
            school_id INT NOT NULL,
            name VARCHAR(50) NOT NULL,
            start_date DATE NOT NULL,
            end_date DATE NOT NULL,
            is_current TINYINT(1) NOT NULL,
            created_at DATETIME NOT NULL,
            INDEX IDX_7B4BE38FC32A47EE (school_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        // Table: period
        $this->addSql('CREATE TABLE IF NOT EXISTS period (
            id INT AUTO_INCREMENT NOT NULL,
            school_year_id INT NOT NULL,
            name VARCHAR(50) NOT NULL,
            type VARCHAR(20) NOT NULL,
            start_date DATE NOT NULL,
            end_date DATE NOT NULL,
            weight NUMERIC(3, 2) NOT NULL,
            INDEX IDX_C5B81ECE8E608965 (school_year_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        // Table: level
        $this->addSql('CREATE TABLE IF NOT EXISTS level (
            id INT AUTO_INCREMENT NOT NULL,
            name VARCHAR(100) NOT NULL,
            category VARCHAR(20) NOT NULL,
            order_number INT NOT NULL,
            description LONGTEXT DEFAULT NULL,
            is_active TINYINT(1) NOT NULL,
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        // Table: user
        $this->addSql('CREATE TABLE IF NOT EXISTS `user` (
            id INT AUTO_INCREMENT NOT NULL,
            username VARCHAR(180) NOT NULL,
            email VARCHAR(180) NOT NULL,
            roles JSON NOT NULL,
            password VARCHAR(255) NOT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            last_login DATETIME DEFAULT NULL,
            avatar VARCHAR(255) DEFAULT NULL,
            first_name VARCHAR(100) DEFAULT NULL,
            last_name VARCHAR(100) DEFAULT NULL,
            phone VARCHAR(20) DEFAULT NULL,
            address LONGTEXT DEFAULT NULL,
            date_of_birth DATE DEFAULT NULL,
            gender VARCHAR(1) DEFAULT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            user_type VARCHAR(50) DEFAULT NULL,
            UNIQUE INDEX UNIQ_8D93D649F85E0677 (username),
            UNIQUE INDEX UNIQ_8D93D649E7927C74 (email),
            INDEX IDX_8D93D649E4EF2B (is_active),
            INDEX IDX_8D93D649C69D3FB7 (user_type),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        // Table: user_school (relation Many-to-Many)
        $this->addSql('CREATE TABLE IF NOT EXISTS user_school (
            user_id INT NOT NULL,
            school_id INT NOT NULL,
            INDEX IDX_D89C5454A76ED395 (user_id),
            INDEX IDX_D89C5454C32A47EE (school_id),
            PRIMARY KEY(user_id, school_id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        // Foreign keys
        $this->addSql('ALTER TABLE school_year ADD CONSTRAINT FK_7B4BE38FC32A47EE 
            FOREIGN KEY (school_id) REFERENCES school (id) ON DELETE CASCADE');
        
        $this->addSql('ALTER TABLE period ADD CONSTRAINT FK_C5B81ECE8E608965 
            FOREIGN KEY (school_year_id) REFERENCES school_year (id) ON DELETE CASCADE');

        $this->addSql('ALTER TABLE user_school ADD CONSTRAINT FK_D89C5454A76ED395 
            FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE');
        
        $this->addSql('ALTER TABLE user_school ADD CONSTRAINT FK_D89C5454C32A47EE 
            FOREIGN KEY (school_id) REFERENCES school (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // Drop foreign keys first
        $this->addSql('ALTER TABLE user_school DROP FOREIGN KEY FK_D89C5454A76ED395');
        $this->addSql('ALTER TABLE user_school DROP FOREIGN KEY FK_D89C5454C32A47EE');
        $this->addSql('ALTER TABLE period DROP FOREIGN KEY FK_C5B81ECE8E608965');
        $this->addSql('ALTER TABLE school_year DROP FOREIGN KEY FK_7B4BE38FC32A47EE');
        
        // Drop tables
        $this->addSql('DROP TABLE user_school');
        $this->addSql('DROP TABLE `user`');
        $this->addSql('DROP TABLE level');
        $this->addSql('DROP TABLE period');
        $this->addSql('DROP TABLE school_year');
        $this->addSql('DROP TABLE school');
    }
}


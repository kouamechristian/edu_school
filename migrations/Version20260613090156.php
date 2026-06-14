<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260613090156 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Identifiants Mobile Money par établissement (table mobile_money_config).';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE mobile_money_config (id INT AUTO_INCREMENT NOT NULL, school_id INT NOT NULL, provider VARCHAR(30) NOT NULL, base_url VARCHAR(255) DEFAULT NULL, api_key VARCHAR(255) DEFAULT NULL, api_secret VARCHAR(255) DEFAULT NULL, webhook_secret VARCHAR(255) DEFAULT NULL, is_active TINYINT(1) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_5A7666C9C32A47EE (school_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE mobile_money_config ADD CONSTRAINT FK_5A7666C9C32A47EE FOREIGN KEY (school_id) REFERENCES school (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE mobile_money_config DROP FOREIGN KEY FK_5A7666C9C32A47EE');
        $this->addSql('DROP TABLE mobile_money_config');
    }
}

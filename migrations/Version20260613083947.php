<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260613083947 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Caisse en ligne par défaut : cash_register.is_online + cashier nullable.';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE cash_register ADD is_online TINYINT(1) DEFAULT 0 NOT NULL, CHANGE cashier_id cashier_id INT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE cash_register DROP is_online, CHANGE cashier_id cashier_id INT NOT NULL');
    }
}

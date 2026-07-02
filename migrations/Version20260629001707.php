<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260629001707 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Suppression du système de paiement en ligne GeniusPay : tables mobile_money_config et payment_webhook_event, colonnes provider_* de payment.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE mobile_money_config DROP FOREIGN KEY FK_5A7666C9C32A47EE');
        $this->addSql('DROP TABLE mobile_money_config');
        $this->addSql('DROP TABLE payment_webhook_event');
        $this->addSql('DROP INDEX UNIQ_6D28840D771D04A7 ON payment');
        $this->addSql('DROP INDEX UNIQ_6D28840D7FD1C147 ON payment');
        $this->addSql('ALTER TABLE payment DROP provider, DROP provider_transaction_id, DROP provider_status, DROP payer_phone, DROP checkout_url, DROP idempotency_key');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE mobile_money_config (id INT AUTO_INCREMENT NOT NULL, school_id INT NOT NULL, provider VARCHAR(30) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, base_url VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, api_key VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, api_secret VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, webhook_secret VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, is_active TINYINT(1) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_5A7666C9C32A47EE (school_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE payment_webhook_event (id INT AUTO_INCREMENT NOT NULL, provider VARCHAR(30) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, event_id VARCHAR(100) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, type VARCHAR(50) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, payload LONGTEXT CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, signature_valid TINYINT(1) NOT NULL, received_at DATETIME NOT NULL, processed_at DATETIME DEFAULT NULL, UNIQUE INDEX uniq_provider_event (provider, event_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('ALTER TABLE mobile_money_config ADD CONSTRAINT FK_5A7666C9C32A47EE FOREIGN KEY (school_id) REFERENCES school (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE payment ADD provider VARCHAR(30) DEFAULT NULL, ADD provider_transaction_id VARCHAR(100) DEFAULT NULL, ADD provider_status VARCHAR(40) DEFAULT NULL, ADD payer_phone VARCHAR(30) DEFAULT NULL, ADD checkout_url LONGTEXT DEFAULT NULL, ADD idempotency_key VARCHAR(64) DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_6D28840D771D04A7 ON payment (provider_transaction_id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_6D28840D7FD1C147 ON payment (idempotency_key)');
    }
}

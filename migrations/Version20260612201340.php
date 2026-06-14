<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260612201340 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Paiement en ligne GeniusPay : champs passerelle sur payment + table payment_webhook_event.';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE payment_webhook_event (id INT AUTO_INCREMENT NOT NULL, provider VARCHAR(30) NOT NULL, event_id VARCHAR(100) NOT NULL, type VARCHAR(50) DEFAULT NULL, payload LONGTEXT DEFAULT NULL, signature_valid TINYINT(1) NOT NULL, received_at DATETIME NOT NULL, processed_at DATETIME DEFAULT NULL, UNIQUE INDEX uniq_provider_event (provider, event_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE payment ADD provider VARCHAR(30) DEFAULT NULL, ADD provider_transaction_id VARCHAR(100) DEFAULT NULL, ADD provider_status VARCHAR(40) DEFAULT NULL, ADD checkout_url LONGTEXT DEFAULT NULL, ADD idempotency_key VARCHAR(64) DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_6D28840D771D04A7 ON payment (provider_transaction_id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_6D28840D7FD1C147 ON payment (idempotency_key)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE payment_webhook_event');
        $this->addSql('DROP INDEX UNIQ_6D28840D771D04A7 ON payment');
        $this->addSql('DROP INDEX UNIQ_6D28840D7FD1C147 ON payment');
        $this->addSql('ALTER TABLE payment DROP provider, DROP provider_transaction_id, DROP provider_status, DROP checkout_url, DROP idempotency_key');
    }
}

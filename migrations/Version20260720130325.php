<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Paiement en ligne GeniusPay :
 *  - table `online_payment` (traçabilité et idempotence des transactions) ;
 *  - identifiants marchands par établissement sur `school` (stockés chiffrés).
 *
 * Note : les renommages d'index sur `activity_log` et `user` proposés par
 * make:migration ont été retirés — ils relèvent d'un écart de nommage
 * préexistant, sans rapport avec cette fonctionnalité.
 */
final class Version20260720130325 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Paiement en ligne GeniusPay : table online_payment + clés marchandes par établissement';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE online_payment (id INT AUTO_INCREMENT NOT NULL, school_id INT NOT NULL, student_id INT NOT NULL, student_fee_id INT DEFAULT NULL, initiated_by_id INT DEFAULT NULL, payment_id INT DEFAULT NULL, reference VARCHAR(100) NOT NULL, amount NUMERIC(12, 2) NOT NULL, status VARCHAR(20) NOT NULL, environment VARCHAR(20) NOT NULL, checkout_url LONGTEXT DEFAULT NULL, last_payload LONGTEXT DEFAULT NULL, failure_reason VARCHAR(255) DEFAULT NULL, created_at DATETIME NOT NULL, completed_at DATETIME DEFAULT NULL, UNIQUE INDEX UNIQ_5CBE57F3AEA34913 (reference), INDEX IDX_5CBE57F3C32A47EE (school_id), INDEX IDX_5CBE57F3CB944F1A (student_id), INDEX IDX_5CBE57F32573764A (student_fee_id), INDEX IDX_5CBE57F3C4EF1FC7 (initiated_by_id), UNIQUE INDEX UNIQ_5CBE57F34C3A3BB (payment_id), INDEX idx_online_payment_status (status), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE online_payment ADD CONSTRAINT FK_5CBE57F3C32A47EE FOREIGN KEY (school_id) REFERENCES school (id)');
        $this->addSql('ALTER TABLE online_payment ADD CONSTRAINT FK_5CBE57F3CB944F1A FOREIGN KEY (student_id) REFERENCES student (id)');
        $this->addSql('ALTER TABLE online_payment ADD CONSTRAINT FK_5CBE57F32573764A FOREIGN KEY (student_fee_id) REFERENCES student_fee (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE online_payment ADD CONSTRAINT FK_5CBE57F3C4EF1FC7 FOREIGN KEY (initiated_by_id) REFERENCES `user` (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE online_payment ADD CONSTRAINT FK_5CBE57F34C3A3BB FOREIGN KEY (payment_id) REFERENCES payment (id) ON DELETE SET NULL');

        $this->addSql('ALTER TABLE school ADD geniuspay_enabled TINYINT(1) DEFAULT 0 NOT NULL, ADD geniuspay_api_key LONGTEXT DEFAULT NULL, ADD geniuspay_api_secret LONGTEXT DEFAULT NULL, ADD geniuspay_webhook_secret LONGTEXT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE online_payment DROP FOREIGN KEY FK_5CBE57F3C32A47EE');
        $this->addSql('ALTER TABLE online_payment DROP FOREIGN KEY FK_5CBE57F3CB944F1A');
        $this->addSql('ALTER TABLE online_payment DROP FOREIGN KEY FK_5CBE57F32573764A');
        $this->addSql('ALTER TABLE online_payment DROP FOREIGN KEY FK_5CBE57F3C4EF1FC7');
        $this->addSql('ALTER TABLE online_payment DROP FOREIGN KEY FK_5CBE57F34C3A3BB');
        $this->addSql('DROP TABLE online_payment');

        $this->addSql('ALTER TABLE school DROP geniuspay_enabled, DROP geniuspay_api_key, DROP geniuspay_api_secret, DROP geniuspay_webhook_secret');
    }
}

<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Imputation multiple : un encaissement réparti sur plusieurs frais produit une
 * ligne `payment` par frais, regroupées sous un même numéro de reçu.
 */
final class Version20260911100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Paiement : numéro de reçu regroupant les imputations d\'un même encaissement';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE payment ADD receipt_number VARCHAR(50) DEFAULT NULL');
        $this->addSql('CREATE INDEX idx_payment_receipt_number ON payment (receipt_number)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_payment_receipt_number ON payment');
        $this->addSql('ALTER TABLE payment DROP receipt_number');
    }
}

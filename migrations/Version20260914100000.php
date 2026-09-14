<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Reçu : conserve le montant versé saisi à l'étape 1 de l'encaissement, affiché
 * comme « MONTANT PAYE » quel que soit le devenir des lignes d'imputation.
 */
final class Version20260914100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Paiement : montant versé de l\'encaissement (receipt_amount)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE payment ADD receipt_amount NUMERIC(10, 2) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE payment DROP receipt_amount');
    }
}

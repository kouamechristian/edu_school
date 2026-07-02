<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Paie — Tranche 2 : lien entre une période payée et sa dépense de caisse.
 */
final class Version20260701235500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute payment_depense_id et payment_method à payroll_period (paiement intégré).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE payroll_period ADD payment_depense_id INT DEFAULT NULL, ADD payment_method VARCHAR(20) DEFAULT NULL');
        $this->addSql('ALTER TABLE payroll_period ADD CONSTRAINT FK_4B042784742E94B6 FOREIGN KEY (payment_depense_id) REFERENCES depense (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_4B042784742E94B6 ON payroll_period (payment_depense_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE payroll_period DROP FOREIGN KEY FK_4B042784742E94B6');
        $this->addSql('DROP INDEX IDX_4B042784742E94B6 ON payroll_period');
        $this->addSql('ALTER TABLE payroll_period DROP payment_depense_id, DROP payment_method');
    }
}

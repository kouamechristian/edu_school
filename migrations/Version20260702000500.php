<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Paie — Tranche 2 : ventilation des charges sur le bulletin (état des cotisations).
 */
final class Version20260702000500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute la ventilation des charges (CNPS patronal, prestations, accident, CMU) à payslip.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE payslip ADD cmu_employee NUMERIC(12, 2) DEFAULT '0.00' NOT NULL, ADD cnps_employer NUMERIC(12, 2) DEFAULT '0.00' NOT NULL, ADD family_benefit NUMERIC(12, 2) DEFAULT '0.00' NOT NULL, ADD work_accident NUMERIC(12, 2) DEFAULT '0.00' NOT NULL, ADD cmu_employer NUMERIC(12, 2) DEFAULT '0.00' NOT NULL");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE payslip DROP cmu_employee, DROP cnps_employer, DROP family_benefit, DROP work_accident, DROP cmu_employer');
    }
}

<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Module de paie (Tranche 1) : paramètres, rubriques, périodes, bulletins et lignes.
 */
final class Version20260701234500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Crée les tables du module de paie (payroll_settings, salary_component, payroll_period, payslip, payslip_line).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("CREATE TABLE payroll_settings (id INT AUTO_INCREMENT NOT NULL, school_id INT NOT NULL, cnps_employee_rate NUMERIC(6, 3) DEFAULT '6.300' NOT NULL, cnps_employer_rate NUMERIC(6, 3) DEFAULT '7.700' NOT NULL, cnps_ceiling NUMERIC(12, 2) DEFAULT '3375000.00' NOT NULL, family_benefit_rate NUMERIC(6, 3) DEFAULT '5.000' NOT NULL, work_accident_rate NUMERIC(6, 3) DEFAULT '2.000' NOT NULL, cmu_employee NUMERIC(10, 2) DEFAULT '1000.00' NOT NULL, cmu_employer NUMERIC(10, 2) DEFAULT '1000.00' NOT NULL, max_parts NUMERIC(4, 1) DEFAULT '5.0' NOT NULL, its_brackets JSON NOT NULL, updated_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_B0D601FEC32A47EE (school_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");
        $this->addSql("CREATE TABLE salary_component (id INT AUTO_INCREMENT NOT NULL, school_id INT NOT NULL, code VARCHAR(30) NOT NULL, name VARCHAR(120) NOT NULL, direction VARCHAR(10) NOT NULL, calc_mode VARCHAR(10) NOT NULL, base VARCHAR(20) NOT NULL, amount NUMERIC(12, 2) DEFAULT '0.00' NOT NULL, rate NUMERIC(6, 3) DEFAULT '0.000' NOT NULL, taxable TINYINT(1) DEFAULT 1 NOT NULL, cnps_subject TINYINT(1) DEFAULT 1 NOT NULL, sort_order INT DEFAULT 100 NOT NULL, is_active TINYINT(1) DEFAULT 1 NOT NULL, is_system TINYINT(1) DEFAULT 0 NOT NULL, created_at DATETIME NOT NULL, INDEX IDX_3E6BD44BC32A47EE (school_id), UNIQUE INDEX uniq_component_school_code (school_id, code), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");
        $this->addSql("CREATE TABLE payroll_period (id INT AUTO_INCREMENT NOT NULL, school_id INT NOT NULL, validated_by_id INT DEFAULT NULL, month SMALLINT NOT NULL, year SMALLINT NOT NULL, status VARCHAR(20) NOT NULL, total_gross NUMERIC(14, 2) DEFAULT '0.00' NOT NULL, total_net NUMERIC(14, 2) DEFAULT '0.00' NOT NULL, total_deductions NUMERIC(14, 2) DEFAULT '0.00' NOT NULL, total_employer NUMERIC(14, 2) DEFAULT '0.00' NOT NULL, validated_at DATETIME DEFAULT NULL, paid_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, INDEX IDX_4B042784C32A47EE (school_id), INDEX IDX_4B042784C69DE5E5 (validated_by_id), UNIQUE INDEX uniq_period_school_month (school_id, year, month), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");
        $this->addSql("CREATE TABLE payslip (id INT AUTO_INCREMENT NOT NULL, period_id INT NOT NULL, employee_id INT NOT NULL, contract_id INT DEFAULT NULL, reference VARCHAR(50) NOT NULL, base_salary NUMERIC(12, 2) DEFAULT '0.00' NOT NULL, parts NUMERIC(4, 1) DEFAULT '1.0' NOT NULL, gross_total NUMERIC(12, 2) DEFAULT '0.00' NOT NULL, taxable_gross NUMERIC(12, 2) DEFAULT '0.00' NOT NULL, cnps_employee NUMERIC(12, 2) DEFAULT '0.00' NOT NULL, its NUMERIC(12, 2) DEFAULT '0.00' NOT NULL, other_deductions NUMERIC(12, 2) DEFAULT '0.00' NOT NULL, total_deductions NUMERIC(12, 2) DEFAULT '0.00' NOT NULL, net_pay NUMERIC(12, 2) DEFAULT '0.00' NOT NULL, employer_total NUMERIC(12, 2) DEFAULT '0.00' NOT NULL, payment_method VARCHAR(20) DEFAULT NULL, notes LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, INDEX IDX_9A13CDF0EC8B7ADE (period_id), INDEX IDX_9A13CDF08C03F15C (employee_id), INDEX IDX_9A13CDF02576E0FD (contract_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");
        $this->addSql("CREATE TABLE payslip_line (id INT AUTO_INCREMENT NOT NULL, payslip_id INT NOT NULL, code VARCHAR(30) DEFAULT NULL, label VARCHAR(120) NOT NULL, kind VARCHAR(12) NOT NULL, base NUMERIC(12, 2) DEFAULT NULL, rate NUMERIC(6, 3) DEFAULT NULL, amount NUMERIC(12, 2) DEFAULT '0.00' NOT NULL, employer_amount NUMERIC(12, 2) DEFAULT NULL, sort_order INT DEFAULT 100 NOT NULL, INDEX IDX_D5ECDDD2296F5EA7 (payslip_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");
        $this->addSql('ALTER TABLE payroll_settings ADD CONSTRAINT FK_B0D601FEC32A47EE FOREIGN KEY (school_id) REFERENCES school (id)');
        $this->addSql('ALTER TABLE salary_component ADD CONSTRAINT FK_3E6BD44BC32A47EE FOREIGN KEY (school_id) REFERENCES school (id)');
        $this->addSql('ALTER TABLE payroll_period ADD CONSTRAINT FK_4B042784C32A47EE FOREIGN KEY (school_id) REFERENCES school (id)');
        $this->addSql('ALTER TABLE payroll_period ADD CONSTRAINT FK_4B042784C69DE5E5 FOREIGN KEY (validated_by_id) REFERENCES `user` (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE payslip ADD CONSTRAINT FK_9A13CDF0EC8B7ADE FOREIGN KEY (period_id) REFERENCES payroll_period (id)');
        $this->addSql('ALTER TABLE payslip ADD CONSTRAINT FK_9A13CDF08C03F15C FOREIGN KEY (employee_id) REFERENCES employee (id)');
        $this->addSql('ALTER TABLE payslip ADD CONSTRAINT FK_9A13CDF02576E0FD FOREIGN KEY (contract_id) REFERENCES contract (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE payslip_line ADD CONSTRAINT FK_D5ECDDD2296F5EA7 FOREIGN KEY (payslip_id) REFERENCES payslip (id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE payslip_line DROP FOREIGN KEY FK_D5ECDDD2296F5EA7');
        $this->addSql('ALTER TABLE payslip DROP FOREIGN KEY FK_9A13CDF0EC8B7ADE');
        $this->addSql('ALTER TABLE payslip DROP FOREIGN KEY FK_9A13CDF08C03F15C');
        $this->addSql('ALTER TABLE payslip DROP FOREIGN KEY FK_9A13CDF02576E0FD');
        $this->addSql('ALTER TABLE payroll_period DROP FOREIGN KEY FK_4B042784C32A47EE');
        $this->addSql('ALTER TABLE payroll_period DROP FOREIGN KEY FK_4B042784C69DE5E5');
        $this->addSql('ALTER TABLE salary_component DROP FOREIGN KEY FK_3E6BD44BC32A47EE');
        $this->addSql('ALTER TABLE payroll_settings DROP FOREIGN KEY FK_B0D601FEC32A47EE');
        $this->addSql('DROP TABLE payslip_line');
        $this->addSql('DROP TABLE payslip');
        $this->addSql('DROP TABLE payroll_period');
        $this->addSql('DROP TABLE salary_component');
        $this->addSql('DROP TABLE payroll_settings');
    }
}

<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260614084705 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute la relation financial_transaction -> school (suivi financier par établissement).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE financial_transaction ADD school_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE financial_transaction ADD CONSTRAINT FK_3000FF4DC32A47EE FOREIGN KEY (school_id) REFERENCES school (id)');
        $this->addSql('CREATE INDEX IDX_3000FF4DC32A47EE ON financial_transaction (school_id)');

        // Backfill : rattache les transactions existantes à l'établissement
        // déductible via le paiement associé (paiement -> caisse -> établissement).
        $this->addSql(
            'UPDATE financial_transaction ft
             JOIN payment p ON p.id = ft.payment_id
             JOIN cash_register cr ON cr.id = p.cash_register_id
             SET ft.school_id = cr.school_id
             WHERE ft.school_id IS NULL AND cr.school_id IS NOT NULL'
        );
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE financial_transaction DROP FOREIGN KEY FK_3000FF4DC32A47EE');
        $this->addSql('DROP INDEX IDX_3000FF4DC32A47EE ON financial_transaction');
        $this->addSql('ALTER TABLE financial_transaction DROP school_id');
    }
}

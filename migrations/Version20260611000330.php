<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Crée la table transaction_type (types de transaction personnalisables),
 * relie financial_transaction à un type, alimente des types par défaut et
 * rattache les transactions existantes selon leur sens.
 */
final class Version20260611000330 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Types de transaction personnalisables';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE transaction_type (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(100) NOT NULL, direction VARCHAR(20) NOT NULL, description LONGTEXT DEFAULT NULL, is_active TINYINT(1) DEFAULT 1 NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_6E9D69885E237E06 (name), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE financial_transaction ADD transaction_type_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE financial_transaction ADD CONSTRAINT FK_3000FF4DB3E6B071 FOREIGN KEY (transaction_type_id) REFERENCES transaction_type (id)');
        $this->addSql('CREATE INDEX IDX_3000FF4DB3E6B071 ON financial_transaction (transaction_type_id)');

        // Types par défaut
        $this->addSql("INSERT INTO transaction_type (name, direction, description, is_active, created_at, updated_at) VALUES
            ('Entrée', 'entrée', 'Entrée de fonds générique', 1, NOW(), NOW()),
            ('Sortie', 'sortie', 'Sortie de fonds générique', 1, NOW(), NOW()),
            ('Transfert', 'transfert', 'Transfert générique', 1, NOW(), NOW()),
            ('Versement bancaire', 'transfert', 'Versement d''espèces de la caisse vers la banque', 1, NOW(), NOW())");

        // Rattache les transactions existantes au type canonique correspondant à leur sens.
        $this->addSql("UPDATE financial_transaction SET transaction_type_id = (SELECT id FROM transaction_type WHERE name = 'Entrée') WHERE type = 'entrée' AND transaction_type_id IS NULL");
        $this->addSql("UPDATE financial_transaction SET transaction_type_id = (SELECT id FROM transaction_type WHERE name = 'Sortie') WHERE type = 'sortie' AND transaction_type_id IS NULL");
        $this->addSql("UPDATE financial_transaction SET transaction_type_id = (SELECT id FROM transaction_type WHERE name = 'Transfert') WHERE type = 'transfert' AND transaction_type_id IS NULL");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE financial_transaction DROP FOREIGN KEY FK_3000FF4DB3E6B071');
        $this->addSql('DROP INDEX IDX_3000FF4DB3E6B071 ON financial_transaction');
        $this->addSql('ALTER TABLE financial_transaction DROP transaction_type_id');
        $this->addSql('DROP TABLE transaction_type');
    }
}

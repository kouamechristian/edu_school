<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Remise sur le montant total des frais d'une inscription.
 * La remise est définie sur registration (type, valeur, motif, auteur, date) et
 * répartie sur les lignes de frais (student_fee.discount_amount ; student_fee.amount
 * reste le montant net dû). Aucune donnée existante n'est modifiée.
 */
final class Version20261022100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Remise sur inscription : registration.discount_* et student_fee.discount_amount';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE registration ADD discount_granted_by_id INT DEFAULT NULL, ADD discount_type VARCHAR(10) DEFAULT NULL, ADD discount_value NUMERIC(10, 2) DEFAULT NULL, ADD discount_reason VARCHAR(255) DEFAULT NULL, ADD discount_granted_at DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE registration ADD CONSTRAINT FK_62A8A7A79C1E0990 FOREIGN KEY (discount_granted_by_id) REFERENCES `user` (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_62A8A7A79C1E0990 ON registration (discount_granted_by_id)');
        $this->addSql("ALTER TABLE student_fee ADD discount_amount NUMERIC(10, 2) DEFAULT '0.00' NOT NULL");
    }

    public function down(Schema $schema): void
    {
        // Rend aux frais leur montant brut avant de supprimer la colonne de remise.
        $this->addSql('UPDATE student_fee SET amount = amount + discount_amount WHERE discount_amount > 0');
        $this->addSql('ALTER TABLE student_fee DROP discount_amount');
        $this->addSql('ALTER TABLE registration DROP FOREIGN KEY FK_62A8A7A79C1E0990');
        $this->addSql('DROP INDEX IDX_62A8A7A79C1E0990 ON registration');
        $this->addSql('ALTER TABLE registration DROP discount_granted_by_id, DROP discount_type, DROP discount_value, DROP discount_reason, DROP discount_granted_at');
    }
}

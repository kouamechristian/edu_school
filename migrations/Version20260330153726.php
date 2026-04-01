<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260330153726 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE payment ADD student_fee_id INT DEFAULT NULL, ADD cash_register_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE payment ADD CONSTRAINT FK_6D28840D2573764A FOREIGN KEY (student_fee_id) REFERENCES student_fee (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE payment ADD CONSTRAINT FK_6D28840DA917CC69 FOREIGN KEY (cash_register_id) REFERENCES cash_register (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_6D28840D2573764A ON payment (student_fee_id)');
        $this->addSql('CREATE INDEX IDX_6D28840DA917CC69 ON payment (cash_register_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE payment DROP FOREIGN KEY FK_6D28840D2573764A');
        $this->addSql('ALTER TABLE payment DROP FOREIGN KEY FK_6D28840DA917CC69');
        $this->addSql('DROP INDEX IDX_6D28840D2573764A ON payment');
        $this->addSql('DROP INDEX IDX_6D28840DA917CC69 ON payment');
        $this->addSql('ALTER TABLE payment DROP student_fee_id, DROP cash_register_id');
    }
}

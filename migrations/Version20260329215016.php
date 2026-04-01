<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260329215016 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE fee_schedule (id INT AUTO_INCREMENT NOT NULL, fee_id INT NOT NULL, order_number INT NOT NULL, amount NUMERIC(10, 2) NOT NULL, due_date DATE NOT NULL, INDEX IDX_3806BAFCAB45AECA (fee_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE fee_schedule ADD CONSTRAINT FK_3806BAFCAB45AECA FOREIGN KEY (fee_id) REFERENCES fee (id)');
        $this->addSql('ALTER TABLE fee DROP due_date, DROP start_date, DROP end_date, DROP discount_percentage, DROP discount_amount');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE fee_schedule DROP FOREIGN KEY FK_3806BAFCAB45AECA');
        $this->addSql('DROP TABLE fee_schedule');
        $this->addSql('ALTER TABLE fee ADD due_date DATE DEFAULT NULL, ADD start_date DATE DEFAULT NULL, ADD end_date DATE DEFAULT NULL, ADD discount_percentage NUMERIC(5, 2) DEFAULT NULL, ADD discount_amount NUMERIC(10, 2) DEFAULT NULL');
    }
}

<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260329203723 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE student_fee (id INT AUTO_INCREMENT NOT NULL, student_id INT NOT NULL, fee_id INT NOT NULL, amount NUMERIC(10, 2) NOT NULL, paid_amount NUMERIC(10, 2) NOT NULL, status VARCHAR(20) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, INDEX IDX_346113CB944F1A (student_id), INDEX IDX_346113AB45AECA (fee_id), UNIQUE INDEX unique_student_fee (student_id, fee_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE student_fee ADD CONSTRAINT FK_346113CB944F1A FOREIGN KEY (student_id) REFERENCES student (id)');
        $this->addSql('ALTER TABLE student_fee ADD CONSTRAINT FK_346113AB45AECA FOREIGN KEY (fee_id) REFERENCES fee (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE student_fee DROP FOREIGN KEY FK_346113CB944F1A');
        $this->addSql('ALTER TABLE student_fee DROP FOREIGN KEY FK_346113AB45AECA');
        $this->addSql('DROP TABLE student_fee');
    }
}

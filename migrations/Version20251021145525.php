<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251021145525 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE student DROP FOREIGN KEY FK_B723AF33A1B5F7F8');
        $this->addSql('ALTER TABLE student CHANGE status status VARCHAR(20) NOT NULL');
        $this->addSql('ALTER TABLE student ADD CONSTRAINT FK_B723AF33FB6C0FEA FOREIGN KEY (pre_registration_id) REFERENCES pre_registration (id)');
        $this->addSql('ALTER TABLE student RENAME INDEX uniq_b723af33a1b5f7f8 TO UNIQ_B723AF33FB6C0FEA');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE student DROP FOREIGN KEY FK_B723AF33FB6C0FEA');
        $this->addSql('ALTER TABLE student CHANGE status status VARCHAR(20) DEFAULT \'active\' NOT NULL');
        $this->addSql('ALTER TABLE student ADD CONSTRAINT FK_B723AF33A1B5F7F8 FOREIGN KEY (pre_registration_id) REFERENCES pre_registration (id) ON UPDATE NO ACTION ON DELETE CASCADE');
        $this->addSql('ALTER TABLE student RENAME INDEX uniq_b723af33fb6c0fea TO UNIQ_B723AF33A1B5F7F8');
    }
}

<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251021130711 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE student ADD pre_registration_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE student ADD CONSTRAINT FK_B723AF33FB6C0FEA FOREIGN KEY (pre_registration_id) REFERENCES pre_registration (id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_B723AF33FB6C0FEA ON student (pre_registration_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE student DROP FOREIGN KEY FK_B723AF33FB6C0FEA');
        $this->addSql('DROP INDEX UNIQ_B723AF33FB6C0FEA ON student');
        $this->addSql('ALTER TABLE student DROP pre_registration_id');
    }
}

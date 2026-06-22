<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260621110728 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE registration DROP INDEX IDX_62A8A7A7FB6C0FEA, ADD UNIQUE INDEX UNIQ_62A8A7A7FB6C0FEA (pre_registration_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE registration DROP INDEX UNIQ_62A8A7A7FB6C0FEA, ADD INDEX IDX_62A8A7A7FB6C0FEA (pre_registration_id)');
    }
}

<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260612135606 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Scope le cycle par établissement (ajoute cycle.school_id).';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE cycle ADD school_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE cycle ADD CONSTRAINT FK_B086D193C32A47EE FOREIGN KEY (school_id) REFERENCES school (id)');
        $this->addSql('CREATE INDEX IDX_B086D193C32A47EE ON cycle (school_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE cycle DROP FOREIGN KEY FK_B086D193C32A47EE');
        $this->addSql('DROP INDEX IDX_B086D193C32A47EE ON cycle');
        $this->addSql('ALTER TABLE cycle DROP school_id');
    }
}

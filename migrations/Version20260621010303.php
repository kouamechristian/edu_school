<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260621010303 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute le lien registration.pre_registration_id (traçabilité préinscription → inscription)';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE registration ADD pre_registration_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE registration ADD CONSTRAINT FK_62A8A7A7FB6C0FEA FOREIGN KEY (pre_registration_id) REFERENCES pre_registration (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_62A8A7A7FB6C0FEA ON registration (pre_registration_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE registration DROP FOREIGN KEY FK_62A8A7A7FB6C0FEA');
        $this->addSql('DROP INDEX IDX_62A8A7A7FB6C0FEA ON registration');
        $this->addSql('ALTER TABLE registration DROP pre_registration_id');
    }
}

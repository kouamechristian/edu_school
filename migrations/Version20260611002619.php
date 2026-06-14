<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260611002619 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE cash_deposit ADD approved_by_id INT DEFAULT NULL, ADD status VARCHAR(20) DEFAULT \'en_attente\' NOT NULL, ADD approved_at DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE cash_deposit ADD CONSTRAINT FK_CB378F652D234F6A FOREIGN KEY (approved_by_id) REFERENCES `user` (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_CB378F652D234F6A ON cash_deposit (approved_by_id)');
        $this->addSql('ALTER TABLE cash_register ADD validated_by_id INT DEFAULT NULL, ADD authorized_by_id INT DEFAULT NULL, ADD is_validated TINYINT(1) DEFAULT 0 NOT NULL, ADD validated_at DATETIME DEFAULT NULL, ADD expense_authorized TINYINT(1) DEFAULT 0 NOT NULL, ADD authorized_at DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE cash_register ADD CONSTRAINT FK_3D7AB1D9C69DE5E5 FOREIGN KEY (validated_by_id) REFERENCES `user` (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE cash_register ADD CONSTRAINT FK_3D7AB1D92B62D3A1 FOREIGN KEY (authorized_by_id) REFERENCES `user` (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_3D7AB1D9C69DE5E5 ON cash_register (validated_by_id)');
        $this->addSql('CREATE INDEX IDX_3D7AB1D92B62D3A1 ON cash_register (authorized_by_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE cash_deposit DROP FOREIGN KEY FK_CB378F652D234F6A');
        $this->addSql('DROP INDEX IDX_CB378F652D234F6A ON cash_deposit');
        $this->addSql('ALTER TABLE cash_deposit DROP approved_by_id, DROP status, DROP approved_at');
        $this->addSql('ALTER TABLE cash_register DROP FOREIGN KEY FK_3D7AB1D9C69DE5E5');
        $this->addSql('ALTER TABLE cash_register DROP FOREIGN KEY FK_3D7AB1D92B62D3A1');
        $this->addSql('DROP INDEX IDX_3D7AB1D9C69DE5E5 ON cash_register');
        $this->addSql('DROP INDEX IDX_3D7AB1D92B62D3A1 ON cash_register');
        $this->addSql('ALTER TABLE cash_register DROP validated_by_id, DROP authorized_by_id, DROP is_validated, DROP validated_at, DROP expense_authorized, DROP authorized_at');
    }
}

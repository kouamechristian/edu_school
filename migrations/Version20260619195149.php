<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260619195149 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Préinscription : type (nouvel/ancien élève) + lien existing_student pour la réinscription sans duplication.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE pre_registration ADD existing_student_id INT DEFAULT NULL, ADD type VARCHAR(20) DEFAULT \'new\' NOT NULL');
        $this->addSql('ALTER TABLE pre_registration ADD CONSTRAINT FK_A2FEF1B970E6A2D4 FOREIGN KEY (existing_student_id) REFERENCES student (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_A2FEF1B970E6A2D4 ON pre_registration (existing_student_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE pre_registration DROP FOREIGN KEY FK_A2FEF1B970E6A2D4');
        $this->addSql('DROP INDEX IDX_A2FEF1B970E6A2D4 ON pre_registration');
        $this->addSql('ALTER TABLE pre_registration DROP existing_student_id, DROP type');
    }
}

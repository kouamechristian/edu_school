<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251021144107 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Fix foreign key constraint between Student and PreRegistration';
    }

    public function up(Schema $schema): void
    {
        // Supprimer l'ancienne contrainte de clé étrangère
        $this->addSql('ALTER TABLE pre_registration DROP FOREIGN KEY FK_1234567890ABCDEF');
        $this->addSql('ALTER TABLE pre_registration DROP COLUMN student_id');
        
        // Ajouter la nouvelle colonne dans la table student
        $this->addSql('ALTER TABLE student ADD pre_registration_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE student ADD CONSTRAINT FK_B723AF33A1B5F7F8 FOREIGN KEY (pre_registration_id) REFERENCES pre_registration (id) ON DELETE CASCADE');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_B723AF33A1B5F7F8 ON student (pre_registration_id)');
    }

    public function down(Schema $schema): void
    {
        // Supprimer la nouvelle contrainte
        $this->addSql('ALTER TABLE student DROP FOREIGN KEY FK_B723AF33A1B5F7F8');
        $this->addSql('DROP INDEX UNIQ_B723AF33A1B5F7F8 ON student');
        $this->addSql('ALTER TABLE student DROP COLUMN pre_registration_id');
        
        // Restaurer l'ancienne structure
        $this->addSql('ALTER TABLE pre_registration ADD student_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE pre_registration ADD CONSTRAINT FK_1234567890ABCDEF FOREIGN KEY (student_id) REFERENCES student (id)');
    }
}

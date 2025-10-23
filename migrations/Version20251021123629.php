<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251021123629 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Correction des relations User/Employee/Student - Suppression du type utilisateur "eleve"';
    }

    public function up(Schema $schema): void
    {
        // 1. Supprimer les relations dans user_school pour les utilisateurs de type "eleve"
        $this->addSql("DELETE FROM user_school WHERE user_id IN (SELECT id FROM user WHERE user_type = 'eleve')");
        
        // 2. Supprimer les grades liés aux utilisateurs de type "eleve" car ils ne devraient pas exister
        $this->addSql("DELETE FROM grade WHERE student_id IN (SELECT id FROM user WHERE user_type = 'eleve')");
        
        // 3. Supprimer les utilisateurs de type "eleve" car les élèves ne sont plus des utilisateurs
        $this->addSql("DELETE FROM user WHERE user_type = 'eleve'");
        
        // Les contraintes existent déjà, pas besoin de les recréer
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE absence DROP FOREIGN KEY FK_765AE0C9CB944F1A');
        $this->addSql('ALTER TABLE absence DROP FOREIGN KEY FK_765AE0C9CCAA91B');
        $this->addSql('ALTER TABLE absence DROP FOREIGN KEY FK_765AE0C9D05A957B');
        $this->addSql('ALTER TABLE absence DROP FOREIGN KEY FK_765AE0C9CD130C9C');
        $this->addSql('ALTER TABLE absence DROP FOREIGN KEY FK_765AE0C9C32A47EE');
        $this->addSql('ALTER TABLE absence DROP FOREIGN KEY FK_765AE0C9EC8B7ADE');
        $this->addSql('ALTER TABLE absence_type DROP FOREIGN KEY FK_FBCF99B6C32A47EE');
        $this->addSql('ALTER TABLE `grade` DROP FOREIGN KEY FK_595AAE34CB944F1A');
        $this->addSql('ALTER TABLE period DROP FOREIGN KEY FK_C5B81ECEC32A47EE');
        $this->addSql('DROP INDEX IDX_C5B81ECEC32A47EE ON period');
    }
}

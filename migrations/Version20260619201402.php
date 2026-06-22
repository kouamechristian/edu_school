<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260619201402 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Registration : lien direct vers School (school_id), repris depuis l\'élève.';
    }

    public function up(Schema $schema): void
    {
        // 1. Colonne nullable, 2. reprise depuis l'élève, 3. passage NOT NULL + FK.
        $this->addSql('ALTER TABLE registration ADD school_id INT DEFAULT NULL');
        $this->addSql('UPDATE registration r INNER JOIN student s ON s.id = r.student_id SET r.school_id = s.school_id');
        $this->addSql('ALTER TABLE registration MODIFY school_id INT NOT NULL');
        $this->addSql('ALTER TABLE registration ADD CONSTRAINT FK_62A8A7A7C32A47EE FOREIGN KEY (school_id) REFERENCES school (id)');
        $this->addSql('CREATE INDEX IDX_62A8A7A7C32A47EE ON registration (school_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE registration DROP FOREIGN KEY FK_62A8A7A7C32A47EE');
        $this->addSql('DROP INDEX IDX_62A8A7A7C32A47EE ON registration');
        $this->addSql('ALTER TABLE registration DROP school_id');
    }
}

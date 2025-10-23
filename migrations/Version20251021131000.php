<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Fix PreRegistration-Student relationship
 */
final class Version20251021131000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Fix PreRegistration-Student relationship by removing pre_registration_id from student table';
    }

    public function up(Schema $schema): void
    {
        // Drop the foreign key constraint and column from student table
        $this->addSql('ALTER TABLE student DROP FOREIGN KEY FK_B723AF33FB6C0FEA');
        $this->addSql('DROP INDEX UNIQ_B723AF33FB6C0FEA ON student');
        $this->addSql('ALTER TABLE student DROP pre_registration_id');
        
        // Add student_id column to pre_registration table
        $this->addSql('ALTER TABLE pre_registration ADD student_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE pre_registration ADD CONSTRAINT FK_1234567890ABCDEF FOREIGN KEY (student_id) REFERENCES student (id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_1234567890ABCDEF ON pre_registration (student_id)');
    }

    public function down(Schema $schema): void
    {
        // Reverse the changes
        $this->addSql('ALTER TABLE pre_registration DROP FOREIGN KEY FK_1234567890ABCDEF');
        $this->addSql('DROP INDEX UNIQ_1234567890ABCDEF ON pre_registration');
        $this->addSql('ALTER TABLE pre_registration DROP student_id');
        
        $this->addSql('ALTER TABLE student ADD pre_registration_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE student ADD CONSTRAINT FK_B723AF33FB6C0FEA FOREIGN KEY (pre_registration_id) REFERENCES pre_registration (id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_B723AF33FB6C0FEA ON student (pre_registration_id)');
    }
}

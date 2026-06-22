<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Bulletin : ajoute le statut de validation et le calcul (snapshot), et crée la table
 * bulletin_line (moyenne / rang / mention par élève).
 */
final class Version20260622180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Bulletin : validation/calcul + table bulletin_line (moyenne, rang, mention)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE bulletin ADD is_validated TINYINT(1) DEFAULT 0 NOT NULL, ADD validated_at DATETIME DEFAULT NULL, ADD validated_by_id INT DEFAULT NULL, ADD computed_at DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE bulletin ADD CONSTRAINT FK_BULLETIN_VALIDATED_BY FOREIGN KEY (validated_by_id) REFERENCES user (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_BULLETIN_VALIDATED_BY ON bulletin (validated_by_id)');

        $this->addSql(<<<'SQL'
            CREATE TABLE bulletin_line (
                id INT AUTO_INCREMENT NOT NULL,
                bulletin_id INT NOT NULL,
                student_id INT NOT NULL,
                average NUMERIC(6, 2) DEFAULT NULL,
                rang INT DEFAULT NULL,
                mention VARCHAR(60) DEFAULT NULL,
                INDEX IDX_BULLINE_BULLETIN (bulletin_id),
                INDEX IDX_BULLINE_STUDENT (student_id),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql('ALTER TABLE bulletin_line ADD CONSTRAINT FK_BULLINE_BULLETIN FOREIGN KEY (bulletin_id) REFERENCES bulletin (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE bulletin_line ADD CONSTRAINT FK_BULLINE_STUDENT FOREIGN KEY (student_id) REFERENCES student (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE bulletin_line');
        $this->addSql('ALTER TABLE bulletin DROP FOREIGN KEY FK_BULLETIN_VALIDATED_BY');
        $this->addSql('DROP INDEX IDX_BULLETIN_VALIDATED_BY ON bulletin');
        $this->addSql('ALTER TABLE bulletin DROP is_validated, DROP validated_at, DROP validated_by_id, DROP computed_at');
    }
}

<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Ajoute le jeton d'authentification pour l'API mobile ed_photo (user.api_token).
 */
final class Version20260719090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return "API mobile ed_photo : ajout de user.api_token (jeton porteur unique).";
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `user` ADD api_token VARCHAR(64) DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_USER_API_TOKEN ON `user` (api_token)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX UNIQ_USER_API_TOKEN ON `user`');
        $this->addSql('ALTER TABLE `user` DROP api_token');
    }
}

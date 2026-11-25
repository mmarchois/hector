<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261124155720 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Comptes invités (nom et email facultatifs) et acceptation des conditions d\'utilisation';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "user" ADD terms_of_use_version VARCHAR(64) DEFAULT NULL');
        $this->addSql('ALTER TABLE "user" ADD terms_of_use_accepted_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE "user" ALTER full_name DROP NOT NULL');
        $this->addSql('ALTER TABLE "user" ALTER email DROP NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // Les invités n'ont ni nom ni email : ils ne survivent pas au retour en arrière
        $this->addSql('DELETE FROM refresh_tokens WHERE username IN (SELECT uuid::text FROM "user" WHERE email IS NULL)');
        $this->addSql('DELETE FROM "user" WHERE email IS NULL');
        $this->addSql('ALTER TABLE "user" DROP terms_of_use_version');
        $this->addSql('ALTER TABLE "user" DROP terms_of_use_accepted_at');
        $this->addSql('ALTER TABLE "user" ALTER full_name SET NOT NULL');
        $this->addSql('ALTER TABLE "user" ALTER email SET NOT NULL');
    }
}

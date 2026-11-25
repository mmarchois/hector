<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261125141419 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Calculs enregistrés (lot L13)';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE calculation_run (owner_uuid UUID NOT NULL, calculator_key VARCHAR(64) NOT NULL, calculator_version INT NOT NULL, trace JSON NOT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, uuid UUID NOT NULL, PRIMARY KEY (uuid))');
        $this->addSql('CREATE INDEX idx_calculation_run_owner_created_at ON calculation_run (owner_uuid, created_at)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE calculation_run');
    }
}

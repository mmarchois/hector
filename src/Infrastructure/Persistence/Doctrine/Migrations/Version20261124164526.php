<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261124164526 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Trace et motif d\'échec des tours (lot L5)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE conversation_turn ADD failure VARCHAR(16) DEFAULT NULL');
        $this->addSql('ALTER TABLE conversation_turn ADD trace_model VARCHAR(64) DEFAULT NULL');
        $this->addSql('ALTER TABLE conversation_turn ADD trace_prompt_version VARCHAR(64) DEFAULT NULL');
        $this->addSql('ALTER TABLE conversation_turn ADD trace_stop_reason VARCHAR(32) DEFAULT NULL');
        $this->addSql('ALTER TABLE conversation_turn ADD trace_input_tokens INT DEFAULT NULL');
        $this->addSql('ALTER TABLE conversation_turn ADD trace_output_tokens INT DEFAULT NULL');
        $this->addSql('ALTER TABLE conversation_turn ADD trace_cache_read_input_tokens INT DEFAULT NULL');
        $this->addSql('ALTER TABLE conversation_turn ADD trace_cache_creation_input_tokens INT DEFAULT NULL');
        $this->addSql('ALTER TABLE conversation_turn ADD trace_duration_ms INT DEFAULT NULL');
        $this->addSql('ALTER TABLE conversation_turn ADD trace_error VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE conversation_turn DROP failure');
        $this->addSql('ALTER TABLE conversation_turn DROP trace_model');
        $this->addSql('ALTER TABLE conversation_turn DROP trace_prompt_version');
        $this->addSql('ALTER TABLE conversation_turn DROP trace_stop_reason');
        $this->addSql('ALTER TABLE conversation_turn DROP trace_input_tokens');
        $this->addSql('ALTER TABLE conversation_turn DROP trace_output_tokens');
        $this->addSql('ALTER TABLE conversation_turn DROP trace_cache_read_input_tokens');
        $this->addSql('ALTER TABLE conversation_turn DROP trace_cache_creation_input_tokens');
        $this->addSql('ALTER TABLE conversation_turn DROP trace_duration_ms');
        $this->addSql('ALTER TABLE conversation_turn DROP trace_error');
    }
}

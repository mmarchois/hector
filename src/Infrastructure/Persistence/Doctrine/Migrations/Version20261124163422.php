<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261124163422 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Conversations, messages et tours (lot L4)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE conversation (owner_uuid UUID NOT NULL, title VARCHAR(80) DEFAULT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, uuid UUID NOT NULL, PRIMARY KEY (uuid))');
        $this->addSql('CREATE INDEX idx_conversation_owner_updated_at ON conversation (owner_uuid, updated_at)');
        $this->addSql('CREATE TABLE conversation_message (role VARCHAR(16) NOT NULL, blocks JSON NOT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, uuid UUID NOT NULL, conversation_uuid UUID NOT NULL, PRIMARY KEY (uuid))');
        $this->addSql('CREATE INDEX idx_conversation_message_order ON conversation_message (conversation_uuid, uuid)');
        $this->addSql('CREATE INDEX IDX_2DEB3E7560033BA ON conversation_message (conversation_uuid)');
        $this->addSql('CREATE TABLE conversation_turn (status VARCHAR(16) NOT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, finished_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL, uuid UUID NOT NULL, user_message_uuid UUID NOT NULL, assistant_message_uuid UUID DEFAULT NULL, conversation_uuid UUID NOT NULL, PRIMARY KEY (uuid))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_F75F765BF826B77D ON conversation_turn (user_message_uuid)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_F75F765BA997BF0D ON conversation_turn (assistant_message_uuid)');
        $this->addSql('CREATE INDEX IDX_F75F765B60033BA ON conversation_turn (conversation_uuid)');
        $this->addSql('ALTER TABLE conversation_message ADD CONSTRAINT FK_2DEB3E7560033BA FOREIGN KEY (conversation_uuid) REFERENCES conversation (uuid) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE conversation_turn ADD CONSTRAINT FK_F75F765BF826B77D FOREIGN KEY (user_message_uuid) REFERENCES conversation_message (uuid) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE conversation_turn ADD CONSTRAINT FK_F75F765BA997BF0D FOREIGN KEY (assistant_message_uuid) REFERENCES conversation_message (uuid) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE conversation_turn ADD CONSTRAINT FK_F75F765B60033BA FOREIGN KEY (conversation_uuid) REFERENCES conversation (uuid) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE conversation_message DROP CONSTRAINT FK_2DEB3E7560033BA');
        $this->addSql('ALTER TABLE conversation_turn DROP CONSTRAINT FK_F75F765BF826B77D');
        $this->addSql('ALTER TABLE conversation_turn DROP CONSTRAINT FK_F75F765BA997BF0D');
        $this->addSql('ALTER TABLE conversation_turn DROP CONSTRAINT FK_F75F765B60033BA');
        $this->addSql('DROP TABLE conversation');
        $this->addSql('DROP TABLE conversation_message');
        $this->addSql('DROP TABLE conversation_turn');
    }
}

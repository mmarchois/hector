<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261125083522 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE knowledge_document_version (label VARCHAR(120) NOT NULL, published_on DATE DEFAULT NULL, effective_from DATE DEFAULT NULL, effective_until DATE DEFAULT NULL, replaces_uuid UUID DEFAULT NULL, file_name VARCHAR(255) NOT NULL, file_fingerprint VARCHAR(64) NOT NULL, storage_key VARCHAR(255) NOT NULL, status VARCHAR(16) NOT NULL, reviewed_by VARCHAR(255) DEFAULT NULL, reviewed_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL, withdrawn_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, uuid UUID NOT NULL, source_uuid UUID NOT NULL, PRIMARY KEY (uuid))');
        $this->addSql('CREATE UNIQUE INDEX uniq_knowledge_document_version_label ON knowledge_document_version (source_uuid, label)');
        $this->addSql('CREATE INDEX IDX_35BB3E06D0F5D5A5 ON knowledge_document_version (source_uuid)');
        $this->addSql('CREATE TABLE knowledge_ingestion_run (pipeline_version VARCHAR(32) NOT NULL, extractor VARCHAR(64) NOT NULL, chunker VARCHAR(64) NOT NULL, chunker_parameters JSON NOT NULL, embedding_model VARCHAR(120) DEFAULT NULL, passage_count INT NOT NULL, started_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, uuid UUID NOT NULL, version_uuid UUID NOT NULL, PRIMARY KEY (uuid))');
        $this->addSql('CREATE INDEX idx_knowledge_ingestion_run_version ON knowledge_ingestion_run (version_uuid, started_at)');
        $this->addSql('CREATE INDEX IDX_DDCA62E5606F4CA2 ON knowledge_ingestion_run (version_uuid)');
        $this->addSql('CREATE TABLE knowledge_passage (ordinal INT NOT NULL, page INT DEFAULT NULL, section VARCHAR(500) DEFAULT NULL, article VARCHAR(64) DEFAULT NULL, text TEXT NOT NULL, fingerprint VARCHAR(64) NOT NULL, uuid UUID NOT NULL, version_uuid UUID NOT NULL, PRIMARY KEY (uuid))');
        $this->addSql('CREATE UNIQUE INDEX uniq_knowledge_passage_ordinal ON knowledge_passage (version_uuid, ordinal)');
        $this->addSql('CREATE INDEX IDX_5462A1E8606F4CA2 ON knowledge_passage (version_uuid)');
        $this->addSql('CREATE TABLE knowledge_source (corpus VARCHAR(32) NOT NULL, title VARCHAR(255) NOT NULL, publisher VARCHAR(255) NOT NULL, kind VARCHAR(32) NOT NULL, domains JSON NOT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, uuid UUID NOT NULL, license_indexing_allowed BOOLEAN NOT NULL, license_excerpt_allowed BOOLEAN NOT NULL, license_excerpt_max_characters INT DEFAULT NULL, license_third_party_transfer_allowed BOOLEAN NOT NULL, license_origin VARCHAR(255) NOT NULL, PRIMARY KEY (uuid))');
        $this->addSql('ALTER TABLE knowledge_document_version ADD CONSTRAINT FK_35BB3E06D0F5D5A5 FOREIGN KEY (source_uuid) REFERENCES knowledge_source (uuid)');
        $this->addSql('ALTER TABLE knowledge_ingestion_run ADD CONSTRAINT FK_DDCA62E5606F4CA2 FOREIGN KEY (version_uuid) REFERENCES knowledge_document_version (uuid) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE knowledge_passage ADD CONSTRAINT FK_5462A1E8606F4CA2 FOREIGN KEY (version_uuid) REFERENCES knowledge_document_version (uuid) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE knowledge_document_version DROP CONSTRAINT FK_35BB3E06D0F5D5A5');
        $this->addSql('ALTER TABLE knowledge_ingestion_run DROP CONSTRAINT FK_DDCA62E5606F4CA2');
        $this->addSql('ALTER TABLE knowledge_passage DROP CONSTRAINT FK_5462A1E8606F4CA2');
        $this->addSql('DROP TABLE knowledge_document_version');
        $this->addSql('DROP TABLE knowledge_ingestion_run');
        $this->addSql('DROP TABLE knowledge_passage');
        $this->addSql('DROP TABLE knowledge_source');
    }
}

<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Initial schema for the Export Service: the export_jobs table.
 */
final class Version20260713120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create export_jobs table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE export_jobs (id UUID NOT NULL, type VARCHAR(50) NOT NULL, format VARCHAR(10) NOT NULL, status VARCHAR(20) NOT NULL, file_path VARCHAR(500) DEFAULT NULL, filters JSON DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, completed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, error_message TEXT DEFAULT NULL, requested_by VARCHAR(180) NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX idx_export_jobs_status ON export_jobs (status)');
        $this->addSql('CREATE INDEX idx_export_jobs_created_at ON export_jobs (created_at)');
        $this->addSql('COMMENT ON COLUMN export_jobs.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN export_jobs.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN export_jobs.completed_at IS \'(DC2Type:datetime_immutable)\'');

        // Internal async job queue (Doctrine Messenger transport). Created here so
        // the table exists before the worker starts, instead of relying on lazy
        // auto_setup.
        $this->addSql('CREATE TABLE messenger_messages (id BIGSERIAL NOT NULL, body TEXT NOT NULL, headers TEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, available_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, delivered_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750 ON messenger_messages (queue_name, available_at, delivered_at, id)');
        $this->addSql('COMMENT ON COLUMN messenger_messages.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN messenger_messages.available_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN messenger_messages.delivered_at IS \'(DC2Type:datetime_immutable)\'');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE export_jobs');
        $this->addSql('DROP TABLE messenger_messages');
    }
}

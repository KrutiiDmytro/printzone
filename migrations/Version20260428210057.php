<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260428210057 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add export_jobs table for data export module';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE export_jobs (id SERIAL NOT NULL, type VARCHAR(50) NOT NULL, format VARCHAR(10) NOT NULL, status VARCHAR(20) NOT NULL, file_path VARCHAR(500) DEFAULT NULL, filters JSON DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, completed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, error_message TEXT DEFAULT NULL, requested_by VARCHAR(180) NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX idx_export_jobs_status ON export_jobs (status)');
        $this->addSql('CREATE INDEX idx_export_jobs_created_at ON export_jobs (created_at)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('DROP TABLE export_jobs');
    }
}

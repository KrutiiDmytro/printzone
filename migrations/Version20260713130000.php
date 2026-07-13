<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Phase 7 (final) — Export Service becomes the owner of export jobs.
 *
 * The monolith admin now creates/lists/reads exports through export-service over
 * HTTP (ExportClient); the async job generation + persistence live in the service.
 * The monolith's export_jobs table and the now-empty `exports` schema are dropped.
 * One-way cutover, like the cart cutover (Version20260703220000).
 */
final class Version20260713130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Drop monolith export_jobs table + exports schema (Export Service is now the source of truth).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('DROP TABLE exports.export_jobs');
        $this->addSql('DROP SCHEMA exports');
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException(
            'Export cutover is one-way; export jobs now live in export-service.'
        );
    }
}

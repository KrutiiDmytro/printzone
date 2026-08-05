<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Phase 2.2 — database-per-service: split the single public schema into one
 * PostgreSQL schema per bounded context. Data-preserving: tables are MOVED with
 * ALTER TABLE ... SET SCHEMA (no DROP/CREATE), so existing rows are kept.
 *
 * Infrastructure tables (messenger_messages, doctrine_migration_versions) stay
 * in public. There are no cross-schema FKs (removed in Phase 1), so no constraint
 * has to be dropped here.
 */
final class Version20260616120000 extends AbstractMigration
{
    /** @var array<string, string[]> schema => tables owned by it */
    private const LAYOUT = [
        'users' => ['users'],
        'catalog' => ['categories', 'brands', 'products', 'printer_models', 'product_attributes'],
        'cart' => ['carts', 'cart_items'],
        'orders' => ['orders', 'order_items'],
        'exports' => ['export_jobs'],
        'messaging' => ['outbox'],
    ];

    /**
     * Auto-generated index/constraint names are derived from the (now schema-qualified)
     * table name, so SET SCHEMA leaves them stale. Re-align them with the mapping.
     *
     * @var array<array{0: string, 1: string, 2: string}> [schema, current name, new name]
     */
    private const INDEX_RENAMES = [
        ['catalog', 'uniq_7ea24434989d9b62', 'UNIQ_86B987E2989D9B62'],
        ['catalog', 'uniq_3af34668989d9b62', 'UNIQ_B0753980989D9B62'],
        ['catalog', 'uniq_d18f97fa989d9b62', 'UNIQ_FD5D84B3989D9B62'],
        ['catalog', 'idx_d18f97fa44f5d008', 'IDX_FD5D84B344F5D008'],
        ['catalog', 'idx_b3ba5a5a12469de2', 'IDX_66B553D212469DE2'],
        ['catalog', 'idx_b3ba5a5a44f5d008', 'IDX_66B553D244F5D008'],
        ['users', 'uniq_1483a5e9e7927c74', 'UNIQ_338ADFC4E7927C74'],
        ['cart', 'uniq_4e004aaca76ed395', 'UNIQ_D194F9C0A76ED395'],
    ];

    public function getDescription(): string
    {
        return 'Phase 2.2: move each module\'s tables into its own PostgreSQL schema (database-per-service)';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(
            !$this->platform instanceof \Doctrine\DBAL\Platforms\PostgreSQLPlatform,
            'Phase 2.2 schema split is PostgreSQL-only (ALTER TABLE ... SET SCHEMA).'
        );

        foreach (self::LAYOUT as $schemaName => $tables) {
            $this->addSql(sprintf('CREATE SCHEMA IF NOT EXISTS %s', $schemaName));
            foreach ($tables as $table) {
                $this->addSql(sprintf('ALTER TABLE %s SET SCHEMA %s', $table, $schemaName));
            }
        }

        foreach (self::INDEX_RENAMES as [$schemaName, $current, $new]) {
            $this->addSql(sprintf('ALTER INDEX %s.%s RENAME TO %s', $schemaName, $current, $new));
        }
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(
            !$this->platform instanceof \Doctrine\DBAL\Platforms\PostgreSQLPlatform,
            'Phase 2.2 schema split is PostgreSQL-only (ALTER TABLE ... SET SCHEMA).'
        );

        foreach (self::INDEX_RENAMES as [$schemaName, $current, $new]) {
            $this->addSql(sprintf('ALTER INDEX %s.%s RENAME TO %s', $schemaName, $new, $current));
        }

        foreach (self::LAYOUT as $schemaName => $tables) {
            foreach ($tables as $table) {
                $this->addSql(sprintf('ALTER TABLE %s.%s SET SCHEMA public', $schemaName, $table));
            }
            $this->addSql(sprintf('DROP SCHEMA IF EXISTS %s', $schemaName));
        }
    }
}

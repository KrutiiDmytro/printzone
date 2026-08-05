<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Phase 4.5 step 5.2 — Catalog becomes the single source of truth.
 *
 * The storefront/cart already read from catalog-service (steps 1-4) and the admin
 * now writes there (steps 5.1/5.3), so the monolith's catalog tables are dropped:
 * products, categories, brands and product_attributes.
 *
 * printer_models stays in the monolith (printer-finder) but loses its FK to the
 * (now gone) brands table — it keeps brand_slug/brand_name snapshots instead
 * (slug is the storefront key). One-way cutover, like the UUID migration.
 */
final class Version20260619120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Drop monolith catalog tables; decouple printer_models from brands (snapshots).';
    }

    public function up(Schema $schema): void
    {
        // Decouple printer_models from the brands FK (DROP COLUMN removes the FK too).
        $this->addSql('ALTER TABLE catalog.printer_models DROP COLUMN brand_id');
        $this->addSql("ALTER TABLE catalog.printer_models ADD brand_slug VARCHAR(255) NOT NULL DEFAULT ''");
        $this->addSql("ALTER TABLE catalog.printer_models ADD brand_name VARCHAR(255) NOT NULL DEFAULT ''");
        $this->addSql('ALTER TABLE catalog.printer_models ALTER COLUMN brand_slug DROP DEFAULT');
        $this->addSql('ALTER TABLE catalog.printer_models ALTER COLUMN brand_name DROP DEFAULT');

        // Drop catalog tables in FK-safe order (children first).
        $this->addSql('DROP TABLE catalog.product_attributes');
        $this->addSql('DROP TABLE catalog.products');
        $this->addSql('DROP TABLE catalog.categories');
        $this->addSql('DROP TABLE catalog.brands');
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException(
            'Catalog cutover is one-way; restore catalog tables from a backup to revert.'
        );
    }
}

<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Phase 1 decomposition — decouple order_items from the Catalog domain.
 *
 * Replaces the Doctrine FK order_items.product_id → products with a plain scalar
 * reference and adds a product_name snapshot (price snapshot already exists).
 * The product_id column is kept (now without a FK constraint).
 */
final class Version20260612160001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Phase 1: decouple order_items from products (snapshot name, drop FK)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE order_items ADD product_name VARCHAR(255) DEFAULT '' NOT NULL");
        // Backfill the snapshot for existing rows from current product data.
        $this->addSql('UPDATE order_items oi SET product_name = p.name FROM products p WHERE oi.product_id = p.id');
        $this->addSql('ALTER TABLE order_items ALTER product_name DROP DEFAULT');
        // Drop the cross-domain FK to products (order→catalog coupling). Keep the FK to orders.
        $this->addSql('ALTER TABLE order_items DROP CONSTRAINT fk_62809db04584665a');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE order_items ADD CONSTRAINT fk_62809db04584665a FOREIGN KEY (product_id) REFERENCES products (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE order_items DROP product_name');
    }
}

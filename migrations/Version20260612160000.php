<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Phase 1 decomposition — decouple cart_items from the Catalog domain.
 *
 * Replaces the Doctrine FK cart_items.product_id → products with a plain scalar
 * reference, and adds product_name + price snapshots taken when the item is added.
 * The product_id column itself is kept (now without a FK constraint).
 */
final class Version20260612160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Phase 1: decouple cart_items from products (snapshot name+price, drop FK)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE cart_items ADD product_name VARCHAR(255) DEFAULT '' NOT NULL");
        $this->addSql('ALTER TABLE cart_items ADD price INT DEFAULT 0 NOT NULL');
        // Backfill snapshots for any existing rows from the current product data.
        $this->addSql('UPDATE cart_items ci SET product_name = p.name, price = p.price FROM products p WHERE ci.product_id = p.id');
        $this->addSql('ALTER TABLE cart_items ALTER product_name DROP DEFAULT');
        $this->addSql('ALTER TABLE cart_items ALTER price DROP DEFAULT');
        // Drop the cross-domain FK to products (cart→catalog coupling). Keep the FK to carts.
        $this->addSql('ALTER TABLE cart_items DROP CONSTRAINT fk_bef484454584665a');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE cart_items ADD CONSTRAINT fk_bef484454584665a FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE cart_items DROP product_name');
        $this->addSql('ALTER TABLE cart_items DROP price');
    }
}

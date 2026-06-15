<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Phase 2.1 — serial int → UUID primary keys (clean cutover).
 *
 * Drops every application table and recreates it with UUID PKs and UUID
 * cross-references. This is a destructive cutover: all existing rows are
 * discarded (acceptable — data was fixtures/demo only). Reload fixtures
 * afterwards if seed data is needed.
 */
final class Version20260615211500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Cutover: migrate all primary keys from serial int to UUID';
    }

    public function up(Schema $schema): void
    {
        // Drop all existing (int-PK) tables; CASCADE clears cross-table FKs.
        foreach ([
            'cart_items', 'carts', 'order_items', 'orders', 'product_attributes',
            'products', 'printer_models', 'categories', 'brands', 'users',
            'export_jobs', 'outbox', 'messenger_messages',
        ] as $table) {
            $this->addSql(sprintf('DROP TABLE IF EXISTS %s CASCADE', $table));
        }

        // Recreate with UUID PKs (generated in PHP via symfony/uid).
        $this->addSql('CREATE TABLE brands (id UUID NOT NULL, name VARCHAR(255) NOT NULL, slug VARCHAR(255) NOT NULL, color VARCHAR(7) DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_7EA24434989D9B62 ON brands (slug)');
        $this->addSql('COMMENT ON COLUMN brands.id IS \'(DC2Type:uuid)\'');
        $this->addSql('CREATE TABLE categories (id UUID NOT NULL, parent_id UUID DEFAULT NULL, name VARCHAR(255) NOT NULL, slug VARCHAR(255) NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_3AF34668989D9B62 ON categories (slug)');
        $this->addSql('CREATE INDEX idx_categories_parent_id ON categories (parent_id)');
        $this->addSql('COMMENT ON COLUMN categories.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN categories.parent_id IS \'(DC2Type:uuid)\'');
        $this->addSql('CREATE TABLE printer_models (id UUID NOT NULL, brand_id UUID NOT NULL, name VARCHAR(255) NOT NULL, slug VARCHAR(255) NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_D18F97FA989D9B62 ON printer_models (slug)');
        $this->addSql('CREATE INDEX IDX_D18F97FA44F5D008 ON printer_models (brand_id)');
        $this->addSql('COMMENT ON COLUMN printer_models.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN printer_models.brand_id IS \'(DC2Type:uuid)\'');
        $this->addSql('CREATE TABLE products (id UUID NOT NULL, category_id UUID NOT NULL, brand_id UUID DEFAULT NULL, name VARCHAR(255) NOT NULL, description TEXT DEFAULT NULL, price INT NOT NULL, stock INT NOT NULL, image VARCHAR(255) DEFAULT NULL, is_featured BOOLEAN DEFAULT false NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_B3BA5A5A12469DE2 ON products (category_id)');
        $this->addSql('CREATE INDEX IDX_B3BA5A5A44F5D008 ON products (brand_id)');
        $this->addSql('CREATE INDEX idx_products_is_featured ON products (is_featured)');
        $this->addSql('CREATE INDEX idx_products_stock ON products (stock)');
        $this->addSql('CREATE INDEX idx_products_category_stock ON products (category_id, stock)');
        $this->addSql('COMMENT ON COLUMN products.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN products.category_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN products.brand_id IS \'(DC2Type:uuid)\'');
        $this->addSql('CREATE TABLE product_attributes (id UUID NOT NULL, product_id UUID NOT NULL, name VARCHAR(255) NOT NULL, value VARCHAR(255) NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX idx_product_attributes_product_id ON product_attributes (product_id)');
        $this->addSql('COMMENT ON COLUMN product_attributes.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN product_attributes.product_id IS \'(DC2Type:uuid)\'');
        $this->addSql('CREATE TABLE users (id UUID NOT NULL, email VARCHAR(180) NOT NULL, roles JSON NOT NULL, password VARCHAR(255) NOT NULL, full_name VARCHAR(255) DEFAULT NULL, google_id VARCHAR(255) DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_1483A5E9E7927C74 ON users (email)');
        $this->addSql('COMMENT ON COLUMN users.id IS \'(DC2Type:uuid)\'');
        $this->addSql('CREATE TABLE orders (id UUID NOT NULL, user_id UUID NOT NULL, user_email VARCHAR(180) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, status VARCHAR(255) NOT NULL, total_amount INT NOT NULL, stripe_session_id VARCHAR(255) DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX idx_orders_created_at ON orders (created_at)');
        $this->addSql('CREATE INDEX idx_orders_status ON orders (status)');
        $this->addSql('CREATE INDEX idx_orders_user_id ON orders (user_id)');
        $this->addSql('COMMENT ON COLUMN orders.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN orders.user_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN orders.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE TABLE order_items (id UUID NOT NULL, order_ref_id UUID NOT NULL, product_id UUID NOT NULL, product_name VARCHAR(255) NOT NULL, quantity INT NOT NULL, price INT NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX idx_order_items_order_ref_id ON order_items (order_ref_id)');
        $this->addSql('CREATE INDEX idx_order_items_product_id ON order_items (product_id)');
        $this->addSql('COMMENT ON COLUMN order_items.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN order_items.order_ref_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN order_items.product_id IS \'(DC2Type:uuid)\'');
        $this->addSql('CREATE TABLE carts (id UUID NOT NULL, user_id UUID NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_4E004AACA76ED395 ON carts (user_id)');
        $this->addSql('CREATE INDEX idx_carts_updated_at ON carts (updated_at)');
        $this->addSql('COMMENT ON COLUMN carts.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN carts.user_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN carts.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE TABLE cart_items (id UUID NOT NULL, cart_id UUID NOT NULL, product_id UUID NOT NULL, product_name VARCHAR(255) NOT NULL, price INT NOT NULL, quantity INT NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX idx_cart_items_cart_id ON cart_items (cart_id)');
        $this->addSql('CREATE INDEX idx_cart_items_product_id ON cart_items (product_id)');
        $this->addSql('COMMENT ON COLUMN cart_items.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN cart_items.cart_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN cart_items.product_id IS \'(DC2Type:uuid)\'');
        $this->addSql('CREATE TABLE export_jobs (id UUID NOT NULL, type VARCHAR(50) NOT NULL, format VARCHAR(10) NOT NULL, status VARCHAR(20) NOT NULL, file_path VARCHAR(500) DEFAULT NULL, filters JSON DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, completed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, error_message TEXT DEFAULT NULL, requested_by VARCHAR(180) NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX idx_export_jobs_status ON export_jobs (status)');
        $this->addSql('CREATE INDEX idx_export_jobs_created_at ON export_jobs (created_at)');
        $this->addSql('COMMENT ON COLUMN export_jobs.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN export_jobs.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN export_jobs.completed_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE TABLE outbox (id UUID NOT NULL, aggregate VARCHAR(100) NOT NULL, event_name VARCHAR(100) NOT NULL, payload JSON NOT NULL, trace_id VARCHAR(64) DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, published_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX idx_outbox_unpublished ON outbox (published_at, created_at)');
        $this->addSql('COMMENT ON COLUMN outbox.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN outbox.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN outbox.published_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE TABLE messenger_messages (id BIGSERIAL NOT NULL, body TEXT NOT NULL, headers TEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, available_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, delivered_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750 ON messenger_messages (queue_name, available_at, delivered_at, id)');
        $this->addSql('COMMENT ON COLUMN messenger_messages.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN messenger_messages.available_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN messenger_messages.delivered_at IS \'(DC2Type:datetime_immutable)\'');

        $this->addSql('ALTER TABLE categories ADD CONSTRAINT FK_3AF34668727ACA70 FOREIGN KEY (parent_id) REFERENCES categories (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE printer_models ADD CONSTRAINT FK_D18F97FA44F5D008 FOREIGN KEY (brand_id) REFERENCES brands (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE products ADD CONSTRAINT FK_B3BA5A5A12469DE2 FOREIGN KEY (category_id) REFERENCES categories (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE products ADD CONSTRAINT FK_B3BA5A5A44F5D008 FOREIGN KEY (brand_id) REFERENCES brands (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE product_attributes ADD CONSTRAINT FK_A2FCC15B4584665A FOREIGN KEY (product_id) REFERENCES products (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE order_items ADD CONSTRAINT FK_62809DB0E238517C FOREIGN KEY (order_ref_id) REFERENCES orders (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE cart_items ADD CONSTRAINT FK_BEF484451AD5CDBF FOREIGN KEY (cart_id) REFERENCES carts (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException('UUID cutover is one-way; restore from a backup to revert.');
    }
}

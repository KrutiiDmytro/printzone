<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Initial order-service schema: orders, order_items, and the messaging outbox.
 */
final class Version20260624152520 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Initial order-service schema (orders, order_items, outbox)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE order_items (id UUID NOT NULL, order_ref_id UUID NOT NULL, product_id UUID NOT NULL, product_name VARCHAR(255) NOT NULL, quantity INT NOT NULL, price INT NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX idx_order_items_order_ref_id ON order_items (order_ref_id)');
        $this->addSql('CREATE INDEX idx_order_items_product_id ON order_items (product_id)');
        $this->addSql('COMMENT ON COLUMN order_items.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN order_items.order_ref_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN order_items.product_id IS \'(DC2Type:uuid)\'');
        $this->addSql('CREATE TABLE orders (id UUID NOT NULL, user_id UUID NOT NULL, user_email VARCHAR(180) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, status VARCHAR(255) NOT NULL, total_amount INT NOT NULL, stripe_session_id VARCHAR(255) DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX idx_orders_created_at ON orders (created_at)');
        $this->addSql('CREATE INDEX idx_orders_status ON orders (status)');
        $this->addSql('CREATE INDEX idx_orders_user_id ON orders (user_id)');
        $this->addSql('COMMENT ON COLUMN orders.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN orders.user_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN orders.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE TABLE outbox (id UUID NOT NULL, aggregate VARCHAR(100) NOT NULL, event_name VARCHAR(100) NOT NULL, payload JSON NOT NULL, trace_id VARCHAR(64) DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, published_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX idx_outbox_unpublished ON outbox (published_at, created_at)');
        $this->addSql('COMMENT ON COLUMN outbox.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN outbox.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN outbox.published_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE order_items ADD CONSTRAINT FK_62809DB0E238517C FOREIGN KEY (order_ref_id) REFERENCES orders (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE order_items DROP CONSTRAINT FK_62809DB0E238517C');
        $this->addSql('DROP TABLE order_items');
        $this->addSql('DROP TABLE orders');
        $this->addSql('DROP TABLE outbox');
    }
}

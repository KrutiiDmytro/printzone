<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Drop the monolith's order tables — order-service is now the source of truth
 * for orders (Strangler step 3). The `orders` schema itself is left in place
 * (it held only these two tables). Deploy order-service BEFORE this runs.
 */
final class Version20260625071542 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Drop monolith orders.orders + orders.order_items (order-service is source of truth)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE orders.order_items DROP CONSTRAINT fk_62809db0e238517c');
        $this->addSql('DROP TABLE orders.order_items');
        $this->addSql('DROP TABLE orders.orders');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('CREATE TABLE orders.order_items (id UUID NOT NULL, order_ref_id UUID NOT NULL, product_id UUID NOT NULL, product_name VARCHAR(255) NOT NULL, quantity INT NOT NULL, price INT NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX idx_order_items_order_ref_id ON orders.order_items (order_ref_id)');
        $this->addSql('CREATE INDEX idx_order_items_product_id ON orders.order_items (product_id)');
        $this->addSql('COMMENT ON COLUMN orders.order_items.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN orders.order_items.order_ref_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN orders.order_items.product_id IS \'(DC2Type:uuid)\'');
        $this->addSql('CREATE TABLE orders.orders (id UUID NOT NULL, user_id UUID NOT NULL, user_email VARCHAR(180) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, status VARCHAR(255) NOT NULL, total_amount INT NOT NULL, stripe_session_id VARCHAR(255) DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX idx_orders_created_at ON orders.orders (created_at)');
        $this->addSql('CREATE INDEX idx_orders_status ON orders.orders (status)');
        $this->addSql('CREATE INDEX idx_orders_user_id ON orders.orders (user_id)');
        $this->addSql('COMMENT ON COLUMN orders.orders.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN orders.orders.user_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN orders.orders.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE orders.order_items ADD CONSTRAINT fk_62809db0e238517c FOREIGN KEY (order_ref_id) REFERENCES orders.orders (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }
}

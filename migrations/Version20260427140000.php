<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260427140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Rename auto-generated Doctrine FK indexes to human-readable names';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER INDEX idx_3af34668727aca70 RENAME TO idx_categories_parent_id');
        $this->addSql('ALTER INDEX idx_e52ffdeea76ed395 RENAME TO idx_orders_user_id');
        $this->addSql('ALTER INDEX idx_bef484451ad5cdbf RENAME TO idx_cart_items_cart_id');
        $this->addSql('ALTER INDEX idx_bef484454584665a RENAME TO idx_cart_items_product_id');
        $this->addSql('ALTER INDEX idx_62809db0e238517c RENAME TO idx_order_items_order_ref_id');
        $this->addSql('ALTER INDEX idx_62809db04584665a RENAME TO idx_order_items_product_id');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER INDEX idx_categories_parent_id RENAME TO idx_3af34668727aca70');
        $this->addSql('ALTER INDEX idx_orders_user_id RENAME TO idx_e52ffdeea76ed395');
        $this->addSql('ALTER INDEX idx_cart_items_cart_id RENAME TO idx_bef484451ad5cdbf');
        $this->addSql('ALTER INDEX idx_cart_items_product_id RENAME TO idx_bef484454584665a');
        $this->addSql('ALTER INDEX idx_order_items_order_ref_id RENAME TO idx_62809db0e238517c');
        $this->addSql('ALTER INDEX idx_order_items_product_id RENAME TO idx_62809db04584665a');
    }
}

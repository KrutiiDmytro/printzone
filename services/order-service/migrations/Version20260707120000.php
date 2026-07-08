<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Adds orders.shipping_address (delivery snapshot captured at checkout).
 * Nullable so pre-existing orders stay valid; carried into OrderPaid for
 * delivery-service.
 */
final class Version20260707120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add orders.shipping_address (delivery snapshot for delivery-service)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE orders ADD shipping_address JSON DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE orders DROP shipping_address');
    }
}

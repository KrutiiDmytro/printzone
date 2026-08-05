<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Strangler-Fig step 4 — Cart Service becomes the owner of the persistent cart.
 *
 * The monolith now reads/writes the authenticated user's cart through cart-service
 * over HTTP (CartClient); guest carts stay in the Symfony session. The monolith's
 * cart tables (cart.carts, cart.cart_items) and the now-empty `cart` schema are
 * dropped. One-way cutover, like the catalog cutover (Version20260619120000).
 */
final class Version20260703220000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Drop monolith cart tables + cart schema (Cart Service is now the source of truth).';
    }

    public function up(Schema $schema): void
    {
        // Children first (cart_items FK-references carts), then the schema itself.
        $this->addSql('DROP TABLE cart.cart_items');
        $this->addSql('DROP TABLE cart.carts');
        $this->addSql('DROP SCHEMA cart');
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException(
            'Cart cutover is one-way; the persistent cart now lives in cart-service.'
        );
    }
}

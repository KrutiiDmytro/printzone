<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260618085405 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create stock_reservations for the checkout Saga (HELD/COMMITTED/RELEASED).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE stock_reservations (id UUID NOT NULL, order_id UUID NOT NULL, product_id UUID NOT NULL, quantity INT NOT NULL, status VARCHAR(20) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        // Idempotency key for at-least-once delivery: one reservation per (order, product).
        $this->addSql('CREATE UNIQUE INDEX uniq_reservation_order_product ON stock_reservations (order_id, product_id)');
        $this->addSql('COMMENT ON COLUMN stock_reservations.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN stock_reservations.order_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN stock_reservations.product_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN stock_reservations.created_at IS \'(DC2Type:datetime_immutable)\'');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE stock_reservations');
    }
}

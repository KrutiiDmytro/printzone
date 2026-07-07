<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Initial delivery-service schema: shipments, event-sourced tracking_events,
 * and the messaging outbox.
 */
final class Version20260707175017 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Initial delivery-service schema (shipments, tracking_events, outbox)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE outbox (id UUID NOT NULL, aggregate VARCHAR(100) NOT NULL, event_name VARCHAR(100) NOT NULL, payload JSON NOT NULL, trace_id VARCHAR(64) DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, published_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX idx_outbox_unpublished ON outbox (published_at, created_at)');
        $this->addSql('COMMENT ON COLUMN outbox.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN outbox.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN outbox.published_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE TABLE shipments (id UUID NOT NULL, order_id UUID NOT NULL, provider VARCHAR(50) NOT NULL, tracking_number VARCHAR(100) DEFAULT NULL, status VARCHAR(32) NOT NULL, address JSON NOT NULL, estimated_at DATE DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX idx_shipments_tracking ON shipments (tracking_number)');
        $this->addSql('CREATE INDEX idx_shipments_status ON shipments (status)');
        $this->addSql('CREATE UNIQUE INDEX uniq_shipments_order_id ON shipments (order_id)');
        $this->addSql('COMMENT ON COLUMN shipments.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN shipments.order_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN shipments.estimated_at IS \'(DC2Type:date_immutable)\'');
        $this->addSql('COMMENT ON COLUMN shipments.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE TABLE tracking_events (id UUID NOT NULL, shipment_id UUID NOT NULL, status VARCHAR(100) NOT NULL, location VARCHAR(255) DEFAULT NULL, description TEXT DEFAULT NULL, occurred_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, recorded_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_FB0BD4067BE036FC ON tracking_events (shipment_id)');
        $this->addSql('CREATE INDEX idx_tracking_shipment ON tracking_events (shipment_id, occurred_at)');
        $this->addSql('COMMENT ON COLUMN tracking_events.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN tracking_events.shipment_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN tracking_events.occurred_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN tracking_events.recorded_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE tracking_events ADD CONSTRAINT FK_FB0BD4067BE036FC FOREIGN KEY (shipment_id) REFERENCES shipments (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE tracking_events DROP CONSTRAINT FK_FB0BD4067BE036FC');
        $this->addSql('DROP TABLE outbox');
        $this->addSql('DROP TABLE shipments');
        $this->addSql('DROP TABLE tracking_events');
    }
}

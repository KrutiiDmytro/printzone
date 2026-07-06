<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260706164631 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create transactional outbox table for payment.* integration events';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE outbox (id UUID NOT NULL, aggregate VARCHAR(100) NOT NULL, event_name VARCHAR(100) NOT NULL, payload JSON NOT NULL, trace_id VARCHAR(64) DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, published_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX idx_outbox_unpublished ON outbox (published_at, created_at)');
        $this->addSql('COMMENT ON COLUMN outbox.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN outbox.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN outbox.published_at IS \'(DC2Type:datetime_immutable)\'');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE outbox');
    }
}

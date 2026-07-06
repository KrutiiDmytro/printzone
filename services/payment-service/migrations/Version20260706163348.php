<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260706163348 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create payments table (Stripe checkout sessions), unique per order_id';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE payments (id UUID NOT NULL, order_id UUID NOT NULL, stripe_session_id VARCHAR(255) DEFAULT NULL, amount INT NOT NULL, currency VARCHAR(3) NOT NULL, status VARCHAR(32) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX idx_payments_session ON payments (stripe_session_id)');
        $this->addSql('CREATE INDEX idx_payments_status ON payments (status)');
        $this->addSql('CREATE UNIQUE INDEX uniq_payments_order_id ON payments (order_id)');
        $this->addSql('COMMENT ON COLUMN payments.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN payments.order_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN payments.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN payments.updated_at IS \'(DC2Type:datetime_immutable)\'');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE payments');
    }
}

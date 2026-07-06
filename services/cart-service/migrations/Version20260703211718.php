<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260703211718 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE cart_items (id UUID NOT NULL, cart_id UUID NOT NULL, product_id UUID NOT NULL, product_name VARCHAR(255) NOT NULL, price INT NOT NULL, quantity INT NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX idx_cart_items_cart_id ON cart_items (cart_id)');
        $this->addSql('CREATE INDEX idx_cart_items_product_id ON cart_items (product_id)');
        $this->addSql('COMMENT ON COLUMN cart_items.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN cart_items.cart_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN cart_items.product_id IS \'(DC2Type:uuid)\'');
        $this->addSql('CREATE TABLE carts (id UUID NOT NULL, user_id UUID NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_4E004AACA76ED395 ON carts (user_id)');
        $this->addSql('CREATE INDEX idx_carts_updated_at ON carts (updated_at)');
        $this->addSql('COMMENT ON COLUMN carts.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN carts.user_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN carts.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE cart_items ADD CONSTRAINT FK_BEF484451AD5CDBF FOREIGN KEY (cart_id) REFERENCES carts (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE cart_items DROP CONSTRAINT FK_BEF484451AD5CDBF');
        $this->addSql('DROP TABLE cart_items');
        $this->addSql('DROP TABLE carts');
    }
}

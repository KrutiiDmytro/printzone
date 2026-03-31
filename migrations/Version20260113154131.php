<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260113154131 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE cart_items DROP CONSTRAINT FK_BEF484454584665A');
        $this->addSql('ALTER TABLE cart_items DROP unit_price');
        $this->addSql('ALTER TABLE cart_items DROP created_at');
        $this->addSql('ALTER TABLE cart_items DROP updated_at');
        $this->addSql('ALTER TABLE cart_items ADD CONSTRAINT FK_BEF484454584665A FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE carts DROP CONSTRAINT FK_4E004AACA76ED395');
        $this->addSql('DROP INDEX idx_4e004aaca76ed395');
        $this->addSql('ALTER TABLE carts DROP created_at');
        $this->addSql('ALTER TABLE carts ALTER user_id SET NOT NULL');
        $this->addSql('ALTER TABLE carts ADD CONSTRAINT FK_4E004AACA76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_4E004AACA76ED395 ON carts (user_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE carts DROP CONSTRAINT fk_4e004aaca76ed395');
        $this->addSql('DROP INDEX UNIQ_4E004AACA76ED395');
        $this->addSql('ALTER TABLE carts ADD created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL');
        $this->addSql('ALTER TABLE carts ALTER user_id DROP NOT NULL');
        $this->addSql('COMMENT ON COLUMN carts.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE carts ADD CONSTRAINT fk_4e004aaca76ed395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX idx_4e004aaca76ed395 ON carts (user_id)');
        $this->addSql('ALTER TABLE cart_items DROP CONSTRAINT fk_bef484454584665a');
        $this->addSql('ALTER TABLE cart_items ADD unit_price INT NOT NULL');
        $this->addSql('ALTER TABLE cart_items ADD created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL');
        $this->addSql('ALTER TABLE cart_items ADD updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL');
        $this->addSql('COMMENT ON COLUMN cart_items.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE cart_items ADD CONSTRAINT fk_bef484454584665a FOREIGN KEY (product_id) REFERENCES products (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }
}

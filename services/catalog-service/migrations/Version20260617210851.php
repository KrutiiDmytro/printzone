<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260617210851 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE brands (id UUID NOT NULL, name VARCHAR(255) NOT NULL, slug VARCHAR(255) NOT NULL, color VARCHAR(7) DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_7EA24434989D9B62 ON brands (slug)');
        $this->addSql('COMMENT ON COLUMN brands.id IS \'(DC2Type:uuid)\'');
        $this->addSql('CREATE TABLE categories (id UUID NOT NULL, parent_id UUID DEFAULT NULL, name VARCHAR(255) NOT NULL, slug VARCHAR(255) NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_3AF34668989D9B62 ON categories (slug)');
        $this->addSql('CREATE INDEX idx_categories_parent_id ON categories (parent_id)');
        $this->addSql('COMMENT ON COLUMN categories.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN categories.parent_id IS \'(DC2Type:uuid)\'');
        $this->addSql('CREATE TABLE products (id UUID NOT NULL, category_id UUID NOT NULL, brand_id UUID DEFAULT NULL, name VARCHAR(255) NOT NULL, description TEXT DEFAULT NULL, price INT NOT NULL, stock INT NOT NULL, image VARCHAR(255) DEFAULT NULL, is_featured BOOLEAN DEFAULT false NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_B3BA5A5A12469DE2 ON products (category_id)');
        $this->addSql('CREATE INDEX IDX_B3BA5A5A44F5D008 ON products (brand_id)');
        $this->addSql('CREATE INDEX idx_products_is_featured ON products (is_featured)');
        $this->addSql('CREATE INDEX idx_products_stock ON products (stock)');
        $this->addSql('COMMENT ON COLUMN products.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN products.category_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN products.brand_id IS \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE categories ADD CONSTRAINT FK_3AF34668727ACA70 FOREIGN KEY (parent_id) REFERENCES categories (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE products ADD CONSTRAINT FK_B3BA5A5A12469DE2 FOREIGN KEY (category_id) REFERENCES categories (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE products ADD CONSTRAINT FK_B3BA5A5A44F5D008 FOREIGN KEY (brand_id) REFERENCES brands (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE categories DROP CONSTRAINT FK_3AF34668727ACA70');
        $this->addSql('ALTER TABLE products DROP CONSTRAINT FK_B3BA5A5A12469DE2');
        $this->addSql('ALTER TABLE products DROP CONSTRAINT FK_B3BA5A5A44F5D008');
        $this->addSql('DROP TABLE brands');
        $this->addSql('DROP TABLE categories');
        $this->addSql('DROP TABLE products');
    }
}

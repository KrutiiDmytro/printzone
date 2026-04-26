<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260426194428 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add indexes on products(isFeatured, stock, category_id+stock) and carts(updatedAt)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE INDEX idx_products_is_featured ON products (is_featured)');
        $this->addSql('CREATE INDEX idx_products_stock ON products (stock)');
        $this->addSql('CREATE INDEX idx_products_category_stock ON products (category_id, stock)');
        $this->addSql('CREATE INDEX idx_carts_updated_at ON carts (updated_at)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_products_is_featured');
        $this->addSql('DROP INDEX idx_products_stock');
        $this->addSql('DROP INDEX idx_products_category_stock');
        $this->addSql('DROP INDEX idx_carts_updated_at');
    }
}

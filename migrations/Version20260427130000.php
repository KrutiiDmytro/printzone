<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260427130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add indexes on categories(parent_id) and orders(user_id)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE INDEX idx_categories_parent_id ON categories (parent_id)');
        $this->addSql('CREATE INDEX idx_orders_user_id ON orders (user_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_categories_parent_id');
        $this->addSql('DROP INDEX idx_orders_user_id');
    }
}

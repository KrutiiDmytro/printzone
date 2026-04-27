<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260427150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Rename auto-generated Doctrine FK index on product_attributes(product_id)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER INDEX idx_a2fcc15b4584665a RENAME TO idx_product_attributes_product_id');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER INDEX idx_product_attributes_product_id RENAME TO idx_a2fcc15b4584665a');
    }
}

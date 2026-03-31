<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migration for product_attributes table
 */
final class Version20260115120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create product_attributes table for product specifications';
    }

    public function up(Schema $schema): void
    {
        // Создаём таблицу только если её нет
        $this->addSql('CREATE TABLE IF NOT EXISTS product_attributes (
            id SERIAL NOT NULL,
            product_id INT NOT NULL,
            name VARCHAR(255) NOT NULL,
            value VARCHAR(255) NOT NULL,
            PRIMARY KEY(id)
        )');
        
        // Создаём индекс (игнорируем если уже есть)
        $this->addSql('DO $$
        BEGIN
            IF NOT EXISTS (SELECT 1 FROM pg_indexes WHERE indexname = \'idx_f2dcf7334584665a\') THEN
                CREATE INDEX IDX_F2DCF7334584665A ON product_attributes (product_id);
            END IF;
        END $$');
        
        // Добавляем constraint (игнорируем если уже есть)
        $this->addSql('DO $$
        BEGIN
            IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = \'fk_f2dcf7334584665a\') THEN
                ALTER TABLE product_attributes ADD CONSTRAINT FK_F2DCF7334584665A
                FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE;
            END IF;
        END $$');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS product_attributes CASCADE');
    }
}
<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260528120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Seed initial brands data';
    }

    public function up(Schema $schema): void
    {
        $brands = [
            ['Samsung', 'samsung', '#1428A0'],
            ['Dell',    'dell',    '#007DB8'],
            ['Sony',    'sony',    '#000000'],
            ['HP',      'hp',      '#0096D6'],
            ['Canon',   'canon',   '#C8102E'],
            ['Epson',   'epson',   '#003087'],
            ['Brother', 'brother', '#005BAC'],
        ];

        foreach ($brands as [$name, $slug, $color]) {
            $this->addSql(
                'INSERT INTO brands (name, slug, color) SELECT :name, :slug, :color WHERE NOT EXISTS (SELECT 1 FROM brands WHERE slug = :slug)',
                ['name' => $name, 'slug' => $slug, 'color' => $color]
            );
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DELETE FROM brands WHERE slug IN ('samsung','dell','sony','hp','canon','epson','brother')");
    }
}

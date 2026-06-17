<?php

namespace App\DataFixtures;

use App\Entity\Brand;
use App\Entity\Category;
use App\Entity\Product;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

/**
 * Canonical catalog dataset (mirrors the monolith slugs so storefront links keep
 * working after the cutover). Printer models stay in the monolith.
 */
class CatalogFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $rootCategories = [
            'Inkjet Printing' => 'inkjet-printing',
            'Laser Printing' => 'laser-printing',
            'Dot Matrix' => 'dot-matrix',
            'Photo Paper' => 'photo-paper',
        ];
        $childCategories = [
            'inkjet-printing' => ['Ink' => 'ink', 'CISS' => 'ciss', 'Cartridges' => 'cartridges-inkjet'],
            'laser-printing' => ['Cartridges' => 'cartridges-laser', 'Toner' => 'toner', 'Drum Units' => 'drum-units'],
            'dot-matrix' => ['Cartridges' => 'cartridges-dot', 'Ribbons' => 'ribbons'],
            'photo-paper' => ['Glossy' => 'glossy', 'Matte' => 'matte', 'Wide Format' => 'wide-format'],
        ];

        $categories = [];
        foreach ($rootCategories as $name => $slug) {
            $category = (new Category())->setName($name)->setSlug($slug);
            $manager->persist($category);
            $categories[$slug] = $category;
        }
        foreach ($childCategories as $parentSlug => $children) {
            foreach ($children as $name => $slug) {
                $category = (new Category())->setName($name)->setSlug($slug)->setParent($categories[$parentSlug]);
                $manager->persist($category);
                $categories[$slug] = $category;
            }
        }

        $brandsData = [
            ['Samsung', 'samsung', '#1428A0'],
            ['Dell', 'dell', '#007DB8'],
            ['Sony', 'sony', '#000000'],
            ['HP', 'hp', '#0096D6'],
            ['Canon', 'canon', '#C8102E'],
            ['Epson', 'epson', '#003087'],
            ['Brother', 'brother', '#005BAC'],
        ];
        $brands = [];
        foreach ($brandsData as [$name, $slug, $color]) {
            $brand = (new Brand())->setName($name)->setSlug($slug)->setColor($color);
            $manager->persist($brand);
            $brands[$slug] = $brand;
        }

        $products = [
            ['HP 305 Colour Ink Cartridge', 'Original HP inkjet cartridge for colour printing, up to 100 pages', 1490, 120, 'cartridges-inkjet', 'product-1.png', 'hp'],
            ['Canon PG-445 Black Cartridge', 'Original Canon ink cartridge, yield 180 pages', 1290, 95, 'cartridges-inkjet', 'product-2.png', 'canon'],
            ['Epson 103 Black Ink 65ml', 'Original Epson EcoTank ink, 65 ml bottle', 890, 200, 'ink', 'product-3.png', 'epson'],
            ['Epson CISS for L3100', 'Continuous ink supply system for Epson L3100', 3200, 30, 'ciss', 'product-4.png', 'epson'],
            ['HP 85A Laser Cartridge', 'Original HP LaserJet cartridge CE285A, yield 1600 pages', 2890, 60, 'cartridges-laser', 'product-5.png', 'hp'],
            ['Canon 057H High-Yield Toner', 'Original Canon 057H high-capacity toner, yield 10 000 pages', 4990, 40, 'toner', 'product-6.png', 'canon'],
            ['Brother DR-2335 Drum Unit', 'Original Brother drum unit, yield 12 000 pages', 3490, 25, 'drum-units', 'product-7.png', 'brother'],
            ['Epson RC-T1BNA Black Ribbon', 'Original Epson fabric ribbon for dot-matrix printers', 690, 150, 'ribbons', 'product-8.png', 'epson'],
            ['Canon GP-501 Glossy Paper 200g A4', 'Canon Glossy Pro photo paper, 200 g/m2, 50 sheets', 990, 80, 'glossy', 'product-9.png', 'canon'],
            ['Epson Ultra Matte A4 190g', 'Epson Ultra matte photo paper, 190 g/m2, 50 sheets', 850, 70, 'matte', 'product-10.png', 'epson'],
        ];
        foreach ($products as [$name, $desc, $price, $stock, $cat, $image, $brand]) {
            $product = (new Product())
                ->setName($name)
                ->setDescription($desc)
                ->setPrice($price)
                ->setStock($stock)
                ->setCategory($categories[$cat])
                ->setBrand($brands[$brand])
                ->setImage($image)
                ->setIsFeatured(in_array($cat, ['cartridges-inkjet', 'toner'], true));
            $manager->persist($product);
        }

        $manager->flush();
    }
}

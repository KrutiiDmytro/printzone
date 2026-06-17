<?php

namespace App\DataFixtures;

use App\Entity\Brand;
use App\Entity\Category;
use App\Entity\Product;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class CatalogFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $brands = [];
        foreach ([['Canon', 'canon', '#cc0000'], ['HP', 'hp', '#0096d6']] as [$name, $slug, $color]) {
            $brand = new Brand();
            $brand->setName($name)->setSlug($slug)->setColor($color);
            $manager->persist($brand);
            $brands[$slug] = $brand;
        }

        $categories = [];
        foreach ([['Cartridges', 'cartridges'], ['Toners', 'toners']] as [$name, $slug]) {
            $category = new Category();
            $category->setName($name)->setSlug($slug);
            $manager->persist($category);
            $categories[$slug] = $category;
        }

        $products = [
            ['Canon PG-540 Black', 'cartridges', 'canon', 1999, 42, true],
            ['Canon CL-541 Color', 'cartridges', 'canon', 2499, 30, false],
            ['HP 305 Black', 'cartridges', 'hp', 1799, 0, false],
            ['HP 305 Color', 'cartridges', 'hp', 2099, 15, true],
            ['Canon 737 Toner', 'toners', 'canon', 5999, 12, false],
            ['HP 17A Toner', 'toners', 'hp', 6499, 8, true],
        ];
        foreach ($products as [$name, $cat, $brand, $price, $stock, $featured]) {
            $product = new Product();
            $product->setName($name)
                ->setDescription($name.' — genuine supply.')
                ->setCategory($categories[$cat])
                ->setBrand($brands[$brand])
                ->setPrice($price)
                ->setStock($stock)
                ->setIsFeatured($featured);
            $manager->persist($product);
        }

        $manager->flush();
    }
}

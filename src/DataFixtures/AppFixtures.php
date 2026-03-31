<?php

namespace App\DataFixtures;

use App\Catalog\Domain\Entity\Category;
use App\Catalog\Domain\Entity\Product;
use App\Catalog\Domain\Entity\ProductAttribute;
use App\User\Domain\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{
    public function __construct(
        private UserPasswordHasherInterface $passwordHasher
    ) {}

    public function load(ObjectManager $manager): void
    {
        // Create Categories
        $categories = [
            'Smartphones' => 'smartphones',
            'Laptops' => 'laptops',
            'Tablets' => 'tablets',
            'Accessories' => 'accessories',
            'Smart Watches' => 'smart-watches',
            'Headphones' => 'headphones',
            'Cameras' => 'cameras',
            'Gaming Consoles' => 'gaming',
            'Audio' => 'audio',
            'Home Appliances' => 'home-appliances',
            'Storage' => 'storage',
            'PC Components' => 'pc-components',
            'Monitors' => 'monitors',
            'Printers' => 'printers',
            'Networking' => 'networking',
            'Software' => 'software',
            'Smart TV' => 'smart-tv',
            'Computer' => 'computer',
            
        ];

        $categoryObjects = [];
        foreach ($categories as $name => $slug) {
            $category = new Category();
            $category->setName($name);
            $category->setSlug($slug);
            $manager->persist($category);
            $categoryObjects[$slug] = $category;
        }

        // Create Products
        $products = [
            [
                'name' => 'iPhone 15 Pro',
                'description' => 'Latest iPhone with A17 Pro chip and titanium design',
                'price' => 99900, // $999.00 in cents
                'stock' => 50,
                'category' => 'smartphones',
                'image' => 'product-1.png'
            ],
            [
                'name' => 'Samsung Galaxy S24',
                'description' => 'Flagship Android phone with amazing camera',
                'price' => 89900,
                'stock' => 45,
                'category' => 'smartphones',
                'image' => 'product-2.png'
            ],
            [
                'name' => 'MacBook Pro 16"',
                'description' => 'Powerful laptop with M3 Pro chip',
                'price' => 249900,
                'stock' => 20,
                'category' => 'laptops',
                'image' => 'product-3.png'
            ],
            [
                'name' => 'Dell XPS 15',
                'description' => 'Premium Windows laptop for professionals',
                'price' => 189900,
                'stock' => 25,
                'category' => 'laptops',
                'image' => 'product-4.png'
            ],
            [
                'name' => 'iPad Air',
                'description' => 'Versatile tablet with M1 chip',
                'price' => 59900,
                'stock' => 35,
                'category' => 'tablets',
                'image' => 'product-5.png'
            ],
            [
                'name' => 'Samsung Galaxy Tab S9',
                'description' => 'Android tablet with S Pen included',
                'price' => 79900,
                'stock' => 30,
                'category' => 'tablets',
                'image' => 'product-6.png'
            ],
            [
                'name' => 'Apple Watch Series 9',
                'description' => 'Advanced health and fitness tracking',
                'price' => 39900,
                'stock' => 60,
                'category' => 'smart-watches',
                'image' => 'product-7.png'
            ],
            [
                'name' => 'AirPods Pro 2',
                'description' => 'Premium wireless earbuds with ANC',
                'price' => 24900,
                'stock' => 100,
                'category' => 'headphones',
                'image' => 'product-8.png'
            ],
            [
                'name' => 'Sony WH-1000XM5',
                'description' => 'Industry-leading noise cancellation headphones',
                'price' => 39900,
                'stock' => 40,
                'category' => 'headphones',
                'image' => 'product-9.png'
            ],
            [
                'name' => 'Magic Keyboard',
                'description' => 'Wireless keyboard for iPad and Mac',
                'price' => 9900,
                'stock' => 75,
                'category' => 'accessories',
                'image' => 'product-10.png'
            ],
        ];

        $productObjects = []; // Сохраняем продукты для атрибутов

        foreach ($products as $index => $productData) {
            $product = new Product();
            $product->setName($productData['name']);
            $product->setDescription($productData['description']);
            $product->setPrice($productData['price']);
            $product->setStock($productData['stock']);
            $product->setCategory($categoryObjects[$productData['category']]);
            $product->setImage($productData['image']);
            $manager->persist($product);
            
            $productObjects[$index] = $product; // Сохраняем
        }

        // Create test administrator
        $user = new User();
        $user->setEmail('admin@example.com');
        $user->setFullName('Admin User');
        $user->setRoles(['ROLE_ADMIN']);
        $hashedPassword = $this->passwordHasher->hashPassword($user, 'admin123');
        $user->setPassword($hashedPassword);
        $manager->persist($user);

        // Create test user
        $regularUser = new User();
        $regularUser->setEmail('user@example.com');
        $regularUser->setFullName('Regular User');
        $regularUser->setRoles(['ROLE_USER']);
        $hashedPassword = $this->passwordHasher->hashPassword($regularUser, 'user123');
        $regularUser->setPassword($hashedPassword);
        $manager->persist($regularUser);

        // Добавляем атрибуты к продуктам
        // iPhone 15 Pro (индекс 0)
        $attr1 = new ProductAttribute();
        $attr1->setProduct($productObjects[0]);
        $attr1->setName('Color');
        $attr1->setValue('Titanium Black');
        $manager->persist($attr1);

        $attr2 = new ProductAttribute();
        $attr2->setProduct($productObjects[0]);
        $attr2->setName('Storage');
        $attr2->setValue('256GB');
        $manager->persist($attr2);

        // MacBook Pro (индекс 2)
        $attr3 = new ProductAttribute();
        $attr3->setProduct($productObjects[2]);
        $attr3->setName('RAM');
        $attr3->setValue('16GB');
        $manager->persist($attr3);

        $attr4 = new ProductAttribute();
        $attr4->setProduct($productObjects[2]);
        $attr4->setName('SSD');
        $attr4->setValue('512GB');
        $manager->persist($attr4);

        // Apple Watch (индекс 6)
        $attr5 = new ProductAttribute();
        $attr5->setProduct($productObjects[6]);
        $attr5->setName('Size');
        $attr5->setValue('45mm');
        $manager->persist($attr5);
            
        $manager->flush();
    }
}

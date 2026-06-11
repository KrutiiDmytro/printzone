<?php

namespace App\DataFixtures;

use App\Catalog\Domain\Entity\Brand;
use App\Catalog\Domain\Entity\Category;
use App\Catalog\Domain\Entity\PrinterModel;
use App\Catalog\Domain\Entity\Product;
use App\User\Domain\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{
    public function __construct(
        private UserPasswordHasherInterface $passwordHasher
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        // Create Categories (4 root + 10 children)
        $rootCategories = [
            'Inkjet Printing' => 'inkjet-printing',
            'Laser Printing' => 'laser-printing',
            'Dot Matrix' => 'dot-matrix',
            'Photo Paper' => 'photo-paper',
        ];
        $childCategories = [
            'inkjet-printing' => ['Ink' => 'ink',          'CISS' => 'ciss',         'Cartridges' => 'cartridges-inkjet'],
            'laser-printing' => ['Cartridges' => 'cartridges-laser', 'Toner' => 'toner',        'Drum Units' => 'drum-units'],
            'dot-matrix' => ['Cartridges' => 'cartridges-dot',   'Ribbons' => 'ribbons'],
            'photo-paper' => ['Glossy' => 'glossy',       'Matte' => 'matte',        'Wide Format' => 'wide-format'],
        ];

        $categoryObjects = [];
        foreach ($rootCategories as $name => $slug) {
            $category = new Category();
            $category->setName($name);
            $category->setSlug($slug);
            $manager->persist($category);
            $categoryObjects[$slug] = $category;
        }
        foreach ($childCategories as $parentSlug => $children) {
            foreach ($children as $name => $slug) {
                $category = new Category();
                $category->setName($name);
                $category->setSlug($slug);
                $category->setParent($categoryObjects[$parentSlug]);
                $manager->persist($category);
                $categoryObjects[$slug] = $category;
            }
        }

        // Create Brands
        $brandsData = [
            ['name' => 'Samsung', 'slug' => 'samsung', 'color' => '#1428A0'],
            ['name' => 'Dell',    'slug' => 'dell',    'color' => '#007DB8'],
            ['name' => 'Sony',    'slug' => 'sony',    'color' => '#000000'],
            ['name' => 'HP',      'slug' => 'hp',      'color' => '#0096D6'],
            ['name' => 'Canon',   'slug' => 'canon',   'color' => '#C8102E'],
            ['name' => 'Epson',   'slug' => 'epson',   'color' => '#003087'],
            ['name' => 'Brother', 'slug' => 'brother', 'color' => '#005BAC'],
        ];

        $brandObjects = [];
        foreach ($brandsData as $brandData) {
            $brand = new Brand();
            $brand->setName($brandData['name']);
            $brand->setSlug($brandData['slug']);
            $brand->setColor($brandData['color']);
            $manager->persist($brand);
            $brandObjects[$brandData['slug']] = $brand;
        }

        // Create Products
        $products = [
            [
                'name' => 'HP 305 Colour Ink Cartridge',
                'description' => 'Original HP inkjet cartridge for colour printing, up to 100 pages',
                'price' => 1490,
                'stock' => 120,
                'category' => 'cartridges-inkjet',
                'image' => 'product-1.png',
                'brand' => 'hp',
            ],
            [
                'name' => 'Canon PG-445 Black Cartridge',
                'description' => 'Original Canon ink cartridge, yield 180 pages',
                'price' => 1290,
                'stock' => 95,
                'category' => 'cartridges-inkjet',
                'image' => 'product-2.png',
                'brand' => 'canon',
            ],
            [
                'name' => 'Epson 103 Black Ink 65ml',
                'description' => 'Original Epson EcoTank ink, 65 ml bottle',
                'price' => 890,
                'stock' => 200,
                'category' => 'ink',
                'image' => 'product-3.png',
                'brand' => 'epson',
            ],
            [
                'name' => 'Epson CISS for L3100',
                'description' => 'Continuous ink supply system for Epson L3100',
                'price' => 3200,
                'stock' => 30,
                'category' => 'ciss',
                'image' => 'product-4.png',
                'brand' => 'epson',
            ],
            [
                'name' => 'HP 85A Laser Cartridge',
                'description' => 'Original HP LaserJet cartridge CE285A, yield 1600 pages',
                'price' => 2890,
                'stock' => 60,
                'category' => 'cartridges-laser',
                'image' => 'product-5.png',
                'brand' => 'hp',
            ],
            [
                'name' => 'Canon 057H High-Yield Toner',
                'description' => 'Original Canon 057H high-capacity toner, yield 10 000 pages',
                'price' => 4990,
                'stock' => 40,
                'category' => 'toner',
                'image' => 'product-6.png',
                'brand' => 'canon',
            ],
            [
                'name' => 'Brother DR-2335 Drum Unit',
                'description' => 'Original Brother drum unit, yield 12 000 pages',
                'price' => 3490,
                'stock' => 25,
                'category' => 'drum-units',
                'image' => 'product-7.png',
                'brand' => 'brother',
            ],
            [
                'name' => 'Epson RC-T1BNA Black Ribbon',
                'description' => 'Original Epson fabric ribbon for dot-matrix printers',
                'price' => 690,
                'stock' => 150,
                'category' => 'ribbons',
                'image' => 'product-8.png',
                'brand' => 'epson',
            ],
            [
                'name' => 'Canon GP-501 Glossy Paper 200g A4',
                'description' => 'Canon Glossy Pro photo paper, 200 g/m², 50 sheets',
                'price' => 990,
                'stock' => 80,
                'category' => 'glossy',
                'image' => 'product-9.png',
                'brand' => 'canon',
            ],
            [
                'name' => 'Epson Ultra Matte A4 190g',
                'description' => 'Epson Ultra matte photo paper, 190 g/m², 50 sheets',
                'price' => 850,
                'stock' => 70,
                'category' => 'matte',
                'image' => 'product-10.png',
                'brand' => 'epson',
            ],
        ];

        foreach ($products as $productData) {
            $product = new Product();
            $product->setName($productData['name']);
            $product->setDescription($productData['description']);
            $product->setPrice($productData['price']);
            $product->setStock($productData['stock']);
            $product->setCategory($categoryObjects[$productData['category']]);
            $product->setImage($productData['image']);
            $product->setBrand($brandObjects[$productData['brand']]);
            $manager->persist($product);
        }

        // Create Printer Models
        $printerModels = [
            'hp' => [
                ['name' => 'LaserJet Pro M404dn',        'slug' => 'hp-laserjet-pro-m404dn'],
                ['name' => 'LaserJet Pro M428fdw',       'slug' => 'hp-laserjet-pro-m428fdw'],
                ['name' => 'Color LaserJet Pro M479fdw', 'slug' => 'hp-color-laserjet-pro-m479fdw'],
                ['name' => 'OfficeJet Pro 9020',         'slug' => 'hp-officejet-pro-9020'],
                ['name' => 'Envy 6020',                  'slug' => 'hp-envy-6020'],
                ['name' => 'DeskJet 2720',               'slug' => 'hp-deskjet-2720'],
            ],
            'canon' => [
                ['name' => 'i-SENSYS LBP243dw',  'slug' => 'canon-i-sensys-lbp243dw'],
                ['name' => 'i-SENSYS LBP246dw',  'slug' => 'canon-i-sensys-lbp246dw'],
                ['name' => 'i-SENSYS MF461dw',   'slug' => 'canon-i-sensys-mf461dw'],
                ['name' => 'PIXMA G550',          'slug' => 'canon-pixma-g550'],
                ['name' => 'PIXMA TS8350',        'slug' => 'canon-pixma-ts8350'],
                ['name' => 'PIXMA TR4650',        'slug' => 'canon-pixma-tr4650'],
            ],
            'epson' => [
                ['name' => 'EcoTank ET-2850',        'slug' => 'epson-ecotank-et-2850'],
                ['name' => 'EcoTank ET-4850',        'slug' => 'epson-ecotank-et-4850'],
                ['name' => 'WorkForce Pro WF-4830',  'slug' => 'epson-workforce-pro-wf-4830'],
                ['name' => 'WorkForce WF-2930',      'slug' => 'epson-workforce-wf-2930'],
                ['name' => 'Expression XP-4200',     'slug' => 'epson-expression-xp-4200'],
                ['name' => 'Expression Home XP-2200', 'slug' => 'epson-expression-home-xp-2200'],
            ],
            'brother' => [
                ['name' => 'HL-L2350DW',   'slug' => 'brother-hl-l2350dw'],
                ['name' => 'HL-L3270CDW',  'slug' => 'brother-hl-l3270cdw'],
                ['name' => 'MFC-L2730DW',  'slug' => 'brother-mfc-l2730dw'],
                ['name' => 'MFC-J5945DW',  'slug' => 'brother-mfc-j5945dw'],
                ['name' => 'DCP-L2550DN',  'slug' => 'brother-dcp-l2550dn'],
                ['name' => 'DCP-J1100DW',  'slug' => 'brother-dcp-j1100dw'],
            ],
            'dell' => [
                ['name' => 'H815dw',   'slug' => 'dell-h815dw'],
                ['name' => 'E525w',    'slug' => 'dell-e525w'],
                ['name' => 'B2360d',   'slug' => 'dell-b2360d'],
                ['name' => 'S2825cdn', 'slug' => 'dell-s2825cdn'],
            ],
        ];

        foreach ($printerModels as $brandSlug => $models) {
            if (!isset($brandObjects[$brandSlug])) {
                continue;
            }
            foreach ($models as $modelData) {
                $model = new PrinterModel();
                $model->setName($modelData['name']);
                $model->setSlug($modelData['slug']);
                $model->setBrand($brandObjects[$brandSlug]);
                $manager->persist($model);
            }
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

        $manager->flush();
    }
}

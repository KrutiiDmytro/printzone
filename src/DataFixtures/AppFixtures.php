<?php

namespace App\DataFixtures;

use App\Catalog\Domain\Entity\PrinterModel;
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
        // Categories, brands and products are owned by catalog-service now. The
        // monolith only seeds printer models (printer-finder) with brand snapshots
        // (slug is the storefront key) and the test users.
        $brandNames = [
            'hp' => 'HP',
            'canon' => 'Canon',
            'epson' => 'Epson',
            'brother' => 'Brother',
            'dell' => 'Dell',
        ];

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
            foreach ($models as $modelData) {
                $model = new PrinterModel();
                $model->setName($modelData['name']);
                $model->setSlug($modelData['slug']);
                $model->setBrandSlug($brandSlug);
                $model->setBrandName($brandNames[$brandSlug]);
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

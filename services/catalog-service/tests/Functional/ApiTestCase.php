<?php

namespace App\Tests\Functional;

use App\Entity\Brand;
use App\Entity\Category;
use App\Entity\Product;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Security\Core\User\InMemoryUser;

abstract class ApiTestCase extends WebTestCase
{
    protected KernelBrowser $client;
    protected EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->client->disableReboot();

        $this->em = static::getContainer()->get(EntityManagerInterface::class);

        $metadata = $this->em->getMetadataFactory()->getAllMetadata();
        $schemaTool = new SchemaTool($this->em);
        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);

        $this->seed();
    }

    private function seed(): void
    {
        $brand = new Brand();
        $brand->setName('Canon')->setSlug('canon')->setColor('#cc0000');
        $this->em->persist($brand);

        $category = new Category();
        $category->setName('Cartridges')->setSlug('cartridges');
        $this->em->persist($category);

        $rows = [
            ['Canon PG-540 Black', 1999, 42, true],
            ['Canon CL-541 Color', 2499, 30, false],
            ['Canon 737 Toner', 5999, 0, false],
        ];
        foreach ($rows as [$name, $price, $stock, $featured]) {
            $product = new Product();
            $product->setName($name)->setCategory($category)->setBrand($brand)
                ->setPrice($price)->setStock($stock)->setIsFeatured($featured);
            $this->em->persist($product);
        }

        $this->em->flush();
    }

    /**
     * Mints a service token signed with the test keypair (the real flow signs
     * with the shared monolith keypair).
     */
    protected function serviceToken(): string
    {
        $jwt = static::getContainer()->get(JWTTokenManagerInterface::class);

        return $jwt->create(new InMemoryUser('service-test', null, ['ROLE_USER']));
    }

    protected function authGet(string $uri): void
    {
        $this->client->request('GET', $uri, [], [], ['HTTP_AUTHORIZATION' => 'Bearer '.$this->serviceToken()]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function json(): array
    {
        return json_decode($this->client->getResponse()->getContent() ?: '{}', true) ?? [];
    }
}

<?php

namespace App\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase as BaseWebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

abstract class WebTestCase extends BaseWebTestCase
{
    public static function createClient(array $options = [], array $server = []): \Symfony\Bundle\FrameworkBundle\KernelBrowser
    {
        $client = parent::createClient($options, $server);
        $client->disableReboot();

        return $client;
    }

    protected function ensureDatabaseExists(): void
    {
        $container = static::getContainer();
        $entityManager = $container->get('doctrine.orm.entity_manager');

        // Перевіряємо, чи існує схема
        $connection = $entityManager->getConnection();
        $schemaManager = $connection->createSchemaManager();

        try {
            $schemaManager->listTables();
        } catch (\Exception $e) {
            // Схема не існує, створюємо її
            $this->createSchema();
            $this->loadFixtures();
        }
    }

    protected function tearDown(): void
    {
        try {
            static::getContainer()
                ->get('doctrine.orm.entity_manager')
                ->getConnection()
                ->close();
        } catch (\Throwable) {
        }
        parent::tearDown();
    }

    protected function createSchema(): void
    {
        $container = static::getContainer();
        $entityManager = $container->get('doctrine.orm.entity_manager');

        $metadatas = $entityManager->getMetadataFactory()->getAllMetadata();

        if (!empty($metadatas)) {
            $schemaTool = new \Doctrine\ORM\Tools\SchemaTool($entityManager);
            try {
                $schemaTool->dropSchema($metadatas);
            } catch (\Exception $e) {
                // Ігноруємо помилки при видаленні (схема може не існувати)
            }
            $schemaTool->createSchema($metadatas);
        }
    }

    protected function loadFixtures(): void
    {
        $container = static::getContainer();
        $entityManager = $container->get('doctrine.orm.entity_manager');
        $passwordHasher = $container->get(UserPasswordHasherInterface::class);

        // Створюємо тестового адміністратора
        $admin = new \App\User\Domain\Entity\User();
        $admin->setEmail('admin@example.com');
        $admin->setFullName('Admin User');
        $admin->setRoles(['ROLE_ADMIN']);
        $admin->setPassword($passwordHasher->hashPassword($admin, 'admin123'));
        $entityManager->persist($admin);

        // Створюємо звичайного користувача
        $user = new \App\User\Domain\Entity\User();
        $user->setEmail('user@example.com');
        $user->setFullName('Regular User');
        $user->setRoles(['ROLE_USER']);
        $user->setPassword($passwordHasher->hashPassword($user, 'user123'));
        $entityManager->persist($user);

        // Створюємо категорії для тестів (потрібні для base.html.twig)
        $categories = [
            ['name' => 'Electronics', 'slug' => 'electronics'],
            ['name' => 'Computers', 'slug' => 'computers'],
            ['name' => 'Phones', 'slug' => 'phones'],
        ];

        foreach ($categories as $catData) {
            $category = new \App\Catalog\Domain\Entity\Category();
            $category->setName($catData['name']);
            $category->setSlug($catData['slug']);
            $entityManager->persist($category);
        }

        $entityManager->flush();
    }

    protected function createAuthenticatedClient(string $email, array $roles = ['ROLE_USER'], string $firewall = 'main')
    {
        $client = static::createClient();

        // Створюємо схему після створення клієнта
        $this->createSchema();
        $this->loadFixtures();

        $container = static::getContainer();
        $userRepository = $container->get('doctrine')->getRepository(\App\User\Domain\Entity\User::class);

        $user = $userRepository->findOneBy(['email' => $email]);

        if (!$user) {
            throw new \RuntimeException(sprintf('User with email "%s" not found', $email));
        }

        $client->loginUser($user, $firewall);

        return $client;
    }

    protected function createAdminClient()
    {
        // Для адмін-панелі використовуємо брандмауер 'admin'
        return $this->createAuthenticatedClient('admin@example.com', ['ROLE_ADMIN'], 'admin');
    }

    protected function createUserClient()
    {
        // Для звичайних маршрутів використовуємо брандмауер 'main'
        return $this->createAuthenticatedClient('user@example.com', ['ROLE_USER'], 'main');
    }
}

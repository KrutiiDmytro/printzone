<?php

namespace App\Tests\Functional\Admin;

use App\Tests\Functional\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

class UserCrudTest extends WebTestCase
{
    public function testUserListIsAccessibleForAdmin(): void
    {
        $client = $this->createAdminClient();
        $client->request('GET', '/admin/user');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Управление пользователями');
    }

    public function testCreateUserWithPasswordHashing(): void
    {
        $client = $this->createAdminClient();
        $crawler = $client->request('GET', '/admin/user/new');

        $this->assertResponseIsSuccessful();

        $form = $crawler->selectButton('Сохранить')->form([
            'User[email]' => 'test@example.com',
            'User[password]' => 'testpassword123',
            'User[fullName]' => 'Test User',
            'User[roles]' => ['ROLE_USER'],
        ]);

        $client->submit($form);
        $this->assertResponseRedirects('/admin/user');

        // Перевіряємо, що пароль захешований
        $container = static::getContainer();
        $userRepository = $container->get('doctrine')->getRepository(\App\User\Domain\Entity\User::class);
        $user = $userRepository->findOneBy(['email' => 'test@example.com']);
        
        $this->assertNotNull($user);
        $this->assertNotEquals('testpassword123', $user->getPassword());
    }
}
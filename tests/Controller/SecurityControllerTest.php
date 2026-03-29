<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class SecurityControllerTest extends WebTestCase
{
    public function testLoginPageIsAccessible(): void
    {
        $client = static::createClient();
        $client->request('GET', '/login');

        $this->assertResponseIsSuccessful();
    }

    public function testLoginPageContainsForm(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/login');

        $this->assertSelectorExists('input[name="username"]');
        $this->assertSelectorExists('input[name="password"]');
    }

    public function testUnauthenticatedUserIsRedirectedToLogin(): void
    {
        $client = static::createClient();
        $client->request('GET', '/');

        $this->assertResponseRedirects('/login');
    }

    public function testLoginWithInvalidCredentials(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/login');

        $form = $crawler->selectButton('Se connecter')->form([
            'username' => 'invalid_user',
            'password' => 'wrong_password',
        ]);

        $client->submit($form);
        $client->followRedirect();

        $this->assertRouteSame('app_login');
    }
}

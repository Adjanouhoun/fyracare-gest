<?php
namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class ClientControllerTest extends WebTestCase
{
    public function testClientIndexPage(): void
    {
        $client = static::createClient();
        $client->request('GET', '/client/');

        $this->assertResponseIsSuccessful();
    }

    public function testClientSearch(): void
    {
        $client = static::createClient();
        $client->request('GET', '/client/?q=jean');

        $this->assertResponseIsSuccessful();
    }

    public function testNewClientPage(): void
    {
        $client = static::createClient();
        $client->request('GET', '/client/new');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('form');
    }
}

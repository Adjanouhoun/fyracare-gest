<?php
namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class PrestationControllerTest extends WebTestCase
{
    public function testPrestationIndexPage(): void
    {
        $client = static::createClient();
        $client->request('GET', '/prestation/');

        $this->assertResponseIsSuccessful();
    }

    public function testStatusFilter(): void
    {
        $client = static::createClient();
        
        $client->request('GET', '/prestation/?status=active');
        $this->assertResponseIsSuccessful();
        
        $client->request('GET', '/prestation/?status=inactive');
        $this->assertResponseIsSuccessful();
    }

    public function testNewPrestationPage(): void
    {
        $client = static::createClient();
        $client->request('GET', '/prestation/new');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('form');
    }
}
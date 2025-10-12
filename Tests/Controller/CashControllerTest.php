<?php
namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class CashControllerTest extends WebTestCase
{
    public function testIndexPage(): void
    {
        $client = static::createClient();
        $client->request('GET', '/cash/');

        $this->assertResponseIsSuccessful();
    }

    public function testExpensesMonthPage(): void
    {
        $client = static::createClient();
        $client->request('GET', '/cash/expenses/month');

        $this->assertResponseIsSuccessful();
    }

    public function testExpensesYearPage(): void
    {
        $client = static::createClient();
        $client->request('GET', '/cash/expenses/year');

        $this->assertResponseIsSuccessful();
    }

    public function testEntriesMonthPage(): void
    {
        $client = static::createClient();
        $client->request('GET', '/cash/entries/month');

        $this->assertResponseIsSuccessful();
    }

    public function testClosuresPage(): void
    {
        $client = static::createClient();
        $client->request('GET', '/cash/closures');

        $this->assertResponseIsSuccessful();
    }
}
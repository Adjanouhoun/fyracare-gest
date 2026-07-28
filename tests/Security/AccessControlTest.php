<?php

namespace App\Tests\Security;

use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class AccessControlTest extends WebTestCase
{
    #[DataProvider('protectedPathProvider')]
    public function testSensitivePagesRedirectAnonymousVisitorsToLogin(string $path): void
    {
        $client = static::createClient();
        $client->request('GET', $path);

        self::assertResponseRedirects('http://localhost/login');
    }

    public static function protectedPathProvider(): iterable
    {
        yield 'clients' => ['/client/'];
        yield 'cash register' => ['/cash/'];
        yield 'statistics' => ['/stats'];
        yield 'activity log' => ['/admin/activity-log'];
        yield 'calendar API' => ['/api/calendar/events'];
        yield 'client autocomplete API' => ['/api/autocomplete/clients'];
    }
}

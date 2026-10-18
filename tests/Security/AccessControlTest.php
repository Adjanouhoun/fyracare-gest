<?php

namespace App\Tests\Security;

use App\Entity\User;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class AccessControlTest extends WebTestCase
{
    public function testAuthenticatedHomeRedirectsToExistingDashboardRoute(): void
    {
        $client = static::createClient();
        $user = (new User())
            ->setEmail('redirect-test-'.bin2hex(random_bytes(6)).'@example.test')
            ->setFullname('Test redirection')
            ->setFonction('Test')
            ->setPassword('test-only')
            ->setRoles(['ROLE_RECEPTION_CAISSE']);
        $entityManager = static::getContainer()->get('doctrine')->getManager();
        $entityManager->persist($user);
        $entityManager->flush();

        $client->loginUser($user);
        $client->request('GET', '/');

        self::assertResponseRedirects('/dashboard');
    }

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

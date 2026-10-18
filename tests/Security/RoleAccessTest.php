<?php

namespace App\Tests\Security;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Role\RoleHierarchyInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Routing\RouterInterface;

final class RoleAccessTest extends KernelTestCase
{
    public function testRoleHierarchyMatchesOperationalResponsibilities(): void
    {
        self::bootKernel();
        $hierarchy = static::getContainer()->get(RoleHierarchyInterface::class);

        self::assertContains(
            'ROLE_RECEPTION_CAISSE',
            $hierarchy->getReachableRoleNames(['ROLE_MANAGER']),
        );
        self::assertContains(
            'ROLE_MANAGER',
            $hierarchy->getReachableRoleNames(['ROLE_ADMIN']),
        );
        self::assertNotContains(
            'ROLE_MANAGER',
            $hierarchy->getReachableRoleNames(['ROLE_RECEPTION_CAISSE']),
        );
        self::assertNotContains(
            'ROLE_RECEPTION_CAISSE',
            $hierarchy->getReachableRoleNames(['ROLE_USER']),
        );
    }

    #[DataProvider('protectedPathProvider')]
    public function testSensitivePathsRequireTheExpectedRole(string $path, string $expectedRole): void
    {
        self::bootKernel();
        $accessMap = static::getContainer()->get('security.access_map');
        [$attributes] = $accessMap->getPatterns(Request::create($path));

        self::assertContains($expectedRole, $attributes ?? []);
    }

    /** @return iterable<string, array{string, string}> */
    public static function protectedPathProvider(): iterable
    {
        yield 'dashboard' => ['/dashboard', 'ROLE_RECEPTION_CAISSE'];
        yield 'own read-only profile' => ['/account', 'ROLE_USER'];
        yield 'user administration' => ['/user/6', 'ROLE_ADMIN'];
        yield 'appointment' => ['/rdv/12', 'ROLE_RECEPTION_CAISSE'];
        yield 'client ranking' => ['/client/ranking', 'ROLE_MANAGER'];
        yield 'payment' => ['/payment/', 'ROLE_RECEPTION_CAISSE'];
        yield 'cash deposit' => ['/cash/deposit', 'ROLE_MANAGER'];
        yield 'cash closure' => ['/cash/close', 'ROLE_RECEPTION_CAISSE'];
        yield 'cash closure reopening' => ['/cash/closure/12/reopen', 'ROLE_MANAGER'];
        yield 'statistics' => ['/stats', 'ROLE_MANAGER'];
        yield 'settings' => ['/admin/settings/', 'ROLE_ADMIN'];
        yield 'activity log' => ['/admin/activity-log', 'ROLE_ADMIN'];
        yield 'users' => ['/user/', 'ROLE_ADMIN'];
    }

    public function testValidatedCashEntriesHaveNoEditRoutes(): void
    {
        self::bootKernel();
        $router = static::getContainer()->get(RouterInterface::class);

        self::assertNull($router->getRouteCollection()->get('cash_expense_edit'));
        self::assertNull($router->getRouteCollection()->get('cash_deposit_edit'));
    }
}

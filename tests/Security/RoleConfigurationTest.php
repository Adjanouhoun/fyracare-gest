<?php

namespace App\Tests\Security;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

final class RoleConfigurationTest extends TestCase
{
    private array $security;

    protected function setUp(): void
    {
        $config = Yaml::parseFile(dirname(__DIR__, 2).'/config/packages/security.yaml');
        $this->security = $config['security'];
    }

    public function testOperationalRoleIncludesLegacyAuthenticatedRole(): void
    {
        self::assertContains(
            'ROLE_USER',
            $this->security['role_hierarchy']['ROLE_RECEPTION_CAISSE'],
        );
    }

    public function testAdministratorIncludesOperationalRoleThroughHierarchy(): void
    {
        self::assertContains(
            'ROLE_MANAGER',
            $this->security['role_hierarchy']['ROLE_ADMIN'],
        );
        self::assertContains(
            'ROLE_RECEPTION_CAISSE',
            $this->security['role_hierarchy']['ROLE_MANAGER'],
        );
    }

    public function testOnlyLoginAndLockArePublic(): void
    {
        $publicPaths = [];
        foreach ($this->security['access_control'] as $rule) {
            if (($rule['roles'] ?? null) === 'PUBLIC_ACCESS') {
                $publicPaths[] = $rule['path'];
            }
        }

        self::assertSame(['^/login', '^/lock'], $publicPaths);
    }

    public function testAuthenticatedCatchAllRuleIsLast(): void
    {
        $rules = $this->security['access_control'];
        self::assertSame(
            ['path' => '^/', 'roles' => 'ROLE_USER'],
            $rules[array_key_last($rules)],
        );
    }
}

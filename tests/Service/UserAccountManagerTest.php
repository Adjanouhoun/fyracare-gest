<?php

namespace App\Tests\Service;

use App\Entity\User;
use App\Security\UserChecker;
use App\Service\UserAccountManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;

final class UserAccountManagerTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private UserAccountManager $manager;
    private User $admin;
    private User $otherAdmin;
    private User $reception;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $this->manager = static::getContainer()->get(UserAccountManager::class);
        $metadata = $this->em->getMetadataFactory()->getAllMetadata();
        $tool = new SchemaTool($this->em);
        $tool->dropSchema($metadata);
        $tool->createSchema($metadata);

        $this->admin = $this->user('admin1@example.test', ['ROLE_ADMIN']);
        $this->otherAdmin = $this->user('admin2@example.test', ['ROLE_ADMIN']);
        $this->reception = $this->user('reception2@example.test', ['ROLE_RECEPTION_CAISSE']);
        foreach ([$this->admin, $this->otherAdmin, $this->reception] as $user) {
            $this->em->persist($user);
        }
        $this->em->flush();
    }

    public function testDeactivationPreservesUserAndBlocksAuthentication(): void
    {
        $this->manager->deactivate($this->reception, $this->admin);

        self::assertFalse($this->reception->isActive());
        self::assertSame($this->admin, $this->reception->getDeactivatedBy());
        self::assertNotNull($this->reception->getDeactivatedAt());
        self::assertSame(1, $this->em->getRepository(User::class)->count(['email' => 'reception2@example.test']));

        $this->expectException(CustomUserMessageAccountStatusException::class);
        (new UserChecker())->checkPreAuth($this->reception);
    }

    public function testAdministratorCannotDeactivateOwnAccount(): void
    {
        $this->expectException(\DomainException::class);
        $this->manager->deactivate($this->admin, $this->admin);
    }

    public function testLastActiveAdministratorIsProtected(): void
    {
        $this->manager->deactivate($this->otherAdmin, $this->admin);

        $this->expectException(\DomainException::class);
        $this->manager->deactivate($this->admin, $this->otherAdmin);
    }

    public function testAccountCanBeReactivated(): void
    {
        $this->manager->deactivate($this->reception, $this->admin);
        $this->manager->reactivate($this->reception);

        self::assertTrue($this->reception->isActive());
        self::assertNull($this->reception->getDeactivatedAt());
        self::assertNull($this->reception->getDeactivatedBy());
    }

    /** @param string[] $roles */
    private function user(string $email, array $roles): User
    {
        return (new User())
            ->setEmail($email)
            ->setFullname($email)
            ->setFonction('Test')
            ->setRoles($roles)
            ->setPassword('test');
    }
}

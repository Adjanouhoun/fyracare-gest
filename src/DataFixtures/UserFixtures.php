<?php
// src/DataFixtures/UserFixtures.php
namespace App\DataFixtures;

use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserFixtures extends Fixture
{
    public function __construct(private UserPasswordHasherInterface $hasher) {}

    public function load(ObjectManager $om): void
    {
        // Admin
        $admin = (new User())
            ->setEmail('admin@fyracare.test')
            ->setFullname('Admin FyraCare')
            ->setFonction('Administrateur')
            ->setRoles(['ROLE_ADMIN']);
        $admin->setPassword($this->hasher->hashPassword($admin, 'admin123'));
        $om->persist($admin);

        // Manager
        $manager = (new User())
            ->setEmail('manager@fyracare.test')
            ->setFullname('Manager FyraCare')
            ->setFonction('Manager')
            ->setRoles(['ROLE_MANAGER']);
        $manager->setPassword($this->hasher->hashPassword($manager, 'manager123'));
        $om->persist($manager);

        // User
        $user = (new User())
            ->setEmail('user@fyracare.test')
            ->setFullname('User FyraCare')
            ->setFonction('Agent')
            ->setRoles(['ROLE_USER']);
        $user->setPassword($this->hasher->hashPassword($user, 'user123'));
        $om->persist($user);

        $om->flush();
    }
}

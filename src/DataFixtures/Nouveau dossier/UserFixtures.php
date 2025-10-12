<?php
namespace App\DataFixtures;

use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class UserFixtures extends Fixture
{
    public const REF_ADMIN = 'user_admin';
    public const REF_USER1 = 'user_1';

    public function __construct(private UserPasswordHasherInterface $hasher) {}

    public function load(ObjectManager $em): void
    {
        $admin = (new User())
            ->setEmail('admin@fyracare.test')
            ->setFullname('Admin FyraCare')
            ->setFonction('Administrateur')
            ->setRoles(['ROLE_ADMIN']);
        $admin->setPassword($this->hasher->hashPassword($admin, 'admin1234'));
        $em->persist($admin);
        $this->addReference(self::REF_ADMIN, $admin);

        $u1 = (new User())
            ->setEmail('user@fyracare.test')
            ->setFullname('Utilisateur FyraCare')
            ->setFonction('Opérateur')
            ->setRoles(['ROLE_USER']);
        $u1->setPassword($this->hasher->hashPassword($u1, 'user1234'));
        $em->persist($u1);
        $this->addReference(self::REF_USER1, $u1);

        $em->flush();
    }
}

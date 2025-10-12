<?php
namespace App\DataFixtures;

use App\Entity\Client;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

final class ClientFixtures extends Fixture
{
    public const COUNT = 40;

    public function load(ObjectManager $em): void
    {
        for ($i = 1; $i <= self::COUNT; $i++) {
            $c = (new Client())
                ->setNometprenom("Client $i")
                ->setTelephone('22'.str_pad((string)random_int(100000, 999999), 6, '0', STR_PAD_LEFT))
                ->setEmail("client$i@demo.test")
                ->setNotes('Notes client '.$i)
                ->setCreatedAt(new \DateTime());
            $em->persist($c);
            $this->addReference('client_'.$i, $c);
        }
        $em->flush();
    }
}

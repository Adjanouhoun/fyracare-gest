<?php
namespace App\DataFixtures;

use App\Entity\Prestation;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

final class PrestationFixtures extends Fixture
{
    public const COUNT = 25; // ajustable

    public function load(ObjectManager $em): void
    {
        for ($i = 1; $i <= self::COUNT; $i++) {
            $p = (new Prestation())
                ->setLibelle('Prestation '.$i)
                ->setDureeMin(random_int(15, 120))
                ->setPrix(random_int(3000, 45000))
                ->setIsActive(true)
                ->setDescription('Description de la prestation '.$i);
            $em->persist($p);
            $this->addReference('prest_'.$i, $p);
        }
        $em->flush();
    }
}

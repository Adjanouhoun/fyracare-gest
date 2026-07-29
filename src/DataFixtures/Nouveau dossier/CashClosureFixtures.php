<?php
namespace App\DataFixtures;

use App\Entity\CashClosure;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

final class CashClosureFixtures extends Fixture implements DependentFixtureInterface
{
    public function load(ObjectManager $em): void
    {
        // 5 clôtures “vides” (exemple : totalIn/out calculés avant en contrôleur dans la vraie vie)
        for ($i = 0; $i < 5; $i++) {
            $day = new \DateTimeImmutable(sprintf('-%d days', $i));
            $cl = (new CashClosure())
                ->setClosedAt($day)
                ->setClosureDate($day)
                ->setTotalIn(random_int(50000, 120000))
                ->setTotalOut(random_int(5000, 40000))
                ->setBalance(0);
            $cl->setBalance($cl->getTotalIn() - $cl->getTotalOut());
            $cl->setCountedBalance($cl->getBalance())->setVariance(0);

            $em->persist($cl);
        }
        $em->flush();
    }

    public function getDependencies(): array
    {
        return [CashMovementFixtures::class];
    }
}

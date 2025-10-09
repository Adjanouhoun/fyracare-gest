<?php
namespace App\DataFixtures;

use App\Entity\Payment;
use App\Entity\CashMovement;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;

/**
 * Ici on ne crée QUE des SORTIES (dépenses).
 * Les ENTRÉES proviennent des Payment (logique métier).
 */
class CashMovementFixtures extends Fixture implements DependentFixtureInterface
{
    public function load(ObjectManager $em): void
    {
        $tz = new \DateTimeZone(\date_default_timezone_get());

        // Dépenses aléatoires sur 30 jours
        $labels = ['Achat consommables', 'Fournitures', 'Transport', 'Petite maintenance', 'Divers'];

        for ($i = 0; $i < 40; $i++) {
            $daysBack = random_int(0, 29);
            $hour     = random_int(10, 17);
            $minute   = [0, 15, 30, 45][array_rand([0,1,2,3])];

            $dt = (new \DateTimeImmutable('today', $tz))
                ->modify("-{$daysBack} days")
                ->setTime($hour, $minute, 0);

            $amount = random_int(1500, 15000);
            $label  = $labels[array_rand($labels)];

            $cm = (new CashMovement())
                ->setType('OUT')                 // sorties
                ->setSource('EXPENSE')           // source: dépense
                ->setAmount($amount)
                ->setNotes($label)
                ->setCreatedAt($dt);            // on force createdAt pour éviter les erreurs

            $em->persist($cm);
        }

        $em->flush();
    }

    public function getDependencies(): array
    {
        return [PaymentFixtures::class];
    }
}

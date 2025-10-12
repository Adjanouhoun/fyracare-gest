<?php
namespace App\Tests\Fixtures;

use App\Entity\Client;
use App\Entity\Prestation;
use App\Entity\Rdv;
use App\Entity\Payment;
use App\Entity\CashMovement;
use App\Entity\ExpenseCategory;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Faker\Factory;

class AppFixtures extends Fixture
{
    public function load(ObjectManager $em): void
    {
        $faker = Factory::create('fr_FR');

        // Catégories de dépenses
        $cats = [];
        foreach (['Loyer','Fournitures','Divers'] as $n) {
            $c = (new ExpenseCategory())->setName($n);
            $em->persist($c); $cats[] = $c;
        }

        // Prestations
        $prestations = [];
        foreach ([
            ['Soin visage', 2000, 60],
            ['Massage dos', 1500, 45],
            ['Coupe + brushing', 1000, 40],
        ] as [$lib,$prix,$duree]) {
            $p = (new Prestation())->setLibelle($lib)->setPrix($prix)->setDureeMin($duree);
            $em->persist($p); $prestations[] = $p;
        }

        // Clients
        $clients = [];
        for ($i=0;$i<5;$i++) {
            $cl = (new Client())
                ->setNometprenom($faker->name())
                ->setTelephone($faker->numerify('22########'))
                ->setEmail($faker->unique()->safeEmail());
            $em->persist($cl); $clients[] = $cl;
        }

        // RDV + Paiements + CashMovement (paiements)
        $now = new \DateTimeImmutable('now');
        for ($i=0;$i<6;$i++) {
            $cl = $clients[array_rand($clients)];
            $pr = $prestations[array_rand($prestations)];
            $start = $now->modify("-{$i} days 10:00");
            $end   = $start->modify('+'.$pr->getDureeMin().' minutes');

            $rdv = (new Rdv())
                ->setClient($cl)->setPrestation($pr)
                ->setStartAt($start)->setEndAt($end)
                ->setStatus(Rdv::S_HONORE);
            $em->persist($rdv);

            $pay = (new Payment())
                ->setRdv($rdv)
                ->setAmount($pr->getPrix())
                ->setMethode('Espèces')
                ->setNotes('Paiement test')
                ->setPaidAt($now)
                ->setReceiptNumber('RC-TEST-'.$i);
            $em->persist($pay);

            $cmIn = (new CashMovement())
                ->setType(CashMovement::IN)
                ->setAmount($pay->getAmount())
                ->setSource(CashMovement::SRC_PAYMENT)
                ->setNotes('Entrée via paiement')
                ->setCreatedAt($now);
            $em->persist($cmIn);
        }

        // Injections manuelles
        for ($i=1;$i<=3;$i++) {
            $cm = (new CashMovement())
                ->setType(CashMovement::IN)
                ->setAmount(500 * $i)
                ->setSource(CashMovement::SRC_MANUAL)
                ->setNotes('Injection test '.$i)
                ->setCreatedAt($now->modify("-{$i} days"));
            $em->persist($cm);
        }

        // Dépenses
        for ($i=1;$i<=4;$i++) {
            $cm = (new CashMovement())
                ->setType(CashMovement::OUT)
                ->setAmount(300 * $i)
                ->setSource(CashMovement::SRC_EXPENSE)
                ->setCategory($cats[array_rand($cats)])
                ->setNotes('Dépense test '.$i)
                ->setCreatedAt($now->modify("-{$i} days"));
            $em->persist($cm);
        }

        $em->flush();
    }
}

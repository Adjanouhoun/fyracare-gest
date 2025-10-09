<?php
namespace App\DataFixtures;

use App\Entity\Rdv;
use App\Entity\Client;
use App\Entity\Prestation;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class RdvFixtures extends Fixture
{
    public function load(ObjectManager $em): void
    {
        $tz = new \DateTimeZone(\date_default_timezone_get());
        $clientRepo = $em->getRepository(Client::class);
        $prestRepo  = $em->getRepository(Prestation::class);

        // IDs existants fournis
        $clientIds = range(6, 105);
        $prestIds  = range(184, 191);

        $statuses  = [
            Rdv::S_PLANIFIE, Rdv::S_CONFIRME, Rdv::S_HONORE,
            Rdv::S_ANNULE, Rdv::S_ABSENT
        ];

        // Petites notes aléatoires (jamais null)
        $notePool = [
            'Client venu avec 5 min d’avance.',
            'Préférence: shampoing doux.',
            'A signalé des démangeaisons récentes.',
            'Conseillé un soin nourrissant en plus.',
            'A reprogrammer si retard.',
            'RAS.',
            'Paiement prévu en espèces.',
            'Confirmer par téléphone la veille.',
            'Allergie connue: aucune.',
            'Souhaite un style naturel.',
            'A déjà fait cette prestation le mois dernier.',
            'Demande de conseil post-soin.',
        ];

        $count = 60; // ~60 RDV sur les 30 derniers jours
        for ($i = 0; $i < $count; $i++) {
            $cId = $clientIds[array_rand($clientIds)];
            $pId = $prestIds[array_rand($prestIds)];

            $client = $clientRepo->find($cId);
            $prest  = $prestRepo->find($pId);
            if (!$client || !$prest) {
                continue;
            }

            // date de début aléatoire (sur 30 derniers jours, créneau 9h–18h)
            $daysBack = random_int(0, 29);
            $hour     = random_int(9, 17);
            $minute   = [0, 15, 30, 45][array_rand([0,1,2,3])];

            $start = (new \DateTimeImmutable('today', $tz))
                ->modify("-{$daysBack} days")
                ->setTime($hour, $minute, 0);

            $end = $start->modify('+'.$prest->getDureeMin().' minutes');

            // 1 chance sur 4 de mettre une note très courte, sinon note dans $notePool
            $randomNote = (random_int(1, 4) === 1)
                ? 'RAS.'
                : $notePool[array_rand($notePool)];

            $rdv = (new Rdv())
                ->setClient($client)
                ->setPrestation($prest)
                ->setStartAt($start)
                ->setEndAt($end)
                ->setStatus($statuses[array_rand($statuses)])
                ->setNotes($randomNote); // <-- plus jamais null

            $em->persist($rdv);
        }

        $em->flush();
    }
}

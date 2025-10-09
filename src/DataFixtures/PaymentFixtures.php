<?php
namespace App\DataFixtures;

use App\Entity\Rdv;
use App\Entity\Payment;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;

class PaymentFixtures extends Fixture implements DependentFixtureInterface
{
    public function load(ObjectManager $em): void
    {
        // Règle : un seul paiement total par RDV, montant = prix prestation
        // On paie ~ 50% des RDV CONFIRMÉ/HONORÉ des 30 derniers jours.
        $tz = new \DateTimeZone(\date_default_timezone_get());

        $rdvs = $em->getRepository(Rdv::class)->createQueryBuilder('r')
            ->andWhere('r.status IN (:st)')
            ->andWhere('r.startAt >= :from')
            ->setParameter('st', [Rdv::S_CONFIRME, Rdv::S_HONORE])
            ->setParameter('from', (new \DateTimeImmutable('today', $tz))->modify('-30 days')->setTime(0,0,0))
            ->getQuery()->getResult();

        foreach ($rdvs as $rdv) {
            // ~50% seulement
            if (random_int(0, 1) === 0) { continue; }

            $amount = $rdv->getPrestation()?->getPrix() ?? 0;
            if ($amount <= 0) { continue; }

            // paidAt autour de l’heure de fin
            $paidAt = ($rdv->getEndAt() ?? $rdv->getStartAt() ?? new \DateTimeImmutable('now', $tz))
                ->modify('+'.random_int(0, 120).' minutes');

            $p = (new Payment())
                ->setRdv($rdv)
                ->setAmount($amount)
                ->setMethode(Payment::M_ESPECES)
                ->setPaidAt($paidAt)
                ->setReceiptNumber(null)
                ->setIsDeposit(false)
                ->setNotes('Paiement de test');

            $em->persist($p);
        }

        $em->flush();

        // Génère receiptNumber = YYYYMMDD-<id>
        $payments = $em->getRepository(Payment::class)->findBy(['receiptNumber' => null]);
        foreach ($payments as $p) {
            $d = $p->getPaidAt() ?? new \DateTimeImmutable('now', $tz);
            $p->setReceiptNumber($d->format('Ymd').'-'.$p->getId());
        }
        $em->flush();
    }

    public function getDependencies(): array
    {
        return [RdvFixtures::class];
    }
}

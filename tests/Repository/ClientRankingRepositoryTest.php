<?php

namespace App\Tests\Repository;

use App\Entity\Client;
use App\Entity\Payment;
use App\Entity\Prestation;
use App\Entity\Rdv;
use App\Repository\PaymentRepository;
use App\Repository\RdvRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class ClientRankingRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private RdvRepository $appointments;
    private PaymentRepository $payments;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $this->appointments = $this->em->getRepository(Rdv::class);
        $this->payments = $this->em->getRepository(Payment::class);

        $metadata = $this->em->getMetadataFactory()->getAllMetadata();
        $schema = new SchemaTool($this->em);
        $schema->dropSchema($metadata);
        $schema->createSchema($metadata);
    }

    public function testRankingCountsHonoredVisitsAndOnlyValidPayments(): void
    {
        $client = (new Client())
            ->setNometprenom('Cliente fidèle')
            ->setTelephone('22000000')
            ->setEmail('fidele@example.test')
            ->setNotes('Test')
            ->setCreatedAt(new \DateTime());
        $service = (new Prestation())
            ->setLibelle('Soin test')
            ->setDureeMin(60)
            ->setPrix(5000)
            ->setIsActive(true);
        $this->em->persist($client);
        $this->em->persist($service);

        $firstVisit = $this->appointment($client, $service, '2026-10-01 10:00:00', Rdv::S_HONORE);
        $secondVisit = $this->appointment($client, $service, '2026-10-08 10:00:00', Rdv::S_HONORE);
        $this->appointment($client, $service, '2026-10-10 10:00:00', Rdv::S_ANNULE);

        $this->payment($firstVisit, 2000, '2026-10-01 10:30:00');
        $this->payment($firstVisit, 3000, '2026-10-02 10:30:00');
        $this->payment($secondVisit, 5000, '2026-10-08 10:30:00', true);
        $this->em->flush();

        $visits = $this->appointments->honoredVisitsByClient();
        $spending = $this->payments->spendingByClient();

        self::assertCount(1, $visits);
        self::assertSame(2, $visits[0]['visits']);
        self::assertSame('2026-10-08 10:00:00', $visits[0]['lastVisit']);
        self::assertCount(1, $spending);
        self::assertSame(5000, $spending[0]['totalSpent']);
    }

    private function appointment(Client $client, Prestation $service, string $date, string $status): Rdv
    {
        $start = new \DateTimeImmutable($date);
        $appointment = (new Rdv())
            ->setClient($client)
            ->setPrestation($service)
            ->setStartAt($start)
            ->setEndAt($start->modify('+1 hour'))
            ->setStatus($status)
            ->setPaymentStatus(Rdv::PS_NON_PAYE)
            ->setPriceAtBooking(5000);
        $this->em->persist($appointment);

        return $appointment;
    }

    private function payment(Rdv $appointment, int $amount, string $date, bool $cancelled = false): void
    {
        $payment = (new Payment())
            ->setRdv($appointment)
            ->setAmount($amount)
            ->setMethode(Payment::M_ESPECES)
            ->setPaidAt(new \DateTimeImmutable($date))
            ->setIsDeposit(false);
        if ($cancelled) {
            $payment->setCancelledAt(new \DateTimeImmutable($date));
        }
        $this->em->persist($payment);
    }
}

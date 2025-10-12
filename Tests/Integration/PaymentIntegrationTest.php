<?php
/**
 * ============================================
 * TESTS D'INTÉGRATION AVEC BASE DE DONNÉES
 * ============================================
 */

// tests/Integration/PaymentIntegrationTest.php
namespace App\Tests\Integration;

use App\Entity\Payment;
use App\Entity\Rdv;
use App\Entity\Client;
use App\Entity\Prestation;
use App\Entity\CashMovement;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class PaymentIntegrationTest extends KernelTestCase
{
    private $entityManager;

    protected function setUp(): void
    {
        $kernel = self::bootKernel();
        $this->entityManager = $kernel->getContainer()
            ->get('doctrine')
            ->getManager();
    }

    public function testCreatePaymentPersistsCorrectly(): void
    {
        $client = new Client();
        $client->setNometprenom('Test Integration')
               ->setTelephone('12345678')
               ->setEmail('integration@test.com')
               ->setNotes('Test')
               ->setCreatedAt(new \DateTime());

        $prestation = new Prestation();
        $prestation->setLibelle('Test Service')
                   ->setDureeMin(30)
                   ->setPrix(5000)
                   ->setIsActive(true)
                   ->setDescription('Test');

        $rdv = new Rdv();
        $rdv->setClient($client)
            ->setPrestation($prestation)
            ->setStartAt(new \DateTimeImmutable('2025-01-15 10:00'))
            ->setStatus(Rdv::S_PLANIFIE);
        $rdv->computeEnd();

        $payment = new Payment();
        $payment->setRdv($rdv)
                ->setAmount(5000)
                ->setMethode(Payment::M_ESPECES)
                ->setPaidAt(new \DateTimeImmutable())
                ->setReceiptNumber('15012025-999');

        $this->entityManager->persist($client);
        $this->entityManager->persist($prestation);
        $this->entityManager->persist($rdv);
        $this->entityManager->persist($payment);
        $this->entityManager->flush();

        $this->assertNotNull($payment->getId());
        $this->assertEquals(5000, $payment->getAmount());
        
        // Vérifier que le RDV est bien persisté
        $savedRdv = $this->entityManager
            ->getRepository(Rdv::class)
            ->find($rdv->getId());
        
        $this->assertNotNull($savedRdv);
        $this->assertEquals('Test Integration', $savedRdv->getClient()->getNometprenom());
    }

    public function testPaymentWithCashMovement(): void
    {
        $client = new Client();
        $client->setNometprenom('Cash Test')
               ->setTelephone('11111111')
               ->setEmail('cash@test.com')
               ->setNotes('')
               ->setCreatedAt(new \DateTime());

        $prestation = new Prestation();
        $prestation->setLibelle('Service Cash')
                   ->setDureeMin(45)
                   ->setPrix(7000)
                   ->setIsActive(true)
                   ->setDescription('');

        $rdv = new Rdv();
        $rdv->setClient($client)
            ->setPrestation($prestation)
            ->setStartAt(new \DateTimeImmutable())
            ->setStatus(Rdv::S_HONORE);
        $rdv->computeEnd();

        $payment = new Payment();
        $payment->setRdv($rdv)
                ->setAmount(7000)
                ->setMethode(Payment::M_MOBILE)
                ->setPaidAt(new \DateTimeImmutable())
                ->setReceiptNumber('TEST-001');

        // CashMovement associé
        $cashMv = new CashMovement();
        $cashMv->setType(CashMovement::IN)
               ->setAmount($payment->getAmount())
               ->setSource(CashMovement::SRC_PAYMENT)
               ->setNotes('Payment RDV Test');

        $this->entityManager->persist($client);
        $this->entityManager->persist($prestation);
        $this->entityManager->persist($rdv);
        $this->entityManager->persist($payment);
        $this->entityManager->persist($cashMv);
        $this->entityManager->flush();

        $this->assertNotNull($cashMv->getId());
        $this->assertEquals(7000, $cashMv->getAmount());
        $this->assertEquals(CashMovement::IN, $cashMv->getType());
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->entityManager->close();
        $this->entityManager = null;
    }
}
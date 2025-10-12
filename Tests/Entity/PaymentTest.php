<?php
namespace App\Tests\Entity;

use App\Entity\Payment;
use App\Entity\Rdv;
use App\Entity\Client;
use App\Entity\Prestation;
use PHPUnit\Framework\TestCase;

class PaymentTest extends TestCase
{
    public function testPaymentCreation(): void
    {
        $payment = new Payment();
        $payment->setAmount(5000);
        $payment->setMethode(Payment::M_ESPECES);
        $payment->setPaidAt(new \DateTimeImmutable());
        $payment->setReceiptNumber('10012025-1');
        
        $this->assertEquals(5000, $payment->getAmount());
        $this->assertEquals(Payment::M_ESPECES, $payment->getMethode());
        $this->assertInstanceOf(\DateTimeImmutable::class, $payment->getPaidAt());
        $this->assertEquals('10012025-1', $payment->getReceiptNumber());
    }

    public function testPaymentMethodes(): void
    {
        $this->assertEquals('ESPECES', Payment::M_ESPECES);
        $this->assertEquals('MOBILE', Payment::M_MOBILE);
    }

    public function testPaymentWithRdv(): void
    {
        $client = new Client();
        $client->setNometprenom('Jean Dupont')
               ->setTelephone('12345678')
               ->setEmail('jean@example.com')
               ->setNotes('Test');

        $prestation = new Prestation();
        $prestation->setLibelle('Consultation')
                   ->setDureeMin(30)
                   ->setPrix(5000)
                   ->setIsActive(true)
                   ->setDescription('Test prestation');

        $rdv = new Rdv();
        $rdv->setClient($client)
            ->setPrestation($prestation)
            ->setStartAt(new \DateTimeImmutable('2025-01-10 14:00'))
            ->setStatus(Rdv::S_PLANIFIE);

        $payment = new Payment();
        $payment->setRdv($rdv)
                ->setAmount(5000)
                ->setMethode(Payment::M_ESPECES)
                ->setPaidAt(new \DateTimeImmutable());

        $this->assertSame($rdv, $payment->getRdv());
        $this->assertEquals('Jean Dupont', $payment->getRdv()->getClient()->getNometprenom());
        $this->assertEquals(5000, $payment->getRdv()->getPrestation()->getPrix());
    }

    public function testIsDeposit(): void
    {
        $payment = new Payment();
        $payment->setIsDeposit(true);
        
        $this->assertTrue($payment->isDeposit());
        
        $payment->setIsDeposit(false);
        $this->assertFalse($payment->isDeposit());
    }
}

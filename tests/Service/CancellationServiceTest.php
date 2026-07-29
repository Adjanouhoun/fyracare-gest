<?php

namespace App\Tests\Service;

use App\Entity\CashMovement;
use App\Entity\Client;
use App\Entity\Payment;
use App\Entity\Prestation;
use App\Entity\Rdv;
use App\Entity\User;
use App\Service\CancellationService;
use App\Service\PaymentProcessor;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class CancellationServiceTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private CancellationService $cancellation;
    private PaymentProcessor $payments;
    private User $admin;
    private Rdv $rdv;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $this->cancellation = static::getContainer()->get(CancellationService::class);
        $this->payments = static::getContainer()->get(PaymentProcessor::class);

        $metadata = $this->em->getMetadataFactory()->getAllMetadata();
        $schema = new SchemaTool($this->em);
        $schema->dropSchema($metadata);
        $schema->createSchema($metadata);

        $this->admin = (new User())
            ->setEmail('admin@example.test')->setFullname('Admin Test')->setFonction('Administration')
            ->setRoles(['ROLE_ADMIN'])->setPassword('test');
        $client = (new Client())
            ->setNometprenom('Client Test')->setTelephone('22000001')->setEmail('client2@example.test')
            ->setNotes('Test')->setCreatedAt(new \DateTime());
        $service = (new Prestation())
            ->setLibelle('Soin')->setDureeMin(30)->setPrix(1000)->setIsActive(true)->setDescription('Test');
        $start = new \DateTimeImmutable('tomorrow 14:00');
        $this->rdv = (new Rdv())
            ->setClient($client)->setPrestation($service)->setStartAt($start)->setEndAt($start->modify('+30 minutes'))
            ->setStatus(Rdv::S_CONFIRME)->setPaymentStatus(Rdv::PS_NON_PAYE);

        foreach ([$this->admin, $client, $service, $this->rdv] as $entity) {
            $this->em->persist($entity);
        }
        $this->em->flush();
    }

    public function testPaymentCancellationKeepsAuditTrailAndCreatesReversal(): void
    {
        $payment = (new Payment())->setAmount(600)->setMethode(Payment::M_ESPECES);
        $this->payments->process($payment, $this->rdv, $this->admin);

        $this->cancellation->cancelPayment($payment, $this->admin, 'Erreur de saisie du montant');

        self::assertTrue($payment->isCancelled());
        self::assertSame($this->admin, $payment->getCancelledBy());
        self::assertSame('Erreur de saisie du montant', $payment->getCancellationReason());
        self::assertSame(0, $this->rdv->getTotalPaid());
        self::assertSame(Rdv::PS_NON_PAYE, $this->rdv->getPaymentStatus());
        self::assertSame(1, $this->em->getRepository(Payment::class)->count([]));

        $movements = $this->em->getRepository(CashMovement::class)->findBy(['payment' => $payment], ['id' => 'ASC']);
        self::assertCount(2, $movements);
        self::assertSame(CashMovement::IN, $movements[0]->getType());
        self::assertSame(CashMovement::OUT, $movements[1]->getType());
        self::assertSame(CashMovement::SRC_PAYMENT_REVERSAL, $movements[1]->getSource());
        self::assertSame(600, $movements[1]->getAmount());
    }

    public function testAppointmentCancellationPreservesExistingPayment(): void
    {
        $payment = (new Payment())->setAmount(400)->setMethode(Payment::M_MOBILE);
        $this->payments->process($payment, $this->rdv, $this->admin);

        $this->cancellation->cancelAppointment($this->rdv, $this->admin, 'Demande explicite du client');

        self::assertTrue($this->rdv->isCancelled());
        self::assertSame(Rdv::S_ANNULE, $this->rdv->getStatus());
        self::assertSame($this->admin, $this->rdv->getCancelledBy());
        self::assertFalse($payment->isCancelled());
        self::assertSame(1, $this->em->getRepository(Payment::class)->count([]));
    }

    public function testMobilePaymentCancellationDoesNotCreateCashReversal(): void
    {
        $payment = (new Payment())->setAmount(400)->setMethode(Payment::M_MOBILE);
        $this->payments->process($payment, $this->rdv, $this->admin);

        $this->cancellation->cancelPayment($payment, $this->admin, 'Erreur sur paiement mobile');

        self::assertTrue($payment->isCancelled());
        self::assertSame(0, $this->em->getRepository(CashMovement::class)->count([]));
    }

    public function testCancellationCannotBeAppliedTwice(): void
    {
        $payment = (new Payment())->setAmount(200)->setMethode(Payment::M_ESPECES);
        $this->payments->process($payment, $this->rdv, $this->admin);
        $this->cancellation->cancelPayment($payment, $this->admin, 'Première annulation valide');

        $this->expectException(\DomainException::class);
        $this->cancellation->cancelPayment($payment, $this->admin, 'Deuxième annulation refusée');
    }

    public function testReasonIsMandatory(): void
    {
        $this->expectException(\DomainException::class);
        $this->cancellation->cancelAppointment($this->rdv, $this->admin, 'non');
    }
}

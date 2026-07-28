<?php

namespace App\Tests\Service;

use App\Entity\CashMovement;
use App\Entity\Client;
use App\Entity\Payment;
use App\Entity\Prestation;
use App\Entity\Rdv;
use App\Entity\User;
use App\Service\PaymentProcessor;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class PaymentProcessorTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private PaymentProcessor $processor;
    private User $author;
    private Rdv $appointment;

    protected function setUp(): void
    {
        self::bootKernel();

        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $this->processor = static::getContainer()->get(PaymentProcessor::class);

        $metadata = $this->em->getMetadataFactory()->getAllMetadata();
        $schemaTool = new SchemaTool($this->em);
        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);

        $this->author = (new User())
            ->setEmail('cashier@example.test')
            ->setFullname('Caissier Test')
            ->setFonction('Réception et caisse')
            ->setRoles(['ROLE_RECEPTION_CAISSE'])
            ->setPassword('not-used-in-this-test');

        $client = (new Client())
            ->setNometprenom('Client Test')
            ->setTelephone('22000000')
            ->setEmail('client@example.test')
            ->setNotes('Donnée fictive')
            ->setCreatedAt(new \DateTime());

        $prestation = (new Prestation())
            ->setLibelle('Prestation Test')
            ->setDureeMin(60)
            ->setPrix(1000)
            ->setIsActive(true)
            ->setDescription('Donnée fictive');

        $start = new \DateTimeImmutable('tomorrow 10:00');
        $this->appointment = (new Rdv())
            ->setClient($client)
            ->setPrestation($prestation)
            ->setStartAt($start)
            ->setEndAt($start->modify('+60 minutes'))
            ->setStatus(Rdv::S_CONFIRME)
            ->setPaymentStatus(Rdv::PS_NON_PAYE)
            ->setNotes('Donnée fictive');

        foreach ([$this->author, $client, $prestation, $this->appointment] as $entity) {
            $this->em->persist($entity);
        }
        $this->em->flush();
    }

    public function testPartialAndFinalPaymentsStayConsistentWithCashMovements(): void
    {
        $first = (new Payment())
            ->setAmount(600)
            ->setMethode(Payment::M_ESPECES)
            ->setNotes('Premier versement');

        $this->processor->process($first, $this->appointment, $this->author);

        self::assertTrue($first->isDeposit());
        self::assertSame(Rdv::PS_PARTIEL, $this->appointment->getPaymentStatus());
        self::assertSame(400, $this->appointment->getRemainingAmount());
        self::assertMatchesRegularExpression('/^PAY-\d{8}-[0-9A-Z]{26}$/', $first->getReceiptNumber());
        self::assertSame($this->author, $first->getReceivedBy());

        $this->appointment->getPrestation()->setPrix(2000);
        self::assertSame(1000, $this->appointment->getTotalAmount());
        self::assertSame(400, $this->appointment->getRemainingAmount());

        $second = (new Payment())
            ->setAmount(400)
            ->setMethode(Payment::M_MOBILE)
            ->setNotes('Solde');

        $this->processor->process($second, $this->appointment, $this->author);

        self::assertFalse($second->isDeposit());
        self::assertSame(Rdv::PS_PAYE, $this->appointment->getPaymentStatus());
        self::assertSame(Rdv::S_CONFIRME, $this->appointment->getStatus());
        self::assertSame(0, $this->appointment->getRemainingAmount());
        self::assertNotSame($first->getReceiptNumber(), $second->getReceiptNumber());

        // Simule une ancienne donnée où le paiement mobile avait créé à tort
        // un mouvement : le filtre de caisse doit également l'écarter.
        $legacyMobileMovement = (new CashMovement())
            ->setType(CashMovement::IN)
            ->setAmount(400)
            ->setSource(CashMovement::SRC_PAYMENT)
            ->setCreatedAt($second->getPaidAt())
            ->setCreatedBy($this->author);
        $this->em->persist($legacyMobileMovement);
        $this->em->flush();

        $movements = $this->em->getRepository(CashMovement::class)->findBy(
            ['source' => CashMovement::SRC_PAYMENT],
            ['id' => 'ASC'],
        );

        self::assertCount(1, $movements);
        self::assertSame($first, $movements[0]->getPayment());
        self::assertSame($this->author, $movements[0]->getCreatedBy());
        self::assertSame(600, array_sum(array_map(
            static fn (CashMovement $movement): int => $movement->getAmount(),
            $movements,
        )));
    }

    public function testOverpaymentIsRejectedWithoutCreatingAccountingEntries(): void
    {
        $payment = (new Payment())
            ->setAmount(1001)
            ->setMethode(Payment::M_ESPECES)
            ->setNotes('Montant invalide');

        try {
            $this->processor->process($payment, $this->appointment, $this->author);
            self::fail('Le dépassement aurait dû être refusé.');
        } catch (\DomainException $exception) {
            self::assertStringContainsString('dépasse', $exception->getMessage());
        }

        self::assertSame(0, $this->countRows('payment'));
        self::assertSame(0, $this->countRows('cash_movement'));
    }

    public function testZeroPaymentIsRejectedWithoutCreatingAccountingEntries(): void
    {
        $payment = (new Payment())
            ->setAmount(0)
            ->setMethode(Payment::M_ESPECES)
            ->setNotes('Montant invalide');

        try {
            $this->processor->process($payment, $this->appointment, $this->author);
            self::fail('Le montant nul aurait dû être refusé.');
        } catch (\DomainException $exception) {
            self::assertStringContainsString('strictement positif', $exception->getMessage());
        }

        self::assertSame(0, $this->countRows('payment'));
        self::assertSame(0, $this->countRows('cash_movement'));
    }

    private function countRows(string $table): int
    {
        return (int) $this->em->getConnection()
            ->executeQuery(sprintf('SELECT COUNT(*) FROM %s', $table))
            ->fetchOne();
    }
}

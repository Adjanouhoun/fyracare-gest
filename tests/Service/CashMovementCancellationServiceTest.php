<?php

namespace App\Tests\Service;

use App\Entity\CashMovement;
use App\Entity\User;
use App\Repository\CashMovementRepository;
use App\Service\CashMovementCancellationService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class CashMovementCancellationServiceTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private CashMovementCancellationService $service;
    private CashMovementRepository $repository;
    private User $admin;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $this->service = static::getContainer()->get(CashMovementCancellationService::class);
        $this->repository = $this->em->getRepository(CashMovement::class);
        $metadata = $this->em->getMetadataFactory()->getAllMetadata();
        $tool = new SchemaTool($this->em);
        $tool->dropSchema($metadata);
        $tool->createSchema($metadata);

        $this->admin = (new User())->setEmail('cash-admin@example.test')->setFullname('Admin caisse')
            ->setFonction('Administration')->setRoles(['ROLE_ADMIN'])->setPassword('test');
        $this->em->persist($this->admin);
        $this->em->flush();
    }

    public function testCancellationPreservesRowAndRemovesItFromActiveTotals(): void
    {
        $movement = (new CashMovement())
            ->setType(CashMovement::OUT)
            ->setAmount(450)
            ->setSource(CashMovement::SRC_EXPENSE)
            ->setNotes('Fournitures')
            ->setCreatedBy($this->admin);
        $this->em->persist($movement);
        $this->em->flush();

        self::assertSame(450, $this->repository->sumAll(CashMovement::OUT));
        $this->service->cancel($movement, $this->admin, 'Erreur de saisie comptable');
        $this->em->clear();

        self::assertSame(0, $this->repository->sumAll(CashMovement::OUT));
        self::assertSame(0, $this->repository->count([]));
        self::assertSame(
            1,
            (int) $this->em->getConnection()->executeQuery(
                'SELECT COUNT(*) FROM cash_movement WHERE deletion_reason = ?',
                ['Erreur de saisie comptable'],
            )->fetchOne(),
        );
    }

    public function testPaymentMovementCannotBeCancelledThroughManualCashWorkflow(): void
    {
        $movement = (new CashMovement())
            ->setType(CashMovement::IN)
            ->setAmount(500)
            ->setSource(CashMovement::SRC_PAYMENT);
        $this->em->persist($movement);
        $this->em->flush();

        $this->expectException(\DomainException::class);
        $this->service->cancel($movement, $this->admin, 'Tentative non autorisée');
    }

    public function testReasonIsMandatory(): void
    {
        $movement = (new CashMovement())
            ->setType(CashMovement::IN)
            ->setAmount(500)
            ->setSource(CashMovement::SRC_MANUAL);
        $this->em->persist($movement);
        $this->em->flush();

        $this->expectException(\DomainException::class);
        $this->service->cancel($movement, $this->admin, 'non');
    }
}

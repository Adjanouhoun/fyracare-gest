<?php

namespace App\Tests\Repository;

use App\Entity\CashMovement;
use App\Repository\CashMovementRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class CashMovementRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private CashMovementRepository $repository;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $this->repository = $this->em->getRepository(CashMovement::class);

        $metadata = $this->em->getMetadataFactory()->getAllMetadata();
        $schemaTool = new SchemaTool($this->em);
        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);
    }

    public function testDailyTotalsAreIndependentFromDisplayedPagination(): void
    {
        for ($index = 0; $index < 15; ++$index) {
            $this->persistMovement(CashMovement::IN, CashMovement::SRC_PAYMENT, 100);
        }
        $this->persistMovement(CashMovement::IN, CashMovement::SRC_MANUAL, 500);
        $this->persistMovement(CashMovement::OUT, CashMovement::SRC_EXPENSE, 250);
        $this->em->flush();

        $from = new \DateTimeImmutable('today 00:00:00');
        $to = new \DateTimeImmutable('today 23:59:59');

        self::assertSame(
            1500,
            $this->repository->sumBetweenByTypeAndSource(
                $from,
                $to,
                CashMovement::IN,
                CashMovement::SRC_PAYMENT,
            ),
        );
        self::assertSame(
            500,
            $this->repository->sumBetweenByTypeExcludingSource(
                $from,
                $to,
                CashMovement::IN,
                CashMovement::SRC_PAYMENT,
            ),
        );
        self::assertSame(250, $this->repository->sumBetween($from, $to, CashMovement::OUT));
    }

    public function testCurrentCashStartsFromZeroEachDay(): void
    {
        $this->persistMovement(CashMovement::IN, CashMovement::SRC_MANUAL, 1000);
        $this->persistMovement(CashMovement::OUT, CashMovement::SRC_EXPENSE, 250);

        $oldMovement = (new CashMovement())
            ->setType(CashMovement::IN)
            ->setSource(CashMovement::SRC_MANUAL)
            ->setAmount(5000)
            ->setCreatedAt(new \DateTimeImmutable('yesterday 12:00:00'));
        $this->em->persist($oldMovement);
        $this->em->flush();

        self::assertSame(750, $this->repository->currentCash(new \DateTimeImmutable('today')));
        self::assertSame(5000, $this->repository->currentCash(new \DateTimeImmutable('yesterday')));
    }

    private function persistMovement(string $type, string $source, int $amount): void
    {
        $movement = (new CashMovement())
            ->setType($type)
            ->setSource($source)
            ->setAmount($amount)
            ->setCreatedAt(new \DateTimeImmutable());
        $this->em->persist($movement);
    }
}

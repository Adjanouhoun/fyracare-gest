<?php

namespace App\Tests\Service;

use App\Entity\CashClosure;
use App\Entity\CashMovement;
use App\Entity\CashClosureReopening;
use App\Entity\User;
use App\Service\CashPeriodGuard;
use App\Service\CashRegisterService;
use App\Service\SettingsProvider;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class CashRegisterServiceTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private CashRegisterService $register;
    private CashPeriodGuard $guard;
    private User $manager;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $this->register = static::getContainer()->get(CashRegisterService::class);
        $this->guard = static::getContainer()->get(CashPeriodGuard::class);
        $metadata = $this->em->getMetadataFactory()->getAllMetadata();
        $tool = new SchemaTool($this->em);
        $tool->dropSchema($metadata);
        $tool->createSchema($metadata);

        $this->manager = (new User())->setEmail('manager-cash@example.test')->setFullname('Manager')
            ->setFonction('Manager')->setRoles(['ROLE_MANAGER'])->setPassword('test');
        $this->em->persist($this->manager);

        $in = (new CashMovement())->setType(CashMovement::IN)->setAmount(1000)
            ->setSource(CashMovement::SRC_MANUAL)->setCreatedBy($this->manager);
        $out = (new CashMovement())->setType(CashMovement::OUT)->setAmount(300)
            ->setSource(CashMovement::SRC_EXPENSE)->setCreatedBy($this->manager);
        $this->em->persist($in);
        $this->em->persist($out);
        $this->em->flush();
    }

    public function testClosureFreezesTotalsAndRecordsVarianceAndAuthor(): void
    {
        $closure = $this->register->close(
            new \DateTimeImmutable('today'),
            680,
            $this->manager,
            'Écart de comptage vérifié',
        );

        self::assertSame(1000, $closure->getTotalIn());
        self::assertSame(300, $closure->getTotalOut());
        self::assertSame(700, $closure->getBalance());
        self::assertSame(680, $closure->getCountedBalance());
        self::assertSame(-20, $closure->getVariance());
        self::assertSame($this->manager, $closure->getClosedBy());
        self::assertSame(1, $this->em->getRepository(CashClosure::class)->count([]));
    }

    public function testSameDayCannotBeClosedTwice(): void
    {
        $this->register->close(new \DateTimeImmutable('today'), 700, $this->manager);

        $this->expectException(\DomainException::class);
        $this->register->close(new \DateTimeImmutable('today'), 700, $this->manager);
    }

    public function testClosedDayRejectsNewCashEntries(): void
    {
        $this->register->close(new \DateTimeImmutable('today'), 700, $this->manager);

        $this->expectException(\DomainException::class);
        $this->guard->assertOpen(new \DateTimeImmutable('today'));
    }

    public function testManagerCanReopenLatestClosureWithAuditSnapshotAndCloseItAgain(): void
    {
        $closure = $this->register->close(
            new \DateTimeImmutable('today'),
            680,
            $this->manager,
            'Écart de comptage vérifié',
        );

        $reopening = $this->register->reopen(
            $closure,
            $this->manager,
            'Clôture effectuée avant une correction urgente',
        );

        self::assertTrue($closure->isReopened());
        self::assertSame(700, $reopening->getPreviousBalance());
        self::assertSame(680, $reopening->getPreviousCountedBalance());
        self::assertSame(-20, $reopening->getPreviousVariance());
        self::assertSame(1, $this->em->getRepository(CashClosureReopening::class)->count([]));
        $this->guard->assertOpen(new \DateTimeImmutable('today'));

        $newMovement = (new CashMovement())
            ->setType(CashMovement::IN)
            ->setAmount(50)
            ->setSource(CashMovement::SRC_MANUAL)
            ->setCreatedBy($this->manager);
        $this->em->persist($newMovement);
        $this->em->flush();

        $reclosed = $this->register->close(
            new \DateTimeImmutable('today'),
            750,
            $this->manager,
        );

        self::assertSame($closure->getId(), $reclosed->getId());
        self::assertFalse($reclosed->isReopened());
        self::assertTrue($reopening->isReclosed());
        self::assertSame(750, $reclosed->getBalance());
        self::assertSame(1, $this->em->getRepository(CashClosure::class)->count([]));
    }

    public function testReopeningRequiresDetailedReason(): void
    {
        $closure = $this->register->close(new \DateTimeImmutable('today'), 700, $this->manager);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('10 caractères');
        $this->register->reopen($closure, $this->manager, 'Erreur');
    }

    public function testClosureCannotBeReopenedTwiceWithoutReclosing(): void
    {
        $closure = $this->register->close(new \DateTimeImmutable('today'), 700, $this->manager);
        $this->register->reopen($closure, $this->manager, 'Clôture accidentelle à corriger');

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('déjà rouverte');
        $this->register->reopen($closure, $this->manager, 'Nouvelle demande de réouverture');
    }

    public function testForgottenDayBlocksTodayUntilLateClosureIsRecorded(): void
    {
        $yesterday = new \DateTimeImmutable('yesterday');
        $today = new \DateTimeImmutable('today');
        $settings = static::getContainer()->get(SettingsProvider::class)->get();
        $settings->setCashClosureEnforcementStartDate($yesterday);

        $movement = (new CashMovement())
            ->setType(CashMovement::IN)
            ->setAmount(250)
            ->setSource(CashMovement::SRC_MANUAL)
            ->setCreatedAt($yesterday->setTime(10, 0))
            ->setCreatedBy($this->manager);
        $this->em->persist($movement);
        $this->em->flush();

        self::assertSame(
            $yesterday->format('Y-m-d'),
            $this->guard->findOldestPendingDate($today)?->format('Y-m-d'),
        );

        try {
            $this->guard->assertOpen($today);
            self::fail('La journée courante ne doit pas être ouverte avant la clôture oubliée.');
        } catch (\DomainException $exception) {
            self::assertStringContainsString($yesterday->format('d/m/Y'), $exception->getMessage());
        }

        $closure = $this->register->close(
            $yesterday,
            250,
            $this->manager,
            null,
            'Oubli constaté le lendemain',
        );

        self::assertTrue($closure->isLate());
        self::assertSame('Oubli constaté le lendemain', $closure->getLateReason());
        self::assertNull($this->guard->findOldestPendingDate($today));
        $this->guard->assertOpen($today);
    }

    public function testLateClosureRequiresAReason(): void
    {
        $yesterday = new \DateTimeImmutable('yesterday');
        $settings = static::getContainer()->get(SettingsProvider::class)->get();
        $settings->setCashClosureEnforcementStartDate($yesterday);
        $this->em->flush();

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('motif');
        $this->register->close($yesterday, 0, $this->manager);
    }

    public function testVarianceRequiresAJustification(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('justification');
        $this->register->close(new \DateTimeImmutable('today'), 701, $this->manager);
    }
}

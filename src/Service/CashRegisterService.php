<?php

namespace App\Service;

use App\Entity\CashClosure;
use App\Entity\CashClosureReopening;
use App\Entity\CashMovement;
use App\Entity\User;
use App\Repository\CashClosureRepository;
use App\Repository\CashMovementRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\DBAL\LockMode;

final class CashRegisterService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly CashMovementRepository $movements,
        private readonly CashClosureRepository $closures,
        private readonly CashPeriodGuard $periodGuard,
    ) {
    }

    public function close(
        \DateTimeImmutable $day,
        int $countedBalance,
        User $author,
        ?string $notes = null,
        ?string $lateReason = null,
    ): CashClosure {
        if ($countedBalance < 0) {
            throw new \DomainException('Le solde compté ne peut pas être négatif.');
        }

        $tz = $day->getTimezone();
        $date = $day->setTime(0, 0);
        $today = new \DateTimeImmutable('today', $tz);
        $notes = trim((string) $notes);
        $lateReason = trim((string) $lateReason);
        if ($date < $today && mb_strlen($lateReason) < 5) {
            throw new \DomainException('Un motif d’au moins 5 caractères est obligatoire pour une clôture tardive.');
        }
        $this->periodGuard->assertOpen($date);
        $from = $date;
        $to = $date->setTime(23, 59, 59);

        return $this->em->wrapInTransaction(function () use (
            $date,
            $from,
            $to,
            $countedBalance,
            $author,
            $notes,
            $lateReason,
        ): CashClosure {
            if ($this->closures->isDateClosed($date)) {
                throw new \DomainException('La caisse de cette journée est déjà clôturée.');
            }

            $totalIn = $this->movements->sumBetween($from, $to, CashMovement::IN);
            $totalOut = $this->movements->sumBetween($from, $to, CashMovement::OUT);
            $expectedBalance = $totalIn - $totalOut;
            $variance = $countedBalance - $expectedBalance;
            if ($variance !== 0 && mb_strlen($notes) < 5) {
                throw new \DomainException(
                    'Une justification d’au moins 5 caractères est obligatoire lorsque le solde compté diffère du solde théorique.',
                );
            }

            $closure = $this->closures->findForDate($date) ?? (new CashClosure())->setClosureDate($date);
            if ($closure->getId() !== null && !$this->em->getConnection()->getDatabasePlatform() instanceof SQLitePlatform) {
                $this->em->lock($closure, LockMode::PESSIMISTIC_WRITE);
                $this->em->refresh($closure);
            }
            $activeReopening = $closure->getActiveReopening();
            if ($closure->getId() !== null && !$activeReopening) {
                throw new \DomainException('La caisse de cette journée est déjà clôturée.');
            }

            $closedAt = new \DateTimeImmutable('now', $date->getTimezone());
            $closure
                ->setClosedAt($closedAt)
                ->setTotalIn($totalIn)
                ->setTotalOut($totalOut)
                ->setBalance($expectedBalance)
                ->setCountedBalance($countedBalance)
                ->setVariance($variance)
                ->setClosedBy($author)
                ->setLateReason($lateReason ?: null)
                ->setNotes($notes ?: null);

            if ($activeReopening) {
                $activeReopening->markReclosed($author, $closedAt);
            }

            $this->em->persist($closure);
            try {
                $this->em->flush();
            } catch (UniqueConstraintViolationException) {
                throw new \DomainException('La caisse de cette journée est déjà clôturée.');
            }

            return $closure;
        });
    }

    public function reopen(CashClosure $closure, User $author, string $reason): CashClosureReopening
    {
        $reason = trim($reason);
        if (mb_strlen($reason) < 10) {
            throw new \DomainException('Un motif précis d’au moins 10 caractères est obligatoire pour rouvrir la caisse.');
        }
        if ($closure->isReopened()) {
            throw new \DomainException('Cette journée de caisse est déjà rouverte.');
        }

        $today = new \DateTimeImmutable('today', $closure->getClosureDate()->getTimezone());
        if ($closure->getClosureDate()->format('Y-m-d') !== $today->format('Y-m-d')) {
            throw new \DomainException(
                'Seule la caisse du jour peut être rouverte. Une journée historique reste figée pour préserver la comptabilité.',
            );
        }

        $latest = $this->closures->findLatest();
        if (!$latest || $latest->getId() !== $closure->getId()) {
            throw new \DomainException(
                'Seule la clôture la plus récente peut être rouverte afin de préserver la chronologie de caisse.',
            );
        }

        return $this->em->wrapInTransaction(function () use ($closure, $author, $reason): CashClosureReopening {
            if (!$this->em->getConnection()->getDatabasePlatform() instanceof SQLitePlatform) {
                $this->em->lock($closure, LockMode::PESSIMISTIC_WRITE);
            }
            $this->em->refresh($closure);
            if ($closure->isReopened()) {
                throw new \DomainException('Cette journée de caisse est déjà rouverte.');
            }

            $event = CashClosureReopening::fromClosure($closure, $author, $reason);
            $closure->addReopening($event);
            $this->em->persist($event);
            $this->em->flush();

            return $event;
        });
    }
}

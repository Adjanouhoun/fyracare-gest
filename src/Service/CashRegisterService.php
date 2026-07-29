<?php

namespace App\Service;

use App\Entity\CashClosure;
use App\Entity\CashMovement;
use App\Entity\User;
use App\Repository\CashClosureRepository;
use App\Repository\CashMovementRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;

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

            $closure = (new CashClosure())
                ->setClosureDate($date)
                ->setClosedAt(new \DateTimeImmutable('now', $date->getTimezone()))
                ->setTotalIn($totalIn)
                ->setTotalOut($totalOut)
                ->setBalance($expectedBalance)
                ->setCountedBalance($countedBalance)
                ->setVariance($variance)
                ->setClosedBy($author)
                ->setLateReason($lateReason ?: null)
                ->setNotes($notes ?: null);

            $this->em->persist($closure);
            try {
                $this->em->flush();
            } catch (UniqueConstraintViolationException) {
                throw new \DomainException('La caisse de cette journée est déjà clôturée.');
            }

            return $closure;
        });
    }
}

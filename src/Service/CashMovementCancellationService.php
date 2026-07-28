<?php

namespace App\Service;

use App\Entity\CashMovement;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

final class CashMovementCancellationService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly CashPeriodGuard $periodGuard,
    ) {
    }

    public function cancel(CashMovement $movement, User $author, string $reason): void
    {
        $reason = trim($reason);
        if (mb_strlen($reason) < 5) {
            throw new \DomainException('Le motif d’annulation doit contenir au moins 5 caractères.');
        }
        if (mb_strlen($reason) > 1000) {
            throw new \DomainException('Le motif d’annulation ne peut pas dépasser 1000 caractères.');
        }
        if ($movement->isDeleted()) {
            throw new \DomainException('Cette écriture est déjà annulée.');
        }
        if (
            $movement->getPayment() !== null
            || in_array(
                $movement->getSource(),
                [CashMovement::SRC_PAYMENT, CashMovement::SRC_PAYMENT_REVERSAL],
                true,
            )
        ) {
            throw new \DomainException(
                'Une écriture liée à un paiement doit être annulée depuis le paiement correspondant.',
            );
        }

        $this->periodGuard->assertOpen($movement->getCreatedAt());
        $movement
            ->setDeletedAt(new \DateTimeImmutable())
            ->setDeletedBy($author)
            ->setDeletionReason($reason);
        $this->em->flush();
    }
}

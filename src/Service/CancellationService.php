<?php

namespace App\Service;

use App\Entity\CashMovement;
use App\Entity\AppointmentStatusHistory;
use App\Entity\Payment;
use App\Entity\Rdv;
use App\Entity\User;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;

final class CancellationService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly CashPeriodGuard $periodGuard,
    ) {
    }

    public function cancelAppointment(Rdv $rdv, User $author, string $reason): void
    {
        $reason = $this->validateReason($reason);

        $this->em->wrapInTransaction(function () use ($rdv, $author, $reason): void {
            $this->lock($rdv);
            if ($rdv->isCancelled()) {
                throw new \DomainException('Ce rendez-vous est déjà annulé.');
            }

            $oldStatus = (string) $rdv->getStatus();
            $rdv
                ->setStatus(Rdv::S_ANNULE)
                ->setCancelledAt(new \DateTimeImmutable())
                ->setCancelledBy($author)
                ->setCancellationReason($reason);
            $history = (new AppointmentStatusHistory())
                ->setRdv($rdv)
                ->setOldStatus($oldStatus)
                ->setNewStatus(Rdv::S_ANNULE)
                ->setChangedBy($author)
                ->setNote($reason);
            $this->em->persist($history);
            $this->em->flush();
        });
    }

    public function cancelPayment(Payment $payment, User $author, string $reason): void
    {
        $reason = $this->validateReason($reason);
        if ($payment->getMethode() === Payment::M_ESPECES) {
            $this->periodGuard->assertOpen(new \DateTimeImmutable());
        }

        $this->em->wrapInTransaction(function () use ($payment, $author, $reason): void {
            $this->lock($payment);
            if ($payment->isCancelled()) {
                throw new \DomainException('Ce paiement est déjà annulé.');
            }

            $rdv = $payment->getRdv();
            if (!$rdv) {
                throw new \DomainException('Le rendez-vous associé au paiement est introuvable.');
            }
            $this->lock($rdv);

            $payment
                ->setCancelledAt(new \DateTimeImmutable())
                ->setCancelledBy($author)
                ->setCancellationReason($reason);

            $rdv->updatePaymentStatus();
            if ($payment->getMethode() === Payment::M_ESPECES) {
                $reversal = (new CashMovement())
                    ->setType(CashMovement::OUT)
                    ->setAmount((int) $payment->getAmount())
                    ->setSource(CashMovement::SRC_PAYMENT_REVERSAL)
                    ->setPayment($payment)
                    ->setCreatedBy($author)
                    ->setNotes(sprintf(
                        'Annulation du paiement %s — %s',
                        $payment->getReceiptNumber() ?: '#'.$payment->getId(),
                        $reason,
                    ));
                $this->em->persist($reversal);
            }
            $this->em->flush();
        });
    }

    private function validateReason(string $reason): string
    {
        $reason = trim($reason);
        if (mb_strlen($reason) < 5) {
            throw new \DomainException('Le motif d’annulation doit contenir au moins 5 caractères.');
        }
        if (mb_strlen($reason) > 1000) {
            throw new \DomainException('Le motif d’annulation ne peut pas dépasser 1000 caractères.');
        }

        return $reason;
    }

    private function lock(object $entity): void
    {
        if ($this->em->getConnection()->getDatabasePlatform() instanceof MySQLPlatform) {
            $this->em->lock($entity, LockMode::PESSIMISTIC_WRITE);
            $this->em->refresh($entity);
        }
    }
}

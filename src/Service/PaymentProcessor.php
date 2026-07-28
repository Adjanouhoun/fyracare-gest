<?php

namespace App\Service;

use App\Entity\CashMovement;
use App\Entity\Payment;
use App\Entity\Rdv;
use App\Entity\User;
use Doctrine\DBAL\LockMode;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Ulid;

final class PaymentProcessor
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly CashPeriodGuard $periodGuard,
    ) {
    }

    /**
     * Enregistre le paiement, le reçu, le statut du rendez-vous et le mouvement
     * de caisse dans une seule transaction.
     */
    public function process(Payment $payment, Rdv $rdv, User $author): Payment
    {
        return $this->em->wrapInTransaction(function () use ($payment, $rdv, $author): Payment {
            if (!$this->em->getConnection()->getDatabasePlatform() instanceof SQLitePlatform) {
                $this->em->lock($rdv, LockMode::PESSIMISTIC_WRITE);
                $this->em->refresh($rdv);
            }

            if ($rdv->isCancelled()) {
                throw new \DomainException('Un rendez-vous annulé ne peut pas recevoir de paiement.');
            }
            $this->periodGuard->assertOpen(new \DateTimeImmutable());

            $amount = (int) $payment->getAmount();
            $remainingAmount = $rdv->getRemainingAmount();

            if ($amount <= 0) {
                throw new \DomainException('Le montant doit être strictement positif.');
            }
            if ($remainingAmount <= 0) {
                throw new \DomainException('Ce rendez-vous est déjà entièrement payé.');
            }
            if ($amount > $remainingAmount) {
                throw new \DomainException(sprintf(
                    'Le montant saisi (%d MRU) dépasse le montant restant à payer (%d MRU).',
                    $amount,
                    $remainingAmount,
                ));
            }

            $payment
                ->setRdv($rdv)
                ->setPaidAt(new \DateTimeImmutable())
                ->setReceiptNumber($this->generateReceiptNumber())
                ->setIsDeposit($amount < $remainingAmount)
                ->setReceivedBy($author);

            $rdv->addPayment($payment);
            $rdv->updatePaymentStatus();

            $this->em->persist($payment);
            if ($payment->getMethode() === Payment::M_ESPECES) {
                $movement = (new CashMovement())
                    ->setType(CashMovement::IN)
                    ->setAmount($amount)
                    ->setSource(CashMovement::SRC_PAYMENT)
                    ->setPayment($payment)
                    ->setCreatedBy($author)
                    ->setNotes(sprintf(
                        '%s RDV #%d – %s (Total payé : %d/%d MRU)',
                        $payment->isDeposit() ? 'Acompte' : 'Paiement final',
                        $rdv->getId(),
                        $rdv->getPrestation()?->getLibelle() ?? 'Prestation',
                        $rdv->getTotalPaid(),
                        $rdv->getTotalAmount(),
                    ));
                $this->em->persist($movement);
            }
            $this->em->flush();

            return $payment;
        });
    }

    private function generateReceiptNumber(): string
    {
        return sprintf(
            'PAY-%s-%s',
            (new \DateTimeImmutable())->format('Ymd'),
            (string) new Ulid(),
        );
    }
}

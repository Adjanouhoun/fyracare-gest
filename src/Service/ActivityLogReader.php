<?php

namespace App\Service;

use App\Entity\AppointmentStatusHistory;
use App\Entity\CashClosure;
use App\Entity\CashClosureReopening;
use App\Entity\CashMovement;
use App\Entity\Payment;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

final class ActivityLogReader
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    /**
     * Construit un journal en lecture seule à partir des traces métier existantes.
     *
     * @return array<int, array{
     *     date: \DateTimeImmutable,
     *     category: string,
     *     title: string,
     *     details: string,
     *     actor: string,
     *     route: ?string,
     *     routeParams: array<string, int>
     * }>
     */
    public function recent(int $limitPerSource = 250): array
    {
        $events = [];

        foreach ($this->em->getRepository(Payment::class)->findBy([], ['paidAt' => 'DESC'], $limitPerSource) as $payment) {
            $events[] = $this->event(
                $payment->getPaidAt() ?? new \DateTimeImmutable('@0'),
                'PAYMENT',
                'Encaissement',
                sprintf(
                    '%s MRU — reçu %s',
                    number_format((int) $payment->getAmount(), 0, ',', ' '),
                    $payment->getReceiptNumber() ?? '#'.$payment->getId(),
                ),
                $payment->getReceivedBy(),
                'app_payment_show',
                ['id' => (int) $payment->getId()],
            );

            if ($payment->isCancelled() && $payment->getCancelledAt()) {
                $events[] = $this->event(
                    $payment->getCancelledAt(),
                    'CANCELLATION',
                    'Annulation d’un paiement',
                    sprintf(
                        '%s MRU — %s',
                        number_format((int) $payment->getAmount(), 0, ',', ' '),
                        $payment->getCancellationReason() ?: 'Motif non renseigné',
                    ),
                    $payment->getCancelledBy(),
                    'app_payment_show',
                    ['id' => (int) $payment->getId()],
                );
            }
        }

        foreach ($this->em->getRepository(CashClosure::class)->findBy([], ['closedAt' => 'DESC'], $limitPerSource) as $closure) {
            $events[] = $this->event(
                $closure->getClosedAt(),
                'CLOSURE',
                $closure->isLate() ? 'Clôture tardive de caisse' : 'Clôture de caisse',
                sprintf(
                    'Journée du %s — solde compté %s MRU — écart %s MRU%s',
                    $closure->getClosureDate()->format('d/m/Y'),
                    number_format($closure->getCountedBalance(), 0, ',', ' '),
                    number_format($closure->getVariance(), 0, ',', ' '),
                    $closure->getLateReason() ? ' — '.$closure->getLateReason() : '',
                ),
                $closure->getClosedBy(),
                'cash_closure_show',
                ['id' => (int) $closure->getId()],
            );
        }

        foreach ($this->em->getRepository(CashClosureReopening::class)->findBy([], ['reopenedAt' => 'DESC'], $limitPerSource) as $reopening) {
            $closure = $reopening->getClosure();
            $events[] = $this->event(
                $reopening->getReopenedAt(),
                'CLOSURE_REOPENING',
                'Réouverture de caisse',
                sprintf(
                    'Journée du %s — %s',
                    $closure?->getClosureDate()->format('d/m/Y') ?? 'date indisponible',
                    $reopening->getReason(),
                ),
                $reopening->getReopenedBy(),
                $closure ? 'cash_closure_show' : null,
                $closure ? ['id' => (int) $closure->getId()] : [],
            );
        }

        foreach ($this->em->getRepository(AppointmentStatusHistory::class)->findBy([], ['changedAt' => 'DESC'], $limitPerSource) as $history) {
            $rdv = $history->getRdv();
            $events[] = $this->event(
                $history->getChangedAt(),
                'APPOINTMENT',
                'Changement de statut du rendez-vous',
                sprintf(
                    'RDV #%d : %s → %s%s',
                    $rdv?->getId() ?? 0,
                    $history->getOldStatus(),
                    $history->getNewStatus(),
                    $history->getNote() ? ' — '.$history->getNote() : '',
                ),
                $history->getChangedBy(),
                $rdv ? 'app_rdv_show' : null,
                $rdv ? ['id' => (int) $rdv->getId()] : [],
            );
        }

        $filters = $this->em->getFilters();
        $filterName = 'active_cash_movements';
        $softDeleteWasEnabled = $filters->isEnabled($filterName);
        if ($softDeleteWasEnabled) {
            $filters->disable($filterName);
        }
        try {
            $movements = $this->em->getRepository(CashMovement::class)->findBy(
                [],
                ['createdAt' => 'DESC'],
                $limitPerSource,
            );
        } finally {
            if ($softDeleteWasEnabled) {
                $filters->enable($filterName);
            }
        }

        foreach ($movements as $movement) {
            if (in_array($movement->getSource(), [
                CashMovement::SRC_PAYMENT,
                CashMovement::SRC_PAYMENT_REVERSAL,
            ], true)) {
                continue;
            }

            $events[] = $this->event(
                $movement->getCreatedAt(),
                'CASH',
                $movement->getType() === CashMovement::IN ? 'Entrée de caisse' : 'Dépense de caisse',
                sprintf(
                    '%s MRU — %s%s',
                    number_format($movement->getAmount(), 0, ',', ' '),
                    $movement->getSource(),
                    $movement->getNotes() ? ' — '.$movement->getNotes() : '',
                ),
                $movement->getCreatedBy(),
                null,
                [],
            );

            if ($movement->isDeleted() && $movement->getDeletedAt()) {
                $events[] = $this->event(
                    $movement->getDeletedAt(),
                    'CANCELLATION',
                    'Annulation d’un mouvement de caisse',
                    sprintf(
                        '%s MRU — %s',
                        number_format($movement->getAmount(), 0, ',', ' '),
                        $movement->getDeletionReason() ?: 'Motif non renseigné',
                    ),
                    $movement->getDeletedBy(),
                    null,
                    [],
                );
            }
        }

        foreach ($this->em->getRepository(User::class)->findBy(
            ['isActive' => false],
            ['deactivatedAt' => 'DESC'],
            $limitPerSource,
        ) as $user) {
            if (!$user->getDeactivatedAt()) {
                continue;
            }
            $events[] = $this->event(
                $user->getDeactivatedAt(),
                'USER',
                'Désactivation d’un utilisateur',
                sprintf('%s — %s', $user->getFullname(), $user->getEmail()),
                $user->getDeactivatedBy(),
                'app_user_show',
                ['id' => (int) $user->getId()],
            );
        }

        usort(
            $events,
            static fn (array $left, array $right): int => $right['date'] <=> $left['date'],
        );

        return $events;
    }

    /**
     * @param array<string, int> $routeParams
     * @return array<string, mixed>
     */
    private function event(
        \DateTimeImmutable $date,
        string $category,
        string $title,
        string $details,
        ?User $actor,
        ?string $route,
        array $routeParams,
    ): array {
        return [
            'date' => $date,
            'category' => $category,
            'title' => $title,
            'details' => $details,
            'actor' => $actor?->getFullname() ?: 'Auteur historique non disponible',
            'route' => $route,
            'routeParams' => $routeParams,
        ];
    }
}

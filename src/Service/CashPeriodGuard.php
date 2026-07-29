<?php

namespace App\Service;

use App\Repository\CashClosureRepository;
use App\Repository\CashMovementRepository;

final class CashPeriodGuard
{
    public function __construct(
        private readonly CashClosureRepository $closures,
        private readonly CashMovementRepository $movements,
        private readonly SettingsProvider $settings,
    ) {
    }

    public function assertOpen(\DateTimeImmutable $date): void
    {
        $date = $date->setTime(0, 0);
        $start = $this->settings->get()->getCashClosureEnforcementStartDate();
        if ($date < $start) {
            throw new \DomainException('Cette date précède l’activation du contrôle de clôture.');
        }
        $pending = $this->findOldestPendingDate($date);
        if ($pending) {
            throw new \DomainException(sprintf(
                'La caisse du %s doit être clôturée avant toute nouvelle opération.',
                $pending->format('d/m/Y'),
            ));
        }
        if ($this->closures->isDateClosed($date)) {
            throw new \DomainException(sprintf(
                'La caisse du %s est clôturée. Aucune nouvelle écriture ou annulation n’est autorisée.',
                $date->format('d/m/Y'),
            ));
        }
    }

    public function findOldestPendingDate(\DateTimeImmutable $before): ?\DateTimeImmutable
    {
        $start = $this->settings->get()->getCashClosureEnforcementStartDate();
        foreach ($this->movements->findDistinctMovementDaysBetween($start, $before) as $day) {
            if (!$this->closures->isDateClosed($day)) {
                return $day;
            }
        }

        return null;
    }
}

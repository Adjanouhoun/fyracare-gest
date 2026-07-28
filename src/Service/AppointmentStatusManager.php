<?php

namespace App\Service;

use App\Entity\AppointmentStatusHistory;
use App\Entity\Rdv;
use App\Entity\User;
use Doctrine\DBAL\LockMode;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\ORM\EntityManagerInterface;

final class AppointmentStatusManager
{
    private const TRANSITIONS = [
        Rdv::S_PLANIFIE => [Rdv::S_CONFIRME, Rdv::S_HONORE, Rdv::S_ABSENT],
        Rdv::S_CONFIRME => [Rdv::S_HONORE, Rdv::S_ABSENT],
        Rdv::S_HONORE => [],
        Rdv::S_ABSENT => [],
        Rdv::S_ANNULE => [],
    ];

    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function transition(Rdv $rdv, string $newStatus, User $author, ?string $note = null): void
    {
        $this->em->wrapInTransaction(function () use ($rdv, $newStatus, $author, $note): void {
            if (!$this->em->getConnection()->getDatabasePlatform() instanceof SQLitePlatform) {
                $this->em->lock($rdv, LockMode::PESSIMISTIC_WRITE);
                $this->em->refresh($rdv);
            }

            $oldStatus = (string) $rdv->getStatus();
            if (!in_array($newStatus, self::TRANSITIONS[$oldStatus] ?? [], true)) {
                throw new \DomainException(sprintf(
                    'Le passage de %s vers %s n’est pas autorisé.',
                    $oldStatus,
                    $newStatus,
                ));
            }
            if (
                in_array($newStatus, [Rdv::S_HONORE, Rdv::S_ABSENT], true)
                && $rdv->getStartAt() > new \DateTimeImmutable()
            ) {
                throw new \DomainException(
                    'Un rendez-vous ne peut pas être marqué honoré ou absent avant son heure de début.',
                );
            }

            $history = (new AppointmentStatusHistory())
                ->setRdv($rdv)
                ->setOldStatus($oldStatus)
                ->setNewStatus($newStatus)
                ->setChangedBy($author)
                ->setNote($this->normalizeNote($note));

            $rdv->setStatus($newStatus);
            $this->em->persist($history);
            $this->em->flush();
        });
    }

    /** @return string[] */
    public function allowedTransitions(Rdv $rdv): array
    {
        return self::TRANSITIONS[(string) $rdv->getStatus()] ?? [];
    }

    private function normalizeNote(?string $note): ?string
    {
        $note = trim((string) $note);
        if (mb_strlen($note) > 1000) {
            throw new \DomainException('La note ne peut pas dépasser 1000 caractères.');
        }

        return $note !== '' ? $note : null;
    }
}

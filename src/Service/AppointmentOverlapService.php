<?php
// src/Service/AppointmentOverlapService.php
namespace App\Service;

use App\Entity\Rdv;
use App\Repository\RdvRepository;
use Doctrine\ORM\EntityManagerInterface;

class AppointmentOverlapService
{
    public function __construct(
        private RdvRepository $rdvRepository,
        private EntityManagerInterface $em
    ) {}

    /**
     * Vérifie si un RDV chevauche d'autres RDV existants
     * 
     * @param \DateTimeImmutable $startAt
     * @param \DateTimeImmutable $endAt
     * @param int|null $excludeId ID du RDV à exclure (pour l'édition)
     * @return array Array de RDV qui chevauchent
     */
    public function findOverlappingAppointments(
        \DateTimeImmutable $startAt,
        \DateTimeImmutable $endAt,
        ?int $excludeId = null
    ): array {
        $qb = $this->rdvRepository->createQueryBuilder('r')
            ->leftJoin('r.client', 'c')->addSelect('c')
            ->leftJoin('r.prestation', 'p')->addSelect('p')
            ->where('r.status != :cancelled')
            ->setParameter('cancelled', Rdv::S_ANNULE)
            ->andWhere(
                '(r.startAt < :endAt AND r.endAt > :startAt)'
            )
            ->setParameter('startAt', $startAt)
            ->setParameter('endAt', $endAt)
            ->orderBy('r.startAt', 'ASC');

        if ($excludeId) {
            $qb->andWhere('r.id != :excludeId')
               ->setParameter('excludeId', $excludeId);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Vérifie si un RDV peut être créé/modifié sans chevauchement
     * 
     * @param Rdv $rdv
     * @return bool
     */
    public function canScheduleAppointment(Rdv $rdv): bool
    {
        $overlaps = $this->findOverlappingAppointments(
            $rdv->getStartAt(),
            $rdv->getEndAt(),
            $rdv->getId()
        );

        return count($overlaps) === 0;
    }

    /**
     * Retourne un message d'erreur détaillé en cas de chevauchement
     * 
     * @param Rdv $rdv
     * @return string|null
     */
    public function getOverlapErrorMessage(Rdv $rdv): ?string
    {
        $overlaps = $this->findOverlappingAppointments(
            $rdv->getStartAt(),
            $rdv->getEndAt(),
            $rdv->getId()
        );

        if (empty($overlaps)) {
            return null;
        }

        $messages = [];
        foreach ($overlaps as $overlap) {
            $client = $overlap->getClient() ? $overlap->getClient()->getNometprenom() : 'Client inconnu';
            $prestation = $overlap->getPrestation() ? $overlap->getPrestation()->getLibelle() : 'Prestation inconnue';
            $time = $overlap->getStartAt()->format('H:i') . ' - ' . $overlap->getEndAt()->format('H:i');
            
            $messages[] = sprintf(
                '• %s (%s) de %s',
                $client,
                $prestation,
                $time
            );
        }

        return "⚠️ Ce créneau chevauche " . count($overlaps) . " rendez-vous existant(s) :\n" 
            . implode("\n", $messages);
    }

    /**
     * Trouve les créneaux disponibles pour une durée donnée sur une journée
     * 
     * @param \DateTimeImmutable $date
     * @param int $durationMinutes
     * @param string $openTime (ex: '09:00')
     * @param string $closeTime (ex: '18:00')
     * @return array Array de créneaux disponibles [['start' => DateTime, 'end' => DateTime], ...]
     */
    public function findAvailableSlots(
        \DateTimeImmutable $date,
        int $durationMinutes,
        string $openTime = '09:00',
        string $closeTime = '18:00'
    ): array {
        $tz = $date->getTimezone();
        
        // Définir les bornes de la journée
        $dayStart = \DateTimeImmutable::createFromFormat('Y-m-d H:i', $date->format('Y-m-d') . ' ' . $openTime, $tz);
        $dayEnd = \DateTimeImmutable::createFromFormat('Y-m-d H:i', $date->format('Y-m-d') . ' ' . $closeTime, $tz);

        // Récupérer tous les RDV de la journée
        $existingRdvs = $this->rdvRepository->createQueryBuilder('r')
            ->where('r.startAt >= :dayStart')
            ->andWhere('r.startAt < :dayEnd')
            ->andWhere('r.status != :cancelled')
            ->setParameter('dayStart', $dayStart)
            ->setParameter('dayEnd', $dayEnd)
            ->setParameter('cancelled', Rdv::S_ANNULE)
            ->orderBy('r.startAt', 'ASC')
            ->getQuery()
            ->getResult();

        $availableSlots = [];
        $currentStart = $dayStart;

        foreach ($existingRdvs as $rdv) {
            // Si il y a un écart avant le prochain RDV
            $gapMinutes = ($rdv->getStartAt()->getTimestamp() - $currentStart->getTimestamp()) / 60;
            
            if ($gapMinutes >= $durationMinutes) {
                $availableSlots[] = [
                    'start' => $currentStart,
                    'end' => $currentStart->modify("+{$durationMinutes} minutes")
                ];
            }

            // Mettre à jour le curseur
            $currentStart = $rdv->getEndAt() > $currentStart ? $rdv->getEndAt() : $currentStart;
        }

        // Vérifier s'il reste du temps après le dernier RDV
        $remainingMinutes = ($dayEnd->getTimestamp() - $currentStart->getTimestamp()) / 60;
        if ($remainingMinutes >= $durationMinutes) {
            $availableSlots[] = [
                'start' => $currentStart,
                'end' => $currentStart->modify("+{$durationMinutes} minutes")
            ];
        }

        return $availableSlots;
    }
}
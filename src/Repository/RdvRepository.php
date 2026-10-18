<?php

namespace App\Repository;

use App\Entity\Rdv;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class RdvRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Rdv::class);
    }

    /**
     * QueryBuilder des RDV du jour (00:00:00 → 23:59:59) dans le fuseau Europe/Paris
     */
    public function createQBForDay(\DateTimeImmutable $day, string $timezone = 'Europe/Paris')
    {
        $tz = new \DateTimeZone($timezone);

        $start = (new \DateTimeImmutable($day->format('Y-m-d').' 00:00:00', $tz));
        $end   = (new \DateTimeImmutable($day->format('Y-m-d').' 23:59:59', $tz));

        return $this->createQueryBuilder('r')
            ->andWhere('r.startAt BETWEEN :start AND :end')
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->leftJoin('r.client', 'c')->addSelect('c')
            ->leftJoin('r.prestation', 'p')->addSelect('p')
            ->orderBy('r.startAt', 'ASC');
    }

    /**
     * QueryBuilder pour un statut donné
     */
    public function createQBForStatus(string $status)
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.status = :st')->setParameter('st', $status)
            ->leftJoin('r.client', 'c')->addSelect('c')
            ->leftJoin('r.prestation', 'p')->addSelect('p')
            ->orderBy('r.startAt', 'DESC');
    }

 
    public function countBetween(\DateTimeImmutable $from, \DateTimeImmutable $to): int
    {
        return (int) $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->andWhere('r.startAt BETWEEN :f AND :t')
            ->setParameter('f', $from)->setParameter('t', $to)
            ->getQuery()->getSingleScalarResult();
    }

    /**
     * @return array<int, array{clientId: int, visits: int, lastVisit: string}>
     */
    public function honoredVisitsByClient(
        ?\DateTimeImmutable $from = null,
        ?\DateTimeImmutable $to = null,
    ): array {
        $qb = $this->createQueryBuilder('r')
            ->select('IDENTITY(r.client) AS clientId, COUNT(r.id) AS visits, MAX(r.startAt) AS lastVisit')
            ->andWhere('r.status = :status')
            ->setParameter('status', Rdv::S_HONORE)
            ->groupBy('r.client');

        if ($from) {
            $qb->andWhere('r.startAt >= :visitsFrom')->setParameter('visitsFrom', $from);
        }
        if ($to) {
            $qb->andWhere('r.startAt <= :visitsTo')->setParameter('visitsTo', $to);
        }

        return array_map(static fn (array $row): array => [
            'clientId' => (int) $row['clientId'],
            'visits' => (int) $row['visits'],
            'lastVisit' => (string) $row['lastVisit'],
        ], $qb->getQuery()->getArrayResult());
    }

    /** Top prestations par volume sur une période */
    public function topPrestationsByCount(\DateTimeImmutable $from, \DateTimeImmutable $to, int $limit=5): array
    {
        return $this->createQueryBuilder('r')
            ->select('p.libelle AS libelle, COUNT(r.id) AS n')
            ->join('r.prestation','p')
            ->andWhere('r.startAt BETWEEN :f AND :t')
            ->groupBy('p.id')
            ->orderBy('n','DESC')
            ->setMaxResults($limit)
            ->setParameter('f',$from)->setParameter('t',$to)
            ->getQuery()->getArrayResult();
    }

    /**
     * Compte les RDV par statut entre deux dates (sur startAt)
     * Retour: [ 'PLANIFIE'=>X, 'CONFIRME'=>Y, ... ]
     */

    public function countByStatusBetween(string $status, \DateTimeImmutable $from, \DateTimeImmutable $to): int
    {
        return (int) $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->andWhere('r.startAt BETWEEN :f AND :t')
            ->andWhere('r.status = :st')
            ->setParameter('f', $from)->setParameter('t', $to)->setParameter('st', $status)
            ->getQuery()->getSingleScalarResult();
    }

    public function sumHonoredRevenueBetween(\DateTimeImmutable $from, \DateTimeImmutable $to): int
    {
        return (int) $this->createQueryBuilder('r')
            ->select('COALESCE(SUM(r.priceAtBooking), 0)')
            ->andWhere('r.startAt BETWEEN :from AND :to')
            ->andWhere('r.status = :status')
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->setParameter('status', Rdv::S_HONORE)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /** @return array<string, array<string, int>> */
    public function statusCountsByDayBetween(\DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        $rows = $this->createQueryBuilder('r')
            ->select('r.startAt, r.status')
            ->andWhere('r.startAt BETWEEN :from AND :to')
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->getQuery()
            ->getArrayResult();

        $counts = [];
        foreach ($rows as $row) {
            /** @var \DateTimeInterface $date */
            $date = $row['startAt'];
            $day = $date->format('Y-m-d');
            $status = (string) $row['status'];
            $counts[$day][$status] = ($counts[$day][$status] ?? 0) + 1;
        }

        return $counts;
    }

    /** @return array<string, int> */
    public function honoredRevenueByDayBetween(\DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        $rows = $this->createQueryBuilder('r')
            ->select('r.startAt, r.priceAtBooking')
            ->andWhere('r.startAt BETWEEN :from AND :to')
            ->andWhere('r.status = :status')
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->setParameter('status', Rdv::S_HONORE)
            ->getQuery()
            ->getArrayResult();

        $totals = [];
        foreach ($rows as $row) {
            /** @var \DateTimeInterface $date */
            $date = $row['startAt'];
            $day = $date->format('Y-m-d');
            $totals[$day] = ($totals[$day] ?? 0) + (int) $row['priceAtBooking'];
        }

        return $totals;
    }

    public function sumOutstandingBetween(\DateTimeImmutable $from, \DateTimeImmutable $to): int
    {
        $appointments = $this->createQueryBuilder('r')
            ->leftJoin('r.payments', 'payment')->addSelect('payment')
            ->andWhere('r.startAt BETWEEN :from AND :to')
            ->andWhere('r.status != :cancelled')
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->setParameter('cancelled', Rdv::S_ANNULE)
            ->getQuery()
            ->getResult();

        return array_sum(array_map(
            static fn (Rdv $rdv): int => max(0, $rdv->getRemainingAmount()),
            $appointments,
        ));
    }

    /** @return array<int, array{libelle: string, count: int, ca: int}> */
    public function performanceByPrestationBetween(
        \DateTimeImmutable $from,
        \DateTimeImmutable $to,
        int $limit = 12,
    ): array {
        $rows = $this->createQueryBuilder('r')
            ->select('p.libelle AS libelle, COUNT(r.id) AS totalCount, SUM(r.priceAtBooking) AS revenue')
            ->join('r.prestation', 'p')
            ->andWhere('r.startAt BETWEEN :from AND :to')
            ->andWhere('r.status = :status')
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->setParameter('status', Rdv::S_HONORE)
            ->groupBy('p.id')
            ->orderBy('revenue', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getArrayResult();

        return array_map(static fn (array $row): array => [
            'libelle' => (string) $row['libelle'],
            'count' => (int) $row['totalCount'],
            'ca' => (int) $row['revenue'],
        ], $rows);
    }

    /**
     * Top prestations par volume de RDV dans l’intervalle.
     * Retourne des lignes: ['libelle' => string, 'n' => int]
     */
    public function topPrestationsBetween(\DateTimeImmutable $from, \DateTimeImmutable $to, int $limit = 5): array
    {
        return $this->createQueryBuilder('r')
            ->select('p.libelle AS libelle, COUNT(r.id) AS n')
            ->leftJoin('r.prestation', 'p')
            ->andWhere('r.startAt BETWEEN :f AND :t')
            ->setParameter('f', $from)->setParameter('t', $to)
            ->groupBy('p.id')
            ->orderBy('n', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()->getArrayResult();
    }

    /**
     * Récupère les rendez-vous partiellement payés
     * (ceux avec le statut de paiement = 'PARTIEL')
     */
    public function findPartiallyPaid(int $limit = null): array
    {
        $qb = $this->createQueryBuilder('r')
            ->leftJoin('r.client', 'c')->addSelect('c')
            ->leftJoin('r.prestation', 'p')->addSelect('p')
            ->leftJoin('r.payments', 'pay')->addSelect('pay')
            ->andWhere('r.paymentStatus = :status')
            ->setParameter('status', Rdv::PS_PARTIEL)
            ->orderBy('r.startAt', 'DESC');

        if ($limit) {
            $qb->setMaxResults($limit);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * QueryBuilder pour les rendez-vous partiellement payés (pour pagination)
     */
    public function createQBForPartiallyPaid()
    {
        return $this->createQueryBuilder('r')
            ->leftJoin('r.client', 'c')->addSelect('c')
            ->leftJoin('r.prestation', 'p')->addSelect('p')
            ->leftJoin('r.payments', 'pay')->addSelect('pay')
            ->andWhere('r.paymentStatus = :status')
            ->setParameter('status', Rdv::PS_PARTIEL)
            ->orderBy('r.startAt', 'DESC');
    }

    /**
     * Compte les rendez-vous partiellement payés
     */
    public function countPartiallyPaid(): int
    {
        return (int) $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->andWhere('r.paymentStatus = :status')
            ->setParameter('status', Rdv::PS_PARTIEL)
            ->getQuery()->getSingleScalarResult();
    }

    /** @return Rdv[] */
    public function findAwaitingConfirmation(\DateTimeImmutable $now, \DateTimeImmutable $deadline, int $limit = 20): array
    {
        return $this->createQueryBuilder('r')
            ->leftJoin('r.client', 'c')->addSelect('c')
            ->leftJoin('r.prestation', 'p')->addSelect('p')
            ->andWhere('r.status = :status')
            ->andWhere('r.startAt BETWEEN :now AND :deadline')
            ->setParameter('status', Rdv::S_PLANIFIE)
            ->setParameter('now', $now)
            ->setParameter('deadline', $deadline)
            ->orderBy('r.startAt', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()->getResult();
    }

    /** @return Rdv[] */
    public function findOverdueUnresolved(\DateTimeImmutable $now, int $limit = 20): array
    {
        return $this->createQueryBuilder('r')
            ->leftJoin('r.client', 'c')->addSelect('c')
            ->leftJoin('r.prestation', 'p')->addSelect('p')
            ->andWhere('r.status IN (:statuses)')
            ->andWhere('r.endAt < :now')
            ->setParameter('statuses', [Rdv::S_PLANIFIE, Rdv::S_CONFIRME])
            ->setParameter('now', $now)
            ->orderBy('r.startAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()->getResult();
    }

    public function countAwaitingConfirmation(
        \DateTimeImmutable $now,
        \DateTimeImmutable $deadline,
    ): int {
        return (int) $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->andWhere('r.status = :status')
            ->andWhere('r.startAt BETWEEN :now AND :deadline')
            ->setParameter('status', Rdv::S_PLANIFIE)
            ->setParameter('now', $now)
            ->setParameter('deadline', $deadline)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countOverdueUnresolved(\DateTimeImmutable $now): int
    {
        return (int) $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->andWhere('r.status IN (:statuses)')
            ->andWhere('r.endAt < :now')
            ->setParameter('statuses', [Rdv::S_PLANIFIE, Rdv::S_CONFIRME])
            ->setParameter('now', $now)
            ->getQuery()
            ->getSingleScalarResult();
    }
}

<?php

namespace App\Repository;

use App\Entity\AppointmentStatusHistory;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<AppointmentStatusHistory> */
final class AppointmentStatusHistoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AppointmentStatusHistory::class);
    }

    /** @return array<string, int> */
    public function countNewStatusesBetween(
        \DateTimeImmutable $from,
        \DateTimeImmutable $to,
    ): array {
        $rows = $this->createQueryBuilder('history')
            ->select('history.newStatus AS status, COUNT(history.id) AS total')
            ->andWhere('history.changedAt BETWEEN :from AND :to')
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->groupBy('history.newStatus')
            ->getQuery()
            ->getArrayResult();

        $counts = [];
        foreach ($rows as $row) {
            $counts[(string) $row['status']] = (int) $row['total'];
        }

        return $counts;
    }
}

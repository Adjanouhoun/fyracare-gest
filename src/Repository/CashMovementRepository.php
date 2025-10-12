<?php
namespace App\Repository;

use App\Entity\CashMovement;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class CashMovementRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CashMovement::class);
    }

    public function sumBetween(\DateTimeImmutable $from, \DateTimeImmutable $to, ?string $type = null): int
    {
        $qb = $this->createQueryBuilder('m')
            ->select('COALESCE(SUM(m.amount),0)')
            ->andWhere('m.createdAt BETWEEN :f AND :t')
            ->setParameter('f', $from)->setParameter('t', $to);

        if ($type) {
            $qb->andWhere('m.type = :tp')->setParameter('tp', $type);
        }
        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /** liste paginable/filtrable */
    public function qbList(?\DateTimeImmutable $from = null, ?\DateTimeImmutable $to = null, ?string $type = null, ?string $source = null)
    {
        $qb = $this->createQueryBuilder('m')->orderBy('m.createdAt', 'DESC');
        if ($from) $qb->andWhere('m.createdAt >= :f')->setParameter('f', $from);
        if ($to)   $qb->andWhere('m.createdAt <= :t')->setParameter('t', $to);
        if ($type)   $qb->andWhere('m.type = :tp')->setParameter('tp', $type);
        if ($source) $qb->andWhere('m.source = :sc')->setParameter('sc', $source);
        return $qb;
    }

    public function sumInToday(\DateTimeZone $tz): int {
    $from = (new \DateTimeImmutable('today', $tz))->setTime(0,0,0);
    $to   = (new \DateTimeImmutable('today', $tz))->setTime(23,59,59);
    return $this->sumBetween($from, $to, \App\Entity\CashMovement::IN);
}

    public function sumOutToday(\DateTimeZone $tz): int {
        $from = (new \DateTimeImmutable('today', $tz))->setTime(0,0,0);
        $to   = (new \DateTimeImmutable('today', $tz))->setTime(23,59,59);
        return $this->sumBetween($from, $to, \App\Entity\CashMovement::OUT);
    }

    /** Entrées du jour (uniquement PAYMENTS) */
    public function findTodayEntries(\DateTimeZone $tz): array {
        $from = (new \DateTimeImmutable('today', $tz))->setTime(0,0,0);
        $to   = (new \DateTimeImmutable('today', $tz))->setTime(23,59,59);

        return $this->createQueryBuilder('m')
            ->andWhere('m.createdAt BETWEEN :f AND :t')
            ->andWhere('m.type = :tIn')
            ->andWhere('m.source = :src')
            ->setParameter('f', $from)->setParameter('t', $to)
            ->setParameter('tIn', \App\Entity\CashMovement::IN)
            ->setParameter('src', \App\Entity\CashMovement::SRC_PAYMENT)
            ->orderBy('m.createdAt', 'DESC')
            ->getQuery()->getResult();
    }

    /** Sorties du jour */
    public function findTodayExpenses(\DateTimeZone $tz): array {
        $from = (new \DateTimeImmutable('today', $tz))->setTime(0,0,0);
        $to   = (new \DateTimeImmutable('today', $tz))->setTime(23,59,59);

        return $this->createQueryBuilder('m')
            ->andWhere('m.createdAt BETWEEN :f AND :t')
            ->andWhere('m.type = :tOut')
            ->setParameter('f', $from)->setParameter('t', $to)
            ->setParameter('tOut', \App\Entity\CashMovement::OUT)
            ->orderBy('m.createdAt', 'DESC')
            ->getQuery()->getResult();
    }

    /** QB Dépenses d’un mois (OUT) */
    public function qbExpensesForMonth(int $year, int $month) {
        $from = (new \DateTimeImmutable(sprintf('%04d-%02d-01', $year, $month)))->setTime(0,0,0);
        $to   = $from->modify('last day of this month')->setTime(23,59,59);

        return $this->createQueryBuilder('m')
            ->andWhere('m.createdAt BETWEEN :f AND :t')
            ->andWhere('m.type = :tOut')
            ->setParameter('f', $from)->setParameter('t', $to)
            ->setParameter('tOut', \App\Entity\CashMovement::OUT)
            ->orderBy('m.createdAt', 'DESC');
    }

    /** QB Dépenses d’une année (OUT) */
    public function qbExpensesForYear(int $year) {
        $from = (new \DateTimeImmutable(sprintf('%04d-01-01', $year)))->setTime(0,0,0);
        $to   = (new \DateTimeImmutable(sprintf('%04d-12-31', $year)))->setTime(23,59,59);

        return $this->createQueryBuilder('m')
            ->andWhere('m.createdAt BETWEEN :f AND :t')
            ->andWhere('m.type = :tOut')
            ->setParameter('f', $from)->setParameter('t', $to)
            ->setParameter('tOut', \App\Entity\CashMovement::OUT)
            ->orderBy('m.createdAt', 'DESC');
    }

    public function sumExpensesForMonth(int $year, int $month): int {
        return (int) $this->qbExpensesForMonth($year, $month)
            ->select('COALESCE(SUM(m.amount),0)')
            ->getQuery()->getSingleScalarResult();
    }

    public function sumExpensesForYear(int $year): int {
        return (int) $this->qbExpensesForYear($year)
            ->select('COALESCE(SUM(m.amount),0)')
            ->getQuery()->getSingleScalarResult();
    }

    /**
     * Entrées (SRC_PAYMENT) entre deux dates incluses.
     */
    public function findEntriesBetween(\DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.createdAt BETWEEN :from AND :to')
            ->andWhere('m.source = :src')
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->setParameter('src', CashMovement::SRC_PAYMENT)
            ->orderBy('m.createdAt', 'ASC')
            ->getQuery()->getResult();
    }

    /**
     * Somme des entrées sur la période.
     */
    public function sumEntriesBetween(\DateTimeImmutable $from, \DateTimeImmutable $to): int
    {
        $sum = $this->createQueryBuilder('m')
            ->select('COALESCE(SUM(m.amount), 0) as total')
            ->andWhere('m.createdAt BETWEEN :from AND :to')
            ->andWhere('m.source = :src')
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->setParameter('src', CashMovement::SRC_PAYMENT)
            ->getQuery()->getSingleScalarResult();

        return (int)$sum;
    }

    /**
     * Regroupe les entrées par jour en PHP (évite les fonctions SQL spécifiques).
     * Retourne un tableau: [['d' => 'YYYY-MM-DD', 'total' => 1234], ...]
     */
    public function entriesPerDay(\DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        $rows = $this->findEntriesBetween($from, $to);

        $byDay = [];
        foreach ($rows as $m) {
            /** @var CashMovement $m */
            $key = $m->getCreatedAt()->format('Y-m-d');
            $byDay[$key] = ($byDay[$key] ?? 0) + $m->getAmount();
        }

        ksort($byDay);
        $out = [];
        foreach ($byDay as $d => $total) {
            $out[] = ['d' => $d, 'total' => $total];
        }
        return $out;
    }

    /**
     * Tous les mouvements d’un type entre deux dates (inclus) triés par date.
     * @return CashMovement[]
     */
    public function findByPeriodAndType(\DateTimeImmutable $from, \DateTimeImmutable $to, string $type): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.type = :t')->setParameter('t', $type)
            ->andWhere('c.createdAt BETWEEN :from AND :to')
            ->setParameter('from', $from)->setParameter('to', $to)
            ->orderBy('c.createdAt', 'ASC')
            ->getQuery()->getResult();
    }

    // Sommes “tout le temps”
    public function sumAll(string|int $type): int {
        return (int) $this->createQueryBuilder('m')
            ->select('COALESCE(SUM(m.amount),0)')
            ->andWhere('m.type = :t')->setParameter('t', $type)
            ->getQuery()->getSingleScalarResult();
    }

    // Caisse actuelle (IN - OUT)
    public function currentCash(): int {
        $in  = $this->sumAll(\App\Entity\CashMovement::IN);
        $out = $this->sumAll(\App\Entity\CashMovement::OUT);
        return $in - $out;
    }

    public function findAllCategories(): array
    {
        return $this->createQueryBuilder('m')
            ->select('DISTINCT cat.id, cat.name')
            ->leftJoin('m.category', 'cat')
            ->where('m.category IS NOT NULL')
            ->orderBy('cat.name', 'ASC')
            ->getQuery()
            ->getArrayResult();
    }

    public function sumByCategory(\DateTimeInterface $from, \DateTimeInterface $to): array
    {
        return $this->createQueryBuilder('m')
            ->select('COALESCE(cat.name, :uncat) AS name')
            ->addSelect('SUM(m.amount) AS total')
            ->leftJoin('m.category', 'cat')
            ->andWhere('m.type = :out')
            ->andWhere('m.createdAt BETWEEN :from AND :to')
            ->setParameter('out', CashMovement::OUT)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->setParameter('uncat', 'Non catégorisée')
            ->groupBy('cat.name')
            ->getQuery()
            ->getArrayResult();
    }
}

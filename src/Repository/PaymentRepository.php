<?php

namespace App\Repository;

use App\Entity\Payment;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Payment>
 */
class PaymentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Payment::class);
    }

    public function sumAmount(): float
    {
        return (float) $this->createQueryBuilder('p')
            ->select('COALESCE(SUM(p.amount), 0)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function sumBetween(\DateTimeImmutable $from, \DateTimeImmutable $to): int
    {
        return (int)($this->createQueryBuilder('p')
            ->select('COALESCE(SUM(p.amount),0)')
            ->andWhere('p.paidAt BETWEEN :f AND :t')
            ->setParameter('f',$from)->setParameter('t',$to)
            ->getQuery()->getSingleScalarResult());
    }

    /**
     * CA par jour entre $from et $to (portable)
     * Retourne: [ ['d' => 'YYYY-mm-dd', 'total' => 1234], ... ]
     */
    public function dailyRevenue(\DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        // 1) Préparer toutes les dates avec total=0
        $tz     = $from->getTimezone();
        $cursor = $from->setTime(0,0);
        $end    = $to->setTime(23,59,59);

        $buckets = [];
        while ($cursor <= $end) {
            $buckets[$cursor->format('Y-m-d')] = 0;
            $cursor = $cursor->modify('+1 day');
        }

        // 2) Récupérer les paiements et agréger en PHP
        $rows = $this->createQueryBuilder('p')
            ->select('p.paidAt, p.amount')
            ->andWhere('p.paidAt BETWEEN :f AND :t')
            ->setParameter('f', $from)
            ->setParameter('t', $to)
            ->orderBy('p.paidAt', 'ASC')
            ->getQuery()
            ->getArrayResult();

        foreach ($rows as $r) {
            // $r['paidAt'] est un \DateTimeImmutable ou \DateTimeInterface
            /** @var \DateTimeInterface $dt */
            $dt = $r['paidAt'];
            $key = $dt->format('Y-m-d');
            if (!isset($buckets[$key])) {
                $buckets[$key] = 0; // sécurité si en dehors (rare)
            }
            $buckets[$key] += (int)$r['amount'];
        }

        // 3) Normaliser pour la vue
        $out = [];
        foreach ($buckets as $d => $total) {
            $out[] = ['d' => $d, 'total' => $total];
        }
        return $out;
    }

    /**
     * Top N prestations par CA (via paiements liés aux RDV)
     * Retour: [ ['libelle'=>'...', 'total'=>12345], ... ]
     */
    public function topPrestationsRevenue(int $limit = 5, ?\DateTimeImmutable $from=null, ?\DateTimeImmutable $to=null): array
    {
        $qb = $this->createQueryBuilder('pay')
            ->select('p.libelle AS libelle, SUM(pay.amount) AS total')
            ->join('pay.rdv','r')
            ->join('r.prestation','p')
            ->groupBy('p.id')
            ->orderBy('total','DESC')
            ->setMaxResults($limit);

        if ($from) $qb->andWhere('pay.paidAt >= :f')->setParameter('f', $from);
        if ($to)   $qb->andWhere('pay.paidAt <= :t')->setParameter('t', $to);

        return $qb->getQuery()->getArrayResult();
    }


    public function sumRevenueBetween(\DateTimeImmutable $from, \DateTimeImmutable $to): int
    {
        return (int) ($this->createQueryBuilder('p')
            ->select('COALESCE(SUM(p.amount),0)')
            ->andWhere('p.paidAt BETWEEN :f AND :t')
            ->setParameter('f', $from)->setParameter('t', $to)
            ->getQuery()->getSingleScalarResult() ?? 0);
    }

    /** CA indexé par jour 'Y-m-d' entre 2 dates (inclus) */
    public function revenueByDayBetween(\DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        $rows = $this->createQueryBuilder('p')
            ->andWhere('p.paidAt BETWEEN :f AND :t')
            ->setParameter('f', $from)->setParameter('t', $to)
            ->orderBy('p.paidAt', 'ASC')
            ->getQuery()->getResult();

        $totals = [];
        /** @var Payment $p */
        foreach ($rows as $p) {
            $key = $p->getPaidAt()->setTime(0,0)->format('Y-m-d');
            $totals[$key] = ($totals[$key] ?? 0) + (int) $p->getAmount();
        }
        ksort($totals);
        return $totals;
    }

    /** Top prestations par CA entre 2 dates */
    public function topPrestationsRevenueBetween(\DateTimeImmutable $from, \DateTimeImmutable $to, int $limit = 8): array
    {
        // jointure Payment -> Rdv -> Prestation
        return $this->createQueryBuilder('p')
            ->select('pr.libelle AS libelle, SUM(p.amount) AS total')
            ->join('p.rdv', 'r')
            ->join('r.prestation', 'pr')
            ->andWhere('p.paidAt BETWEEN :f AND :t')
            ->setParameter('f', $from)->setParameter('t', $to)
            ->groupBy('pr.id')
            ->orderBy('total', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()->getArrayResult();
    }

    public function findBetween(\DateTimeImmutable $from, \DateTimeImmutable $to): array {
        return $this->createQueryBuilder('p')
            ->andWhere('p.paidAt BETWEEN :from AND :to')
            ->setParameter('from', $from)->setParameter('to', $to)
            ->leftJoin('p.rdv','r')->addSelect('r')
            ->leftJoin('r.client','c')->addSelect('c')
            ->leftJoin('r.prestation','pr')->addSelect('pr')
            ->orderBy('p.paidAt','ASC')
            ->getQuery()->getResult();
    }

}

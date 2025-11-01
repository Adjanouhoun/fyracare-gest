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
}

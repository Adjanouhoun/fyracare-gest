<?php

namespace App\Repository;

use App\Entity\Prestation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class PrestationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Prestation::class);
    }

    public function searchQb(?string $q, ?string $status = null)
    {
        $qb = $this->createQueryBuilder('p')
            ->orderBy('p.libelle', 'ASC');

        if ($q !== null && $q !== '') {
            $qb->andWhere('LOWER(p.libelle) LIKE :q OR LOWER(p.description) LIKE :q')
            ->setParameter('q', '%'.mb_strtolower($q).'%');
        }

        if ($status === 'active') {
            $qb->andWhere('p.isActive = :a')->setParameter('a', true);
        } elseif ($status === 'inactive') {
            $qb->andWhere('p.isActive = :a')->setParameter('a', false);
        }

        return $qb;
    }

    /** @deprecated — gardée pour compat, préfère searchQb + paginator */
    public function search(?string $q, ?string $status = null): array
    {
        return $this->searchQb($q, $status)->getQuery()->getResult();
    }

}

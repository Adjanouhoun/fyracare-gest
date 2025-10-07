<?php

namespace App\Repository;

use App\Entity\Client;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class ClientRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Client::class);
    }

    /**
     * Retourne un QueryBuilder filtrable (pour KnpPaginator)
     */
    public function searchQb(?string $q = null)
    {
        $qb = $this->createQueryBuilder('c')
            ->orderBy('c.createdAt', 'DESC');

        if ($q !== null && $q !== '') {
            $qb->andWhere('LOWER(c.nometprenom) LIKE :q OR LOWER(c.email) LIKE :q OR c.telephone LIKE :qraw')
               ->setParameter('q', '%'.mb_strtolower($q).'%')
               ->setParameter('qraw', '%'.$q.'%');
        }

        return $qb;
    }
}

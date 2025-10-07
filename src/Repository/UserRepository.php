<?php
// src/Repository/UserRepository.php
namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class UserRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry) { parent::__construct($registry, User::class); }

    public function createSearchQB(?string $q): \Doctrine\ORM\QueryBuilder
    {
        $qb = $this->createQueryBuilder('u')->orderBy('u.fullname', 'ASC');
        if ($q = trim((string)$q)) {
            $qb->andWhere('LOWER(u.fullname) LIKE :q OR LOWER(u.email) LIKE :q')
               ->setParameter('q', '%'.mb_strtolower($q).'%');
        }
        return $qb;
    }
}

<?php
namespace App\Repository;

use App\Entity\CashClosure;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class CashClosureRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CashClosure::class);
    }

    public function findForDate(\DateTimeImmutable $date): ?CashClosure
    {
        return $this->findOneBy(['closureDate' => $date->setTime(0, 0)]);
    }

    public function isDateClosed(\DateTimeImmutable $date): bool
    {
        return $this->findForDate($date) !== null;
    }
}

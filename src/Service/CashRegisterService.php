<?php
namespace App\Service;

use App\Entity\{Payment, CashClosure};
use Doctrine\ORM\EntityManagerInterface;

class CashRegisterService
{
    public function __construct(private EntityManagerInterface $em) {}

    public function closeDay(\DateTimeImmutable $day): CashClosure
    {
        $start=$day->setTime(0,0); $end=$day->setTime(23,59,59);
        $payments=$this->em->getRepository(Payment::class)->createQueryBuilder('p')
            ->andWhere('p.paidAt BETWEEN :s AND :e')->setParameters(['s'=>$start,'e'=>$end])
            ->getQuery()->getResult();

        $t=['ESPECES'=>0.0,'CARTE'=>0.0,'VIREMENT'=>0.0];
        foreach($payments as $p){ $t[$p->getMethod()] = ($t[$p->getMethod()] ?? 0)+ (float)$p->getAmount(); }

        $c=(new CashClosure())
            ->setDay($day)
            ->setTotalCash(number_format($t['ESPECES'] ?? 0,2,'.',''))
            ->setTotalCard(number_format($t['CARTE'] ?? 0,2,'.',''))
            ->setTotalWire(number_format($t['VIREMENT'] ?? 0,2,'.',''))
            ->setGrandTotal(number_format(array_sum($t),2,'.',''));
        $this->em->persist($c); $this->em->flush();
        return $c;
    }
}

<?php
namespace App\Service;

use App\Entity\CashClosure;
use App\Entity\CashMovement;
use App\Repository\CashClosureRepository;
use App\Repository\CashMovementRepository;
use Doctrine\ORM\EntityManagerInterface;

class CashRegisterService
{
    public function __construct(
        private EntityManagerInterface $em,
        private CashMovementRepository $movRepo,
        private CashClosureRepository $closureRepo,
    ){}

    public function closeDay(\DateTimeImmutable $day, ?string $tzName = null): CashClosure
    {
        $tz = new \DateTimeZone($tzName ?: \date_default_timezone_get());
        $from = (new \DateTimeImmutable($day->format('Y-m-d').' 00:00:00', $tz));
        $to   = (new \DateTimeImmutable($day->format('Y-m-d').' 23:59:59', $tz));

        // Mouvements non encore rattachés à une clôture sur la journée
        $rows = $this->em->createQueryBuilder()
            ->select('m')->from(CashMovement::class,'m')
            ->andWhere('m.createdAt BETWEEN :f AND :t')
            ->andWhere('m.closure IS NULL')
            ->setParameter('f',$from)->setParameter('t',$to)
            ->orderBy('m.createdAt','ASC')
            ->getQuery()->getResult();

        $totalIn  = array_sum(array_map(fn(CashMovement $m)=>$m->getType()===CashMovement::IN ? $m->getAmount():0, $rows));
        $totalOut = array_sum(array_map(fn(CashMovement $m)=>$m->getType()===CashMovement::OUT? $m->getAmount():0, $rows));

        $prev    = $this->closureRepo->findLast();
        $opening = $prev?->getClosingBalance() ?? 0;
        $closing = $opening + $totalIn - $totalOut;

        $closure = (new CashClosure())
            ->setFromAt($from)->setToAt($to)
            ->setTotalIn($totalIn)->setTotalOut($totalOut)
            ->setOpeningBalance($opening)->setClosingBalance($closing)
            ->setCode($this->generateCode($from));

        $this->em->persist($closure);
        foreach ($rows as $m) { $m->setClosure($closure); }
        $this->em->flush();

        return $closure;
    }

    private function generateCode(\DateTimeImmutable $d): string
    {
        return 'CLO-'.$d->format('Ymd').'-'.random_int(100,999);
    }
}

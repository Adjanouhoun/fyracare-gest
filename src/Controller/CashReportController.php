<?php

namespace App\Controller;

use App\Entity\CashMovement;
use App\Entity\Payment;
use App\Repository\CashMovementRepository;
use App\Repository\ExpenseCategoryRepository;
use App\Repository\PaymentRepository;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/cash', name: 'cash_')]
#[IsGranted('ROLE_MANAGER')]
final class CashReportController extends AbstractController
{
    #[Route('/reports', name: 'reports', methods: ['GET'])]
    public function index(
        Request $request,
        CashMovementRepository $movements,
        ExpenseCategoryRepository $categories,
        PaymentRepository $payments,
        PaginatorInterface $paginator,
    ): Response {
        $timezone = new \DateTimeZone(\date_default_timezone_get());
        $today = new \DateTimeImmutable('today', $timezone);
        $period = $request->query->getString('period', 'month');
        if (!in_array($period, ['day', 'month', 'year'], true)) {
            $period = 'month';
        }
        $nature = $request->query->getString('nature', 'all');
        if (!in_array($nature, ['all', 'entries', 'injections', 'expenses', 'refunds'], true)) {
            $nature = 'all';
        }
        $category = $nature === 'expenses' && $request->query->getInt('category') > 0
            ? $categories->find($request->query->getInt('category'))
            : null;
        $paymentMethod = $nature === 'entries' ? $request->query->getString('method', 'all') : 'all';
        if (!in_array($paymentMethod, ['all', Payment::M_ESPECES, Payment::M_MOBILE], true)) {
            $paymentMethod = 'all';
        }

        [$from, $to, $periodLabel, $chartLabels] = $this->resolvePeriod($request, $today, $timezone, $period);

        $listQb = $movements->createQueryBuilder('m')
            ->leftJoin('m.category', 'category')->addSelect('category')
            ->leftJoin('m.payment', 'payment')->addSelect('payment')
            ->leftJoin('m.createdBy', 'author')->addSelect('author')
            ->andWhere('m.createdAt BETWEEN :from AND :to')
            ->andWhere('m.deletedAt IS NULL')
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->orderBy('m.createdAt', 'DESC');
        $this->applyNatureFilter($listQb, $nature);
        if ($category !== null) {
            $listQb->andWhere('m.category = :category')->setParameter('category', $category);
        }

        $journal = $paginator->paginate($listQb, $request->query->getInt('page', 1), 25);
        $allMovementsQb = $movements->createQueryBuilder('m')
            ->andWhere('m.createdAt BETWEEN :from AND :to')
            ->andWhere('m.deletedAt IS NULL')
            ->setParameter('from', $from)
            ->setParameter('to', $to);
        $this->applyNatureFilter($allMovementsQb, $nature);
        if ($category !== null) {
            $allMovementsQb->andWhere('m.category = :category')->setParameter('category', $category);
        }
        $allMovements = $allMovementsQb->getQuery()->getResult();

        $series = [
            'entries' => array_fill(0, count($chartLabels), 0),
            'injections' => array_fill(0, count($chartLabels), 0),
            'expenses' => array_fill(0, count($chartLabels), 0),
            'refunds' => array_fill(0, count($chartLabels), 0),
        ];
        $totals = [
            'entries' => 0,
            'injections' => 0,
            'expenses' => 0,
            'refunds' => 0,
        ];
        foreach ($allMovements as $movement) {
            \assert($movement instanceof CashMovement);
            $index = match ($period) {
                'day' => (int) $movement->getCreatedAt()->format('G'),
                'month' => (int) $movement->getCreatedAt()->format('j') - 1,
                default => (int) $movement->getCreatedAt()->format('n') - 1,
            };
            $bucket = $this->movementNature($movement);
            $series[$bucket][$index] += $movement->getAmount();
            $totals[$bucket] += $movement->getAmount();
        }

        $totals['net'] = $totals['entries'] + $totals['injections'] - $totals['expenses'] - $totals['refunds'];

        $paymentJournal = null;
        $cancelledMobile = 0;
        if ($nature === 'entries') {
            $paymentListQb = $payments->createQueryBuilder('p')
                ->leftJoin('p.rdv', 'rdv')->addSelect('rdv')
                ->leftJoin('rdv.client', 'client')->addSelect('client')
                ->leftJoin('rdv.prestation', 'prestation')->addSelect('prestation')
                ->leftJoin('p.receivedBy', 'receiver')->addSelect('receiver')
                ->andWhere('p.paidAt BETWEEN :from AND :to')
                ->andWhere('p.cancelledAt IS NULL')
                ->setParameter('from', $from)
                ->setParameter('to', $to)
                ->orderBy('p.paidAt', 'DESC');
            if ($paymentMethod !== 'all') {
                $paymentListQb->andWhere('p.methode = :method')->setParameter('method', $paymentMethod);
            }
            $paymentJournal = $paginator->paginate(
                $paymentListQb,
                $request->query->getInt('page', 1),
                25,
            );

            $validPayments = $payments->createQueryBuilder('p')
                ->andWhere('p.paidAt BETWEEN :from AND :to')
                ->andWhere('p.cancelledAt IS NULL')
                ->setParameter('from', $from)
                ->setParameter('to', $to)
                ->getQuery()
                ->getResult();
            $series['cash'] = array_fill(0, count($chartLabels), 0);
            $series['mobile'] = array_fill(0, count($chartLabels), 0);
            $totals['entriesCash'] = 0;
            $totals['entriesMobile'] = 0;
            foreach ($validPayments as $payment) {
                \assert($payment instanceof Payment);
                $index = match ($period) {
                    'day' => (int) $payment->getPaidAt()->format('G'),
                    'month' => (int) $payment->getPaidAt()->format('j') - 1,
                    default => (int) $payment->getPaidAt()->format('n') - 1,
                };
                $key = $payment->getMethode() === Payment::M_MOBILE ? 'mobile' : 'cash';
                $series[$key][$index] += (int) $payment->getAmount();
                $totals[$key === 'cash' ? 'entriesCash' : 'entriesMobile'] += (int) $payment->getAmount();
            }
            $totals['entriesAll'] = $totals['entriesCash'] + $totals['entriesMobile'];
            $totals['entries'] = match ($paymentMethod) {
                Payment::M_ESPECES => $totals['entriesCash'],
                Payment::M_MOBILE => $totals['entriesMobile'],
                default => $totals['entriesAll'],
            };
            $totals['net'] = $totals['entries'];

            $cancelledMobile = (int) $payments->createQueryBuilder('p')
                ->select('COALESCE(SUM(p.amount), 0)')
                ->andWhere('p.paidAt BETWEEN :from AND :to')
                ->andWhere('p.methode = :mobile')
                ->andWhere('p.cancelledAt IS NOT NULL')
                ->setParameter('from', $from)
                ->setParameter('to', $to)
                ->setParameter('mobile', Payment::M_MOBILE)
                ->getQuery()
                ->getSingleScalarResult();
        }

        return $this->render('cash/reports.html.twig', [
            'from' => $from,
            'to' => $to,
            'period' => $period,
            'nature' => $nature,
            'categories' => $categories->findBy([], ['name' => 'ASC']),
            'selectedCategory' => $category,
            'paymentMethod' => $paymentMethod,
            'paymentJournal' => $paymentJournal,
            'cancelledMobile' => $cancelledMobile,
            'periodLabel' => $periodLabel,
            'journal' => $journal,
            'totals' => $totals,
            'chartLabels' => $chartLabels,
            'series' => $series,
        ]);
    }

    /** @return array{\DateTimeImmutable, \DateTimeImmutable, string, string[]} */
    private function resolvePeriod(
        Request $request,
        \DateTimeImmutable $today,
        \DateTimeZone $timezone,
        string $period,
    ): array {
        if ($period === 'day') {
            try {
                $day = $request->query->getString('date') !== ''
                    ? new \DateTimeImmutable($request->query->getString('date'), $timezone)
                    : $today;
            } catch (\Throwable) {
                $day = $today;
            }
            return [
                $day->setTime(0, 0),
                $day->setTime(23, 59, 59),
                $day->format('d/m/Y'),
                array_map(static fn (int $hour): string => sprintf('%02dh', $hour), range(0, 23)),
            ];
        }

        $year = $request->query->getInt('year', (int) $today->format('Y'));
        $year = min(max($year, 2000), 2100);
        if ($period === 'year') {
            $from = new \DateTimeImmutable(sprintf('%04d-01-01', $year), $timezone);
            return [
                $from,
                $from->setDate($year, 12, 31)->setTime(23, 59, 59),
                (string) $year,
                ['Jan', 'Fév', 'Mar', 'Avr', 'Mai', 'Juin', 'Juil', 'Août', 'Sep', 'Oct', 'Nov', 'Déc'],
            ];
        }

        $month = min(max($request->query->getInt('month', (int) $today->format('n')), 1), 12);
        $from = new \DateTimeImmutable(sprintf('%04d-%02d-01', $year, $month), $timezone);
        $days = (int) $from->format('t');
        return [
            $from,
            $from->modify('last day of this month')->setTime(23, 59, 59),
            $from->format('m/Y'),
            array_map(static fn (int $day): string => sprintf('%02d', $day), range(1, $days)),
        ];
    }

    private function applyNatureFilter(\Doctrine\ORM\QueryBuilder $qb, string $nature): void
    {
        match ($nature) {
            'entries' => $qb->andWhere('m.type = :in AND m.source = :payment')
                ->setParameter('in', CashMovement::IN)
                ->setParameter('payment', CashMovement::SRC_PAYMENT),
            'injections' => $qb->andWhere('m.type = :in AND m.source != :payment')
                ->setParameter('in', CashMovement::IN)
                ->setParameter('payment', CashMovement::SRC_PAYMENT),
            'expenses' => $qb->andWhere('m.type = :out AND m.source = :expense')
                ->setParameter('out', CashMovement::OUT)
                ->setParameter('expense', CashMovement::SRC_EXPENSE),
            'refunds' => $qb->andWhere('m.type = :out AND m.source = :refund')
                ->setParameter('out', CashMovement::OUT)
                ->setParameter('refund', CashMovement::SRC_PAYMENT_REVERSAL),
            default => null,
        };
    }

    private function movementNature(CashMovement $movement): string
    {
        if ($movement->getType() === CashMovement::IN) {
            return $movement->getSource() === CashMovement::SRC_PAYMENT ? 'entries' : 'injections';
        }
        return $movement->getSource() === CashMovement::SRC_PAYMENT_REVERSAL ? 'refunds' : 'expenses';
    }
}

<?php

namespace App\Controller;

use App\Entity\CashClosure;
use App\Entity\CashMovement;
use App\Form\CashClosureType;
use App\Form\CashExpenseType;
use App\Repository\CashMovementRepository;
use App\Repository\CashClosureRepository;
use App\Repository\PaymentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface; 
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/cash', name: 'cash_')]
class CashController extends AbstractController
{
    #[Route('/', name: 'index', methods: ['GET'])]
    public function index(
        Request $request,
        CashMovementRepository $mvRepo,
        PaginatorInterface $paginator
    ): Response {
        $tz = new \DateTimeZone(\date_default_timezone_get());

        $entriesToday  = $mvRepo->findTodayEntries($tz);   // tableau
        $expensesToday = $mvRepo->findTodayExpenses($tz);  // tableau

        $totalIn  = $mvRepo->sumInToday($tz);
        $totalOut = $mvRepo->sumOutToday($tz);
        $balance  = $totalIn - $totalOut;
        $currentCash = $mvRepo->currentCash();

        $year  = (int) (new \DateTimeImmutable('now', $tz))->format('Y');
        $month = (int) (new \DateTimeImmutable('now', $tz))->format('m');

        // Pagination (même si c'est un tableau, KNP le gère)
        $entriesPagination = $paginator->paginate(
            $entriesToday,
            $request->query->getInt('page_in', 1),
            10,
            ['pageParameterName' => 'page_in']
        );
        $expensesPagination = $paginator->paginate(
            $expensesToday,
            $request->query->getInt('page_out', 1),
            10,
            ['pageParameterName' => 'page_out']
        );

        return $this->render('cash/index.html.twig', [
            'entriesToday'        => $entriesPagination, // 👈 paginé
            'expensesToday'       => $expensesPagination, // 👈 paginé
            'totalIn'             => $totalIn,
            'totalOut'            => $totalOut,
            'balance'             => $balance,
            'year'                => $year,
            'month'               => $month,
            'currentCash'         => $currentCash,
        ]);
    }

    #[Route('/expense/new', name: 'expense_new', methods: ['GET', 'POST'])]
    public function newExpense(
        Request $request,
        EntityManagerInterface $em,
        CashMovementRepository $mvRepo
    ): Response {
        $tz = new \DateTimeZone(\date_default_timezone_get());
        $availableToday = $mvRepo->sumInToday($tz) - $mvRepo->sumOutToday($tz);

        $mv = new CashMovement();
        $mv->setType(CashMovement::OUT)
           ->setSource(CashMovement::SRC_EXPENSE);

        $form = $this->createForm(CashExpenseType::class, $mv);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($mv->getAmount() <= 0) {
                $this->addFlash('danger', 'Le montant doit être supérieur à 0.');
            } elseif ($mv->getAmount() > $availableToday) {
                $this->addFlash('danger', 'Impossible : la dépense dépasse le solde disponible de la journée ('
                    . number_format($availableToday, 0, ',', ' ') . ' MRU).');
            } else {
                $em->persist($mv);
                $em->flush();
                $this->addFlash('success', 'Dépense enregistrée.');
                return $this->redirectToRoute('cash_index');
            }
        }

        return $this->render('cash/new_expense.html.twig', [
            'form'           => $form,
            'availableToday' => $availableToday,
        ]);
    }

    #[Route('/close', name: 'close', methods: ['GET', 'POST'])]
    public function closeDay(
        Request $request,
        CashMovementRepository $mvRepo,
        EntityManagerInterface $em
    ): Response {
        $tz   = new \DateTimeZone(\date_default_timezone_get());
        $from = (new \DateTimeImmutable('today', $tz))->setTime(0, 0);
        $to   = (new \DateTimeImmutable('today', $tz))->setTime(23, 59, 59);

        $totalIn  = $mvRepo->sumBetween($from, $to, CashMovement::IN);
        $totalOut = $mvRepo->sumBetween($from, $to, CashMovement::OUT);
        $balance  = $totalIn - $totalOut;

        $closure = new CashClosure();
        $closure->setTotalIn($totalIn)->setTotalOut($totalOut)->setBalance($balance);

        $form = $this->createForm(CashClosureType::class, $closure);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $mv = new CashMovement();
            $mv->setType(CashMovement::OUT)
               ->setAmount(0)
               ->setSource(CashMovement::SRC_CLOSURE)
               ->setNotes('Clôture de caisse du ' . $closure->getClosedAt()->format('d/m/Y'));

            $em->persist($closure);
            $em->persist($mv);
            $em->flush();

            $this->addFlash('success', 'Clôture enregistrée.');
            return $this->redirectToRoute('cash_closures');
        }

        return $this->render('cash/close.html.twig', [
            'form'     => $form,
            'from'     => $from,
            'to'       => $to,
            'totalIn'  => $totalIn,
            'totalOut' => $totalOut,
            'balance'  => $balance,
        ]);
    }

    #[Route('/closures', name: 'closures', methods: ['GET'])]
    public function closures(CashClosureRepository $repo): Response
    {
        $items = $repo->findBy([], ['closedAt' => 'DESC']);
        return $this->render('cash/closures.html.twig', ['items' => $items]);
    }

    #[Route('/closure/{id}', name: 'closure_show', methods: ['GET'])]
    public function closureShow(CashClosure $closure): Response
    {
        return $this->render('cash/closure_show.html.twig', ['closure' => $closure]);
    }

    #[Route('/expenses/month', name: 'expenses_month', methods: ['GET'])]
    public function expensesMonth(
        Request $request,
        CashMovementRepository $cashRepo,
        PaginatorInterface $paginator
    ): Response {
        $tz  = new \DateTimeZone(\date_default_timezone_get());
        $now = new \DateTimeImmutable('now', $tz);

        $m = (int) $request->query->get('m', (int) $now->format('n'));
        $y = (int) $request->query->get('y', (int) $now->format('Y'));
        if ($m < 1 || $m > 12) { $m = (int) $now->format('n'); }
        if ($y < 2000 || $y > 2100) { $y = (int) $now->format('Y'); }

        $from = (new \DateTimeImmutable(sprintf('%04d-%02d-01', $y, $m), $tz))->setTime(0, 0, 0);
        $to   = $from->modify('last day of this month')->setTime(23, 59, 59);

        $qb = $cashRepo->createQueryBuilder('c')
            ->andWhere('c.type = :out')
            ->andWhere('c.createdAt BETWEEN :from AND :to')
            ->setParameter('out', CashMovement::OUT)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->orderBy('c.createdAt', 'ASC');

        // Pagination de la liste
        $pagination = $paginator->paginate(
            $qb,
            $request->query->getInt('page', 1),
            10
        );

        // Pour le graphe : on récupère tous les résultats (sans pagination) pour agréger
        $expensesAll = (clone $qb)->getQuery()->getResult();

        $daysInMonth = (int) $from->format('t');
        $daily = array_fill(1, $daysInMonth, 0);
        foreach ($expensesAll as $cm) {
            $d = (int) $cm->getCreatedAt()->setTimezone($tz)->format('j');
            $daily[$d] += (int) $cm->getAmount();
        }
        $labels = [];
        $values = [];
        for ($d = 1; $d <= $daysInMonth; $d++) {
            $labels[] = str_pad((string) $d, 2, '0', STR_PAD_LEFT);
            $values[] = $daily[$d];
        }
        $totalMonth = array_sum($values);

        $months = [
            1=>'Janvier',2=>'Février',3=>'Mars',4=>'Avril',5=>'Mai',6=>'Juin',
            7=>'Juillet',8=>'Août',9=>'Septembre',10=>'Octobre',11=>'Novembre',12=>'Décembre'
        ];
        $yearNow = (int) $now->format('Y');
        $years   = range($yearNow - 5, $yearNow + 1);

        return $this->render('cash/expenses_month.html.twig', [
            'from'       => $from,
            'to'         => $to,
            'labels'     => $labels,
            'values'     => $values,
            'total'      => $totalMonth,
            'pagination' => $pagination, 
            'm'          => $m,
            'y'          => $y,
            'months'     => $months,
            'years'      => $years,
        ]);
    }

    #[Route('/expenses/year', name: 'expenses_year', methods: ['GET'])]
    public function expensesYear(
        Request $request,
        CashMovementRepository $movRepo,
        PaginatorInterface $paginator
    ): Response {
        $tz   = new \DateTimeZone(\date_default_timezone_get());
        $year = (int) ($request->query->get('year') ?: (new \DateTimeImmutable('now', $tz))->format('Y'));

        $from = new \DateTimeImmutable("$year-01-01 00:00:00", $tz);
        $to   = new \DateTimeImmutable("$year-12-31 23:59:59", $tz);

        $qb = $movRepo->createQueryBuilder('m')
            ->andWhere('m.type = :t')
            ->andWhere('m.createdAt BETWEEN :from AND :to')
            ->setParameter('t', CashMovement::OUT)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->orderBy('m.createdAt', 'ASC');

        $pagination = $paginator->paginate($qb, $request->query->getInt('page', 1), 10);

        $allExpenses = (clone $qb)->getQuery()->getResult();

        $labels = ['Jan','Fév','Mar','Avr','Mai','Juin','Juil','Août','Sep','Oct','Nov','Déc'];
        $values = array_fill(0, 12, 0);

        foreach ($allExpenses as $e) {
            $dt = $e->getCreatedAt();
            if (!$dt) { continue; }
            $i = (int)$dt->format('n') - 1;
            $values[$i] += (int)$e->getAmount();
        }
        $total = array_sum($values);

        return $this->render('cash/expenses_year.html.twig', [
            'from'       => $from,
            'to'         => $to,
            'labels'     => $labels,
            'values'     => $values,
            'total'      => $total,
            'year'       => $year,
            'pagination' => $pagination,
        ]);
    }

    #[Route('/entries/month', name: 'entries_month', methods: ['GET'])]
    public function entriesMonth(
        Request $request,
        PaymentRepository $payRepo,
        PaginatorInterface $paginator
    ): Response {
        $tz  = new \DateTimeZone(\date_default_timezone_get());
        $now = new \DateTimeImmutable('now', $tz);

        $m = (int) $request->query->get('m', (int) $now->format('n'));
        $y = (int) $request->query->get('y', (int) $now->format('Y'));
        if ($m < 1 || $m > 12) { $m = (int) $now->format('n'); }
        if ($y < 2000 || $y > 2100) { $y = (int) $now->format('Y'); }

        $from = (new \DateTimeImmutable(sprintf('%04d-%02d-01', $y, $m), $tz))->setTime(0, 0, 0);
        $to   = $from->modify('last day of this month')->setTime(23, 59, 59);

        $qb = $payRepo->createQueryBuilder('p')
            ->andWhere('p.paidAt BETWEEN :from AND :to')
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->leftJoin('p.rdv', 'r')->addSelect('r')
            ->leftJoin('r.client', 'c')->addSelect('c')
            ->leftJoin('r.prestation', 'pr')->addSelect('pr')
            ->orderBy('p.paidAt', 'ASC');

        // Pagination de la liste
        $pagination = $paginator->paginate($qb, $request->query->getInt('page', 1), 10);

        // Graph (agrégation complète)
        $paymentsAll = (clone $qb)->getQuery()->getResult();
        $daysInMonth = (int) $from->format('t');
        $daily = array_fill(1, $daysInMonth, 0);
        foreach ($paymentsAll as $p) {
            $pa = $p->getPaidAt();
            if ($pa) {
                $d = (int) $pa->setTimezone($tz)->format('j');
                $daily[$d] += (int) $p->getAmount();
            }
        }
        $labels = [];
        $values = [];
        for ($d = 1; $d <= $daysInMonth; $d++) {
            $labels[] = str_pad((string) $d, 2, '0', STR_PAD_LEFT);
            $values[] = $daily[$d];
        }
        $totalMonth = array_sum($values);

        $months = [
            1=>'Janvier',2=>'Février',3=>'Mars',4=>'Avril',5=>'Mai',6=>'Juin',
            7=>'Juillet',8=>'Août',9=>'Septembre',10=>'Octobre',11=>'Novembre',12=>'Décembre'
        ];
        $yearNow = (int) $now->format('Y');
        $years   = range($yearNow - 5, $yearNow + 1);

        return $this->render('cash/entries_month.html.twig', [
            'from'       => $from,
            'to'         => $to,
            'labels'     => $labels,
            'values'     => $values,
            'total'      => $totalMonth,
            'pagination' => $pagination,
            'm'          => $m,
            'y'          => $y,
            'months'     => $months,
            'years'      => $years,
        ]);
    }

    #[Route('/entries/year', name: 'entries_year', methods: ['GET'])]
    public function entriesYear(
        Request $request,
        PaymentRepository $payRepo,
        PaginatorInterface $paginator
    ): Response {
        $tz    = new \DateTimeZone(\date_default_timezone_get());
        $year  = (int) ($request->query->get('year') ?: (new \DateTimeImmutable('today', $tz))->format('Y'));

        $from  = new \DateTimeImmutable("$year-01-01 00:00:00", $tz);
        $to    = new \DateTimeImmutable("$year-12-31 23:59:59", $tz);

        $qb = $payRepo->createQueryBuilder('p')
            ->andWhere('p.paidAt BETWEEN :from AND :to')
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->leftJoin('p.rdv', 'r')->addSelect('r')
            ->leftJoin('r.client', 'c')->addSelect('c')
            ->leftJoin('r.prestation', 'pr')->addSelect('pr')
            ->orderBy('p.paidAt', 'ASC');

        // Pagination
        $pagination = $paginator->paginate($qb, $request->query->getInt('page', 1), 10);

        // Graph (agrégation complète)
        $paymentsAll = (clone $qb)->getQuery()->getResult();
        $monthly = array_fill(1, 12, 0);
        foreach ($paymentsAll as $p) {
            $pa = $p->getPaidAt();
            if ($pa) {
                $mo = (int)$pa->setTimezone($tz)->format('n'); // 1..12
                $monthly[$mo] += (int) $p->getAmount();
            }
        }
        $labels = ['Janv.','Févr.','Mars','Avr.','Mai','Juin','Juil.','Août','Sept.','Oct.','Nov.','Déc.'];
        $values = array_values($monthly);
        $totalYear = array_sum($values);

        return $this->render('cash/entries_year.html.twig', [
            'from'       => $from,
            'to'         => $to,
            'labels'     => $labels,
            'values'     => $values,
            'total'      => $totalYear,
            'pagination' => $pagination, 
            'year'       => $year,
        ]);
    }
}

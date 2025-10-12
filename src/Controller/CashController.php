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
        CashMovementRepository $movRepo,
        PaginatorInterface $paginator
    ): Response {
        $tz  = new \DateTimeZone(\date_default_timezone_get());
        $day = $request->query->get('d')
            ? new \DateTimeImmutable($request->query->get('d'), $tz)
            : new \DateTimeImmutable('today', $tz);

        $start = $day->setTime(0, 0, 0);
        $end   = $day->setTime(23, 59, 59);

        // --- ENTRÉES (PAIEMENTS) ---
        $paymentsQb = $movRepo->createQueryBuilder('m')
            ->andWhere('m.type = :in')
            ->andWhere('m.source = :srcPay')
            ->andWhere('m.createdAt BETWEEN :from AND :to')
            ->setParameter('in', CashMovement::IN)
            ->setParameter('srcPay', CashMovement::SRC_PAYMENT)
            ->setParameter('from', $start)
            ->setParameter('to', $end)
            ->orderBy('m.createdAt', 'ASC');

        // --- SORTIES (DÉPENSES) ---
        $expensesQb = $movRepo->createQueryBuilder('m')
            ->andWhere('m.type = :out')
            ->andWhere('m.createdAt BETWEEN :from AND :to')
            ->setParameter('out', CashMovement::OUT)
            ->setParameter('from', $start)
            ->setParameter('to', $end)
            ->orderBy('m.createdAt', 'ASC');

        // --- ENTRÉES (INJECTIONS) ---

        $injectionsQb = $movRepo->createQueryBuilder('m')
            ->andWhere('m.type = :in')
            ->andWhere('m.source != :srcPayment')
            ->andWhere('m.createdAt BETWEEN :from AND :to')
            ->setParameter('in', CashMovement::IN)
            ->setParameter('srcPayment', CashMovement::SRC_PAYMENT)
            ->setParameter('from', $start)
            ->setParameter('to', $end)
            ->orderBy('m.createdAt', 'ASC');

        // Si tu utilises le soft delete, décommente :
        // foreach ([$paymentsQb, $expensesQb, $injectionsQb] as $qb) {
        //     $qb->andWhere('m.deletedAt IS NULL');
        // }

        // --- PAGINATION (10 lignes par tableau) ---
        $paymentsPage   = $paginator->paginate(
            $paymentsQb,
            $request->query->getInt('page_pay', 1),
            10,
            ['pageParameterName' => 'page_pay']
        );
        $expensesPage   = $paginator->paginate(
            $expensesQb,
            $request->query->getInt('page_out', 1),
            10,
            ['pageParameterName' => 'page_out']
        );
        $injectionsPage = $paginator->paginate(
            $injectionsQb,
            $request->query->getInt('page_inj', 1),
            10,
            ['pageParameterName' => 'page_inj']
        );

        // --- Totaux du jour (sur les pages courantes) ---
        $sumAmounts = static fn($iter) => array_sum(array_map(fn($m) => $m->getAmount(), iterator_to_array($iter)));
        $totalInPayments = $sumAmounts($paymentsPage);
        $totalOutExpenses = $sumAmounts($expensesPage);
        $totalInInjections = $sumAmounts($injectionsPage);

        $balance = ($totalInPayments + $totalInInjections) - $totalOutExpenses;
        $totalCaisse = $movRepo->currentCash();

        // Solde général (adapte selon tes méthodes de repo)
        // Idées: sumAllIn() - sumAllOut() ou repo->sumByType(...)
        $currentCash = method_exists($movRepo, 'sumCurrentCash')
            ? $movRepo->sumCurrentCash()
            : (
                (method_exists($movRepo, 'sumAllIn') ? $movRepo->sumAllIn() : 0)
                -
                (method_exists($movRepo, 'sumAllOut') ? $movRepo->sumAllOut() : 0)
            );

        $month = (int) $day->format('n');
        $year  = (int) $day->format('Y');

        return $this->render('cash/index.html.twig', [
            'day'             => $day,
            'month'           => $month,
            'year'            => $year,
            'paymentsPage'    => $paymentsPage,
            'expensesPage'    => $expensesPage,
            'injectionsPage'  => $injectionsPage,
            'totalInPayments' => $totalInPayments,
            'totalOutExpenses'=> $totalOutExpenses,
            'totalInInjections'=> $totalInInjections,
            'balance'         => $balance,
            'currentCash'     => $currentCash,
            'totalCaisse'     => $totalCaisse,
        ]);
    }

    #[Route('/expense/new', name: 'expense_new', methods: ['GET', 'POST'])]
    public function newExpense(
        Request $request,
        EntityManagerInterface $em,
        CashMovementRepository $mvRepo
    ): Response {
        $tz = new \DateTimeZone(\date_default_timezone_get());
        $availableToday = $mvRepo->currentCash();

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
    EntityManagerInterface $em,
    PaginatorInterface $paginator
    ): Response {
        $tz  = new \DateTimeZone(date_default_timezone_get());
        $now = new \DateTimeImmutable('now', $tz);

        $m = (int) $request->query->get('m', (int) $now->format('n'));
        $y = (int) $request->query->get('y', (int) $now->format('Y'));
        $categoryId = $request->query->get('category');

        $from = (new \DateTimeImmutable(sprintf('%04d-%02d-01', $y, $m), $tz))->setTime(0, 0, 0);
        $to   = $from->modify('last day of this month')->setTime(23, 59, 59);

        $qb = $cashRepo->createQueryBuilder('c')
            ->leftJoin('c.category', 'cat')->addSelect('cat')
            ->andWhere('c.type = :out')
            ->andWhere('c.createdAt BETWEEN :from AND :to')
            ->setParameter('out', \App\Entity\CashMovement::OUT)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->orderBy('c.createdAt', 'ASC');

        if ($categoryId) {
            $qb->andWhere('cat.id = :catId')->setParameter('catId', $categoryId);
        }

        $pagination = $paginator->paginate($qb, $request->query->getInt('page', 1), 10);

        // 🔹 Graphique linéaire journalier
        $daysInMonth = (int) $from->format('t');
        $daily = array_fill(1, $daysInMonth, 0);
        foreach ((clone $qb)->getQuery()->getResult() as $exp) {
            $d = (int) $exp->getCreatedAt()->setTimezone($tz)->format('j');
            $daily[$d] += (int) $exp->getAmount();
        }
        $labels = range(1, $daysInMonth);
        $values = array_values($daily);
        $totalMonth = array_sum($values);

        // 🔹 Catégories pour filtre + graphique camembert
        $allCategories = $cashRepo->getEntityManager()
            ->createQuery('SELECT c.id, c.name FROM App\Entity\ExpenseCategory c ORDER BY c.name ASC')
            ->getArrayResult();

        $categoryTotals = $cashRepo->sumByCategory($from, $to);

        // Préparer des tableaux simples (labels / values) pour Twig (et ApexCharts)
        $catLabels = array_map(fn($r) => $r['name'] ?? 'Non catégorisée', $categoryTotals);
        $catValues = array_map(fn($r) => (float) ($r['total'] ?? 0), $categoryTotals);

        

        $months = [
            1=>'Janvier',2=>'Février',3=>'Mars',4=>'Avril',5=>'Mai',6=>'Juin',
            7=>'Juillet',8=>'Août',9=>'Septembre',10=>'Octobre',11=>'Novembre',12=>'Décembre'
        ];
        $yearNow = (int) $now->format('Y');
        $years = range($yearNow - 5, $yearNow + 1);
//dump($categoryTotals);
        return $this->render('cash/expenses_month.html.twig', [
            'from' => $from,
            'to' => $to,
            'pagination' => $pagination,
            'm' => $m,
            'y' => $y,
            'months' => $months,
            'years' => $years,
            'allCategories' => $allCategories,
            'selectedCategory' => $categoryId,
            'labels' => $labels,
            'values' => $values,
            'total' => $totalMonth,
            'categoryTotals' => $categoryTotals,
            'catLabels'      => $catLabels,
            'catValues'      => $catValues,
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

        $currentYear = (int) (new \DateTimeImmutable('now', $tz))->format('Y');
        $years = range($currentYear, $currentYear - 5);

        $qb = $movRepo->createQueryBuilder('m')
            ->andWhere('m.type = :t')
            ->andWhere('m.createdAt BETWEEN :from AND :to')
            ->setParameter('t', CashMovement::OUT)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->orderBy('m.createdAt', 'ASC');

        $pagination = $paginator->paginate($qb, $request->query->getInt('page', 1), 10);

        $allExpenses = (clone $qb)->getQuery()->getResult();
        $categoryTotals = $movRepo->sumByCategory($from, $to);

        $labels = ['Jan','Fév','Mar','Avr','Mai','Juin','Juil','Août','Sep','Oct','Nov','Déc'];
        $values = array_fill(0, 12, 0);
        $yearCatLabels = array_map(fn($r) => $r['name'] ?? 'Non catégorisée', $categoryTotals);
        $yearCatValues = array_map(fn($r) => (float) ($r['total'] ?? 0), $categoryTotals);

        foreach ($allExpenses as $e) {
            $dt = $e->getCreatedAt();
            if (!$dt) { continue; }
            $i = (int)$dt->format('n') - 1;
            $values[$i] += (int)$e->getAmount();
        }
        $total = array_sum($values);

        return $this->render('cash/expenses_year.html.twig', [
            'from'            => $from,
            'to'              => $to,
            'labels'          => $labels,
            'values'          => $values,
            'total'           => $total,
            'year'            => $year,   // <- déjà présent
            'y'               => $year,   // <- alias pour le Twig existant
            'years'           => $years,  // <- pour le <select> année
            'pagination'      => $pagination,
            'categoryTotals'  => $categoryTotals,
            'yearCatLabels'   => $yearCatLabels,
            'yearCatValues'   => $yearCatValues,
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

    #[Route('/cash/expense/{id}', name: 'expense_show', methods: ['GET'])]
    public function expenseShow(CashMovement $expense): Response
    {
        return $this->render('cash/expense_show.html.twig', [
            'expense' => $expense,
        ]);
    }

    #[Route('/cash/expense/{id}/edit', name: 'expense_edit', methods: ['GET','POST'])]
    public function expenseEdit(Request $request, CashMovement $expense, EntityManagerInterface $em, CashMovementRepository $mvRepo): Response
    {
        // (Optionnel) Recontrôle du plafond journalier si tu veux
        $form = $this->createForm(CashExpenseType::class, $expense);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'Dépense mise à jour.');
            return $this->redirectToRoute('cash_index');
        }

        return $this->render('cash/expense_edit.html.twig', [
            'form'    => $form,
            'expense' => $expense,
        ]);
    }

    #[Route('/cash/expense/{id}/delete', name: 'expense_delete', methods: ['POST'])]
    public function expenseDelete(Request $request, CashMovement $expense, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete_expense_'.$expense->getId(), $request->request->get('_token'))) {
            $em->remove($expense);
            $em->flush();
            $this->addFlash('success', 'Dépense supprimée.');
        }
        return $this->redirectToRoute('cash_index');
    }

     #[Route('/injections/month', name: 'injections_month', methods: ['GET'])]
    public function injectionsMonth(
        Request $request,
        CashMovementRepository $movRepo,
        PaginatorInterface $paginator
    ): Response {
        $tz = new \DateTimeZone(\date_default_timezone_get());

        $y = (int) $request->query->get('y', (int) (new \DateTimeImmutable('now', $tz))->format('Y'));
        $m = (int) $request->query->get('m', (int) (new \DateTimeImmutable('now', $tz))->format('n'));

        $from = new \DateTimeImmutable(sprintf('%04d-%02d-01 00:00:00', $y, $m), $tz);
        $to   = $from->modify('last day of this month')->setTime(23,59,59);

        // Injection = IN et source ≠ PAYMENT
        $qb = $movRepo->createQueryBuilder('m')
            ->andWhere('m.type = :in')
            ->andWhere('m.source != :srcPayment')
            ->andWhere('m.createdAt BETWEEN :from AND :to')
            ->setParameter('in', CashMovement::IN)
            ->setParameter('srcPayment', CashMovement::SRC_PAYMENT)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->orderBy('m.createdAt', 'ASC');

        $pagination = $paginator->paginate($qb, $request->query->getInt('page', 1), 10);

        // Totaux par jour pour petite courbe (optionnel)
        $labels = [];
        $values = [];
        $daysInMonth = (int) $to->format('j');
        for ($i=1; $i <= $daysInMonth; $i++) {
            $labels[] = sprintf('%02d', $i);
            $values[] = 0;
        }
        foreach ($pagination as $inj) {
            $d = (int) $inj->getCreatedAt()->format('j'); // 1..31
            $values[$d-1] += (int) $inj->getAmount();
        }
        $total = array_sum($values);

        // données pour filtre
        $months = [1=>'Jan','Fév','Mar','Avr','Mai','Juin','Juil','Août','Sep','Oct','Nov','Déc'];
        $years  = range((int) date('Y'), (int) date('Y')-5);

        return $this->render('cash/injections_month.html.twig', [
            'm' => $m, 'y' => $y,
            'from' => $from, 'to' => $to,
            'months' => $months, 'years' => $years,
            'labels' => $labels, 'values' => $values, 'total' => $total,
            'pagination' => $pagination,
        ]);
    }

    #[Route('/injections/year', name: 'injections_year', methods: ['GET'])]
    public function injectionsYear(
        Request $request,
        CashMovementRepository $movRepo,
        PaginatorInterface $paginator
    ): Response {
        $tz   = new \DateTimeZone(\date_default_timezone_get());
        $year = (int) $request->query->get('year', (int) (new \DateTimeImmutable('now', $tz))->format('Y'));

        $from = new \DateTimeImmutable("$year-01-01 00:00:00", $tz);
        $to   = new \DateTimeImmutable("$year-12-31 23:59:59", $tz);

        $qb = $movRepo->createQueryBuilder('m')
            ->andWhere('m.type = :in')
            ->andWhere('m.source != :srcPayment')
            ->andWhere('m.createdAt BETWEEN :from AND :to')
            ->setParameter('in', CashMovement::IN)
            ->setParameter('srcPayment', CashMovement::SRC_PAYMENT)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->orderBy('m.createdAt', 'ASC');

        $pagination = $paginator->paginate($qb, $request->query->getInt('page', 1), 10);

        // Totaux par mois
        $labels = ['Jan','Fév','Mar','Avr','Mai','Juin','Juil','Août','Sep','Oct','Nov','Déc'];
        $values = array_fill(0, 12, 0);
        foreach ($pagination as $inj) {
            $n = (int) $inj->getCreatedAt()->format('n'); // 1..12
            $values[$n-1] += (int) $inj->getAmount();
        }
        $total = array_sum($values);
        $years = range((int) date('Y'), (int) date('Y')-5);

        return $this->render('cash/injections_year.html.twig', [
            'year' => $year, 'y' => $year,
            'from' => $from, 'to' => $to,
            'labels' => $labels, 'values' => $values, 'total' => $total,
            'years' => $years,
            'pagination' => $pagination,
        ]);
    }
}

<?php

namespace App\Controller;

use App\Entity\CashClosure;
use App\Entity\CashMovement;
use App\Entity\Payment;
use App\Entity\User;
use App\Form\CashClosureType;
use App\Form\CashExpenseType;
use App\Repository\CashMovementRepository;
use App\Repository\CashClosureRepository;
use App\Repository\PaymentRepository;
use App\Service\CashMovementCancellationService;
use App\Service\CashPeriodGuard;
use App\Service\CashRegisterService;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface; 
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/cash', name: 'cash_')]
#[IsGranted('ROLE_RECEPTION_CAISSE')]
class CashController extends AbstractController
{
    #[Route('/', name: 'index', methods: ['GET'])]
    public function index(
        Request $request,
        CashMovementRepository $movRepo,
        CashClosureRepository $closureRepo,
        CashPeriodGuard $cashPeriodGuard,
        PaymentRepository $paymentRepo,
        PaginatorInterface $paginator
    ): Response {
        $tz  = new \DateTimeZone(\date_default_timezone_get());
        $today = new \DateTimeImmutable('today', $tz);
        $day = $request->query->get('d')
            ? new \DateTimeImmutable($request->query->get('d'), $tz)
            : $today;

        $start = $day->setTime(0, 0, 0);
        $end   = $day->setTime(23, 59, 59);

        $filter = $request->query->getString('type');
        $allowedFilters = ['cash', 'mobile', 'injection', 'expense', 'refund', 'cancelled'];
        if (!in_array($filter, $allowedFilters, true)) {
            $filter = '';
        }

        // Journal opérationnel unifié. Les paiements viennent de Payment (espèces + mobile)
        // et les autres opérations de CashMovement. Les mouvements PAYMENT ne sont donc
        // jamais ajoutés une seconde fois.
        $journalItems = [];
        if ($filter !== 'cancelled') {
            if (in_array($filter, ['', 'cash', 'mobile'], true)) {
                $paymentQb = $paymentRepo->createQueryBuilder('p')
                    ->leftJoin('p.rdv', 'rdv')->addSelect('rdv')
                    ->leftJoin('rdv.client', 'client')->addSelect('client')
                    ->leftJoin('rdv.prestation', 'prestation')->addSelect('prestation')
                    ->leftJoin('p.receivedBy', 'receiver')->addSelect('receiver')
                    ->andWhere('p.paidAt BETWEEN :from AND :to')
                    ->andWhere('p.cancelledAt IS NULL')
                    ->setParameter('from', $start)
                    ->setParameter('to', $end);
                if ($filter === 'cash') {
                    $paymentQb->andWhere('p.methode = :method')->setParameter('method', Payment::M_ESPECES);
                } elseif ($filter === 'mobile') {
                    $paymentQb->andWhere('p.methode = :method')->setParameter('method', Payment::M_MOBILE);
                }
                foreach ($paymentQb->getQuery()->getResult() as $payment) {
                    \assert($payment instanceof Payment);
                    $isMobile = $payment->getMethode() === Payment::M_MOBILE;
                    $journalItems[] = [
                        'date' => $payment->getPaidAt(),
                        'nature' => $isMobile ? 'Paiement mobile' : 'Paiement espèces',
                        'kind' => $isMobile ? 'mobile' : 'cash',
                        'detail' => trim(sprintf(
                            '%s%s',
                            $payment->getRdv()?->getClient()?->getNometprenom() ?? '',
                            $payment->getRdv()?->getPrestation()
                                ? ' · '.$payment->getRdv()->getPrestation()->getLibelle()
                                : '',
                        )) ?: ($payment->getReceiptNumber() ?: 'Paiement'),
                        'reference' => $payment->getReceiptNumber(),
                        'user' => $payment->getReceivedBy()?->getFullname(),
                        'amount' => (int) $payment->getAmount(),
                        'cashImpact' => $isMobile ? 0 : (int) $payment->getAmount(),
                        'commercialImpact' => (int) $payment->getAmount(),
                        'cancelled' => false,
                    ];
                }
            }

            if (in_array($filter, ['', 'injection', 'expense', 'refund'], true)) {
                $movementQb = $movRepo->createQueryBuilder('m')
                    ->leftJoin('m.category', 'category')->addSelect('category')
                    ->leftJoin('m.createdBy', 'author')->addSelect('author')
                    ->andWhere('m.createdAt BETWEEN :from AND :to')
                    ->andWhere('m.deletedAt IS NULL')
                    ->andWhere('m.source != :paymentSource')
                    ->setParameter('from', $start)
                    ->setParameter('to', $end)
                    ->setParameter('paymentSource', CashMovement::SRC_PAYMENT);
                match ($filter) {
                    'injection' => $movementQb->andWhere('m.type = :in')
                        ->setParameter('in', CashMovement::IN),
                    'expense' => $movementQb->andWhere('m.source = :source')
                        ->setParameter('source', CashMovement::SRC_EXPENSE),
                    'refund' => $movementQb->andWhere('m.source = :source')
                        ->setParameter('source', CashMovement::SRC_PAYMENT_REVERSAL),
                    default => null,
                };
                foreach ($movementQb->getQuery()->getResult() as $movement) {
                    \assert($movement instanceof CashMovement);
                    $isInjection = $movement->getType() === CashMovement::IN;
                    $isRefund = $movement->getSource() === CashMovement::SRC_PAYMENT_REVERSAL;
                    $journalItems[] = [
                        'date' => $movement->getCreatedAt(),
                        'nature' => $isInjection ? 'Apport de fonds' : ($isRefund ? 'Remboursement espèces' : 'Dépense'),
                        'kind' => $isInjection ? 'injection' : ($isRefund ? 'refund' : 'expense'),
                        'detail' => $movement->getNotes() ?: 'Aucun détail',
                        'reference' => $movement->getCategory()?->getName(),
                        'user' => $movement->getCreatedBy()?->getFullname(),
                        'amount' => $movement->getAmount(),
                        'cashImpact' => $isInjection ? $movement->getAmount() : -$movement->getAmount(),
                        'commercialImpact' => 0,
                        'cancelled' => false,
                    ];
                }
            }
        } elseif ($this->isGranted('ROLE_MANAGER')) {
            $cancelledPayments = $paymentRepo->createQueryBuilder('p')
                ->leftJoin('p.cancelledBy', 'author')->addSelect('author')
                ->andWhere('p.cancelledAt BETWEEN :from AND :to')
                ->setParameter('from', $start)
                ->setParameter('to', $end)
                ->getQuery()
                ->getResult();
            foreach ($cancelledPayments as $payment) {
                \assert($payment instanceof Payment);
                $journalItems[] = [
                    'date' => $payment->getCancelledAt(),
                    'nature' => $payment->getMethode() === Payment::M_MOBILE ? 'Paiement mobile annulé' : 'Paiement espèces annulé',
                    'kind' => 'cancelled',
                    'detail' => $payment->getCancellationReason() ?: 'Paiement annulé',
                    'reference' => $payment->getReceiptNumber(),
                    'user' => $payment->getCancelledBy()?->getFullname(),
                    'amount' => (int) $payment->getAmount(),
                    'cashImpact' => 0,
                    'commercialImpact' => 0,
                    'cancelled' => true,
                ];
            }
            $cancelledMovements = $movRepo->createQueryBuilder('m')
                ->leftJoin('m.deletedBy', 'author')->addSelect('author')
                ->andWhere('m.deletedAt BETWEEN :from AND :to')
                ->setParameter('from', $start)
                ->setParameter('to', $end)
                ->getQuery()
                ->getResult();
            foreach ($cancelledMovements as $movement) {
                \assert($movement instanceof CashMovement);
                $journalItems[] = [
                    'date' => $movement->getDeletedAt(),
                    'nature' => $movement->getType() === CashMovement::IN ? 'Apport annulé' : 'Dépense annulée',
                    'kind' => 'cancelled',
                    'detail' => $movement->getDeletionReason() ?: 'Écriture annulée',
                    'reference' => null,
                    'user' => $movement->getDeletedBy()?->getFullname(),
                    'amount' => $movement->getAmount(),
                    'cashImpact' => 0,
                    'commercialImpact' => 0,
                    'cancelled' => true,
                ];
            }
        }
        usort($journalItems, static fn (array $a, array $b): int => $b['date'] <=> $a['date']);
        $journal = $paginator->paginate(
            $journalItems,
            $request->query->getInt('page', 1),
            20,
        );

        // Les totaux sont calculés sur toute la journée, indépendamment de la pagination.
        $totalInPayments = $movRepo->sumBetweenByTypeAndSource(
            $start,
            $end,
            CashMovement::IN,
            CashMovement::SRC_PAYMENT,
        );
        $totalOutExpenses = $movRepo->sumBetweenByTypeAndSource(
            $start,
            $end,
            CashMovement::OUT,
            CashMovement::SRC_EXPENSE,
        );
        $totalOutRefunds = $movRepo->sumBetweenByTypeAndSource(
            $start,
            $end,
            CashMovement::OUT,
            CashMovement::SRC_PAYMENT_REVERSAL,
        );
        $totalInInjections = $movRepo->sumBetweenByTypeExcludingSource(
            $start,
            $end,
            CashMovement::IN,
            CashMovement::SRC_PAYMENT,
        );

        $balance = ($totalInPayments + $totalInInjections) - $totalOutExpenses - $totalOutRefunds;
        $netCashMovements = $totalInPayments - $totalOutRefunds;
        $paymentsByMethod = $paymentRepo->sumByMethodBetween($start, $end);
        $cashValidPayments = $paymentsByMethod[Payment::M_ESPECES] ?? 0;
        $mobilePayments = $paymentsByMethod[Payment::M_MOBILE] ?? 0;
        $commercialReceipts = $cashValidPayments + $mobilePayments;
        $month = (int) $day->format('n');
        $year  = (int) $day->format('Y');

        return $this->render('cash/index_v2.html.twig', [
            'day'             => $day,
            'month'           => $month,
            'year'            => $year,
            'journal'         => $journal,
            'filter'          => $filter,
            'totalInPayments' => $totalInPayments,
            'totalOutExpenses'=> $totalOutExpenses,
            'totalInInjections'=> $totalInInjections,
            'totalOutRefunds' => $totalOutRefunds,
            'balance'         => $balance,
            'netCashMovements'=> $netCashMovements,
            'mobilePayments'  => $mobilePayments,
            'cashValidPayments' => $cashValidPayments,
            'commercialReceipts' => $commercialReceipts,
            'todayClosure'    => $closureRepo->findForDate($day),
            'isToday'         => $day->format('Y-m-d') === $today->format('Y-m-d'),
            'pendingCashClosure' => $cashPeriodGuard->findOldestPendingDate($today),
        ]);
    }

    #[Route('/expense/new', name: 'expense_new', methods: ['GET', 'POST'])]
    public function newExpense(
        Request $request,
        EntityManagerInterface $em,
        CashMovementRepository $mvRepo,
        CashPeriodGuard $periodGuard,
    ): Response {
        $tz = new \DateTimeZone(\date_default_timezone_get());
        $availableToday = $mvRepo->currentCash();

        $mv = new CashMovement();
        $mv->setType(CashMovement::OUT)
           ->setSource(CashMovement::SRC_EXPENSE);

        $form = $this->createForm(CashExpenseType::class, $mv);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $periodGuard->assertOpen(new \DateTimeImmutable('now', $tz));
            } catch (\DomainException $exception) {
                $this->addFlash('danger', $exception->getMessage());
                return $this->redirectToRoute('cash_index');
            }
            if ($mv->getAmount() <= 0) {
                $this->addFlash('danger', 'Le montant doit être supérieur à 0.');
            } elseif ($mv->getAmount() > $availableToday) {
                $this->addFlash('danger', 'Impossible : la dépense dépasse le solde disponible de la journée ('
                    . number_format($availableToday, 0, ',', ' ') . ' MRU).');
            } else {
                $author = $this->getUser();
                if (!$author instanceof User) {
                    throw $this->createAccessDeniedException();
                }
                $mv->setCreatedBy($author);
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
    #[IsGranted('ROLE_RECEPTION_CAISSE')]
    public function closeDay(
        Request $request,
        CashMovementRepository $mvRepo,
        CashClosureRepository $closureRepo,
        CashRegisterService $cashRegister,
        CashPeriodGuard $cashPeriodGuard,
    ): Response {
        $tz   = new \DateTimeZone(\date_default_timezone_get());
        $requestedDate = $request->query->getString('date');
        $today = new \DateTimeImmutable('today', $tz);
        try {
            $from = $requestedDate !== ''
                ? (new \DateTimeImmutable($requestedDate, $tz))->setTime(0, 0)
                : ($cashPeriodGuard->findOldestPendingDate($today) ?? $today);
        } catch (\Throwable) {
            throw $this->createNotFoundException('Date de clôture invalide.');
        }
        if ($from > $today) {
            throw $this->createNotFoundException('Une journée future ne peut pas être clôturée.');
        }
        $to = $from->setTime(23, 59, 59);

        $totalIn  = $mvRepo->sumBetween($from, $to, CashMovement::IN);
        $totalOut = $mvRepo->sumBetween($from, $to, CashMovement::OUT);
        $balance  = $totalIn - $totalOut;
        $cashEntries = $mvRepo->sumBetweenByTypeAndSource(
            $from, $to, CashMovement::IN, CashMovement::SRC_PAYMENT,
        );
        $fundContributions = $mvRepo->sumBetweenByTypeExcludingSource(
            $from, $to, CashMovement::IN, CashMovement::SRC_PAYMENT,
        );
        $expenses = $mvRepo->sumBetweenByTypeAndSource(
            $from, $to, CashMovement::OUT, CashMovement::SRC_EXPENSE,
        );
        $cashRefunds = $mvRepo->sumBetweenByTypeAndSource(
            $from, $to, CashMovement::OUT, CashMovement::SRC_PAYMENT_REVERSAL,
        );

        $existingClosure = $closureRepo->findForDate($from);
        if ($existingClosure) {
            $this->addFlash('info', 'La caisse de cette journée est déjà clôturée.');
            return $this->redirectToRoute('cash_closure_show', ['id' => $existingClosure->getId()]);
        }

        $closure = new CashClosure();
        $closure->setTotalIn($totalIn)->setTotalOut($totalOut)->setBalance($balance);
        $closure->setCountedBalance(max(0, $balance));

        $form = $this->createForm(CashClosureType::class, $closure);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $user = $this->getUser();
            if (!$user instanceof User) {
                throw $this->createAccessDeniedException();
            }
            try {
                $savedClosure = $cashRegister->close(
                    $from,
                    $closure->getCountedBalance(),
                    $user,
                    $closure->getNotes(),
                    $closure->getLateReason(),
                );
                $this->addFlash('success', 'Clôture enregistrée.');
                return $this->redirectToRoute('cash_closure_show', ['id' => $savedClosure->getId()]);
            } catch (\DomainException $exception) {
                $this->addFlash('danger', $exception->getMessage());
            }
        }

        return $this->render('cash/close.html.twig', [
            'form'     => $form,
            'from'     => $from,
            'to'       => $to,
            'totalIn'  => $totalIn,
            'totalOut' => $totalOut,
            'balance'  => $balance,
            'cashEntries' => $cashEntries,
            'fundContributions' => $fundContributions,
            'expenses' => $expenses,
            'cashRefunds' => $cashRefunds,
            'isLate'   => $from < $today,
        ]);
    }

    #[Route('/closures', name: 'closures', methods: ['GET'])]
    #[IsGranted('ROLE_MANAGER')]
    public function closures(CashClosureRepository $repo): Response
    {
        $items = $repo->findBy([], ['closedAt' => 'DESC']);
        return $this->render('cash/closures.html.twig', ['items' => $items]);
    }

    #[Route('/closure/{id}', name: 'closure_show', methods: ['GET'])]
    #[IsGranted('ROLE_MANAGER')]
    public function closureShow(CashClosure $closure): Response
    {
        return $this->render('cash/closure_show.html.twig', ['closure' => $closure]);
    }

    #[Route('/expenses/month', name: 'expenses_month', methods: ['GET'])]
    #[IsGranted('ROLE_MANAGER')]
    public function expensesMonth(
    Request $request,
    CashMovementRepository $cashRepo,
    EntityManagerInterface $em,
    PaginatorInterface $paginator
    ): Response {
        return $this->redirectToRoute('cash_reports', [
            'period' => 'month',
            'nature' => 'expenses',
            'month' => $request->query->getInt('m', (int) date('n')),
            'year' => $request->query->getInt('y', (int) date('Y')),
        ]);

        /*
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
            ->andWhere('c.source = :srcExpense')
            ->andWhere('c.deletedAt IS NULL')
            ->andWhere('c.createdAt BETWEEN :from AND :to')
            ->setParameter('out', \App\Entity\CashMovement::OUT)
            ->setParameter('srcExpense', CashMovement::SRC_EXPENSE)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->orderBy('c.createdAt', 'DESC');

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
        ]);*/
    }

    #[Route('/expenses/year', name: 'expenses_year', methods: ['GET'])]
    #[IsGranted('ROLE_MANAGER')]
    public function expensesYear(
        Request $request,
        CashMovementRepository $movRepo,
        PaginatorInterface $paginator
    ): Response {
        return $this->redirectToRoute('cash_reports', [
            'period' => 'year',
            'nature' => 'expenses',
            'year' => $request->query->getInt('year', (int) date('Y')),
        ]);

        /*
        $tz   = new \DateTimeZone(\date_default_timezone_get());
        $year = (int) ($request->query->get('year') ?: (new \DateTimeImmutable('now', $tz))->format('Y'));

        $from = new \DateTimeImmutable("$year-01-01 00:00:00", $tz);
        $to   = new \DateTimeImmutable("$year-12-31 23:59:59", $tz);

        $currentYear = (int) (new \DateTimeImmutable('now', $tz))->format('Y');
        $years = range($currentYear, $currentYear - 5);

        $qb = $movRepo->createQueryBuilder('m')
            ->andWhere('m.type = :t')
            ->andWhere('m.source = :srcExpense')
            ->andWhere('m.deletedAt IS NULL')
            ->andWhere('m.createdAt BETWEEN :from AND :to')
            ->setParameter('t', CashMovement::OUT)
            ->setParameter('srcExpense', CashMovement::SRC_EXPENSE)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->orderBy('m.createdAt', 'DESC');

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
        ]);*/
    }

    #[Route('/entries/month', name: 'entries_month', methods: ['GET'])]
    #[IsGranted('ROLE_MANAGER')]
    public function entriesMonth(
        Request $request,
        PaymentRepository $payRepo,
        PaginatorInterface $paginator
    ): Response {
        return $this->redirectToRoute('cash_reports', [
            'period' => 'month',
            'nature' => 'entries',
            'month' => $request->query->getInt('m', (int) date('n')),
            'year' => $request->query->getInt('y', (int) date('Y')),
        ]);

        /*
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
            ->andWhere('p.cancelledAt IS NULL')
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->leftJoin('p.rdv', 'r')->addSelect('r')
            ->leftJoin('r.client', 'c')->addSelect('c')
            ->leftJoin('r.prestation', 'pr')->addSelect('pr')
            ->orderBy('p.paidAt', 'DESC');

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
        ]);*/
    }

    #[Route('/entries/year', name: 'entries_year', methods: ['GET'])]
    #[IsGranted('ROLE_MANAGER')]
    public function entriesYear(
        Request $request,
        PaymentRepository $payRepo,
        PaginatorInterface $paginator
    ): Response {
        return $this->redirectToRoute('cash_reports', [
            'period' => 'year',
            'nature' => 'entries',
            'year' => $request->query->getInt('year', (int) date('Y')),
        ]);

        /*
        $tz    = new \DateTimeZone(\date_default_timezone_get());
        $year  = (int) ($request->query->get('year') ?: (new \DateTimeImmutable('today', $tz))->format('Y'));

        $from  = new \DateTimeImmutable("$year-01-01 00:00:00", $tz);
        $to    = new \DateTimeImmutable("$year-12-31 23:59:59", $tz);

        $qb = $payRepo->createQueryBuilder('p')
            ->andWhere('p.paidAt BETWEEN :from AND :to')
            ->andWhere('p.cancelledAt IS NULL')
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->leftJoin('p.rdv', 'r')->addSelect('r')
            ->leftJoin('r.client', 'c')->addSelect('c')
            ->leftJoin('r.prestation', 'pr')->addSelect('pr')
            ->orderBy('p.paidAt', 'DESC');

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
        ]);*/
    }

    #[Route('/expense/{id}', name: 'expense_show', methods: ['GET'])]
    #[Route('/cash/expense/{id}', name: 'expense_show_legacy', methods: ['GET'])]
    public function expenseShow(CashMovement $expense): Response
    {
        return $this->render('cash/expense_show.html.twig', [
            'expense' => $expense,
        ]);
    }

    #[Route('/expense/{id}/cancel', name: 'expense_cancel', methods: ['POST'])]
    #[Route('/cash/expense/{id}/cancel', name: 'expense_cancel_legacy', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function expenseCancel(
        Request $request,
        CashMovement $expense,
        CashMovementCancellationService $cancellation,
    ): Response
    {
        if (!$this->isCsrfTokenValid(
            'cancel_expense_'.$expense->getId(),
            $request->request->getString('_token'),
        )) {
            throw $this->createAccessDeniedException('Jeton de sécurité invalide.');
        }
        if ($expense->getType() !== CashMovement::OUT || $expense->getSource() !== CashMovement::SRC_EXPENSE) {
            throw $this->createNotFoundException();
        }

        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        try {
            $cancellation->cancel($expense, $user, $request->request->getString('reason'));
            $this->addFlash('success', 'Dépense annulée. L’historique est conservé.');
        } catch (\DomainException $exception) {
            $this->addFlash('danger', $exception->getMessage());
        }
        return $this->redirectToRoute('cash_index');
    }

     #[Route('/injections/month', name: 'injections_month', methods: ['GET'])]
    #[IsGranted('ROLE_MANAGER')]
    public function injectionsMonth(
        Request $request,
        CashMovementRepository $movRepo,
        PaginatorInterface $paginator
    ): Response {
        return $this->redirectToRoute('cash_reports', [
            'period' => 'month',
            'nature' => 'injections',
            'month' => $request->query->getInt('m', (int) date('n')),
            'year' => $request->query->getInt('y', (int) date('Y')),
        ]);

        /*
        $tz = new \DateTimeZone(\date_default_timezone_get());

        $y = (int) $request->query->get('y', (int) (new \DateTimeImmutable('now', $tz))->format('Y'));
        $m = (int) $request->query->get('m', (int) (new \DateTimeImmutable('now', $tz))->format('n'));

        $from = new \DateTimeImmutable(sprintf('%04d-%02d-01 00:00:00', $y, $m), $tz);
        $to   = $from->modify('last day of this month')->setTime(23,59,59);

        // Injection = IN et source ≠ PAYMENT
        $qb = $movRepo->createQueryBuilder('m')
            ->andWhere('m.type = :in')
            ->andWhere('m.source != :srcPayment')
            ->andWhere('m.deletedAt IS NULL')
            ->andWhere('m.createdAt BETWEEN :from AND :to')
            ->setParameter('in', CashMovement::IN)
            ->setParameter('srcPayment', CashMovement::SRC_PAYMENT)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->orderBy('m.createdAt', 'DESC');

        // Le graphique doit porter sur toute la période, pas uniquement sur la page affichée.
        $allInjections = (clone $qb)->getQuery()->getResult();
        $pagination = $paginator->paginate($qb, $request->query->getInt('page', 1), 10);

        // Totaux par jour pour petite courbe (optionnel)
        $labels = [];
        $values = [];
        $daysInMonth = (int) $to->format('j');
        for ($i=1; $i <= $daysInMonth; $i++) {
            $labels[] = sprintf('%02d', $i);
            $values[] = 0;
        }
        foreach ($allInjections as $inj) {
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
        ]);*/
    }

    #[Route('/injections/year', name: 'injections_year', methods: ['GET'])]
    #[IsGranted('ROLE_MANAGER')]
    public function injectionsYear(
        Request $request,
        CashMovementRepository $movRepo,
        PaginatorInterface $paginator
    ): Response {
        return $this->redirectToRoute('cash_reports', [
            'period' => 'year',
            'nature' => 'injections',
            'year' => $request->query->getInt('year', (int) date('Y')),
        ]);

        /*
        $tz   = new \DateTimeZone(\date_default_timezone_get());
        $year = (int) $request->query->get('year', (int) (new \DateTimeImmutable('now', $tz))->format('Y'));

        $from = new \DateTimeImmutable("$year-01-01 00:00:00", $tz);
        $to   = new \DateTimeImmutable("$year-12-31 23:59:59", $tz);

        $qb = $movRepo->createQueryBuilder('m')
            ->andWhere('m.type = :in')
            ->andWhere('m.source != :srcPayment')
            ->andWhere('m.deletedAt IS NULL')
            ->andWhere('m.createdAt BETWEEN :from AND :to')
            ->setParameter('in', CashMovement::IN)
            ->setParameter('srcPayment', CashMovement::SRC_PAYMENT)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->orderBy('m.createdAt', 'DESC');

        // Le graphique doit porter sur toute l'année, pas uniquement sur la page affichée.
        $allInjections = (clone $qb)->getQuery()->getResult();
        $pagination = $paginator->paginate($qb, $request->query->getInt('page', 1), 10);

        // Totaux par mois
        $labels = ['Jan','Fév','Mar','Avr','Mai','Juin','Juil','Août','Sep','Oct','Nov','Déc'];
        $values = array_fill(0, 12, 0);
        foreach ($allInjections as $inj) {
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
        ]);*/
    }
}

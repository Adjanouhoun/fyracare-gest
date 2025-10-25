<?php
namespace App\Controller;

use App\Repository\RdvRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;

class AgendaController extends AbstractController
{
    #[Route('/admin/agenda', name:'agenda', methods: ['GET'])]
    public function index(Request $request, RdvRepository $repo): Response
    {
        $tzId = \date_default_timezone_get();
        $tz   = new \DateTimeZone($tzId);

        $now = new \DateTimeImmutable('now', $tz);

        // ---- Bornes Jour
        $dayStart = (new \DateTimeImmutable($now->format('Y-m-d').' 00:00:00', $tz));
        $dayEnd   = (new \DateTimeImmutable($now->format('Y-m-d').' 23:59:59', $tz));

        // ---- Bornes Semaine (lundi -> dimanche)
        $weekStart = (new \DateTimeImmutable('monday this week', $tz))->setTime(0,0,0);
        $weekEnd   = (new \DateTimeImmutable('sunday this week', $tz))->setTime(23,59,59);

        // ---- Bornes Mois
        $monthStart = (new \DateTimeImmutable('first day of this month', $tz))->setTime(0,0,0);
        $monthEnd   = (new \DateTimeImmutable('last day of this month',  $tz))->setTime(23,59,59);

        // ---- Compteurs
        $countDay   = $this->countBetween($repo, $dayStart,   $dayEnd);
        $countWeek  = $this->countBetween($repo, $weekStart,  $weekEnd);
        $countMonth = $this->countBetween($repo, $monthStart, $monthEnd);

        // KPI globaux
        $total = $repo->count([]);

        // Vue initiale du calendrier (query ?view=day|week|month)
        $viewParam = $request->query->get('view', 'week');
        $initialView = match ($viewParam) {
            'day'   => 'timeGridDay',
            'month' => 'dayGridMonth',
            default => 'timeGridWeek',
        };

        return $this->render('agenda/index.html.twig', [
            'total'       => $total,
            'countDay'    => $countDay,
            'countWeek'   => $countWeek,
            'countMonth'  => $countMonth,
            'initialView' => $initialView,
        ]);
    }

    #[Route('/admin/agenda/test', name:'agenda_test', methods: ['GET'])]
    public function test(): Response
    {
        return $this->render('agenda/test.html.twig');
    }

    private function countBetween(RdvRepository $repo, \DateTimeImmutable $start, \DateTimeImmutable $end): int
    {
        return (int) $repo->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->andWhere('r.startAt BETWEEN :a AND :b')
            ->setParameter('a', $start)
            ->setParameter('b', $end)
            ->getQuery()->getSingleScalarResult();
    }
}

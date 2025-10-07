<?php
namespace App\Controller;

use App\Entity\Rdv;
use App\Repository\RdvRepository;
use App\Repository\PaymentRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

final class DashboardController extends AbstractController
{
    #[Route('/dashboard', name: 'admin_dashboard', methods: ['GET'])]
    public function index(RdvRepository $rdvRepo, PaymentRepository $payRepo): Response
    {
        $tz = new \DateTimeZone(\date_default_timezone_get());

        // Aujourd'hui
        $todayStart = (new \DateTimeImmutable('today', $tz))->setTime(0,0,0);
        $todayEnd   = (new \DateTimeImmutable('today', $tz))->setTime(23,59,59);

        // KPIs du jour
        $kpis = [
            'rdv'       => $rdvRepo->countBetween($todayStart, $todayEnd),
            'honores'   => $rdvRepo->countByStatusBetween(\App\Entity\Rdv::S_HONORE,   $todayStart, $todayEnd),
            'annules'   => $rdvRepo->countByStatusBetween(\App\Entity\Rdv::S_ANNULE,   $todayStart, $todayEnd),
            'confirmes' => $rdvRepo->countByStatusBetween(\App\Entity\Rdv::S_CONFIRME, $todayStart, $todayEnd),
            'absents'   => $rdvRepo->countByStatusBetween(\App\Entity\Rdv::S_ABSENT,   $todayStart, $todayEnd),
            'caToday'   => $payRepo->sumRevenueBetween($todayStart, $todayEnd),
        ];

        // 30 jours glissants (CA)
        $from30 = $todayStart->modify('-29 days');
        $map30  = $payRepo->revenueByDayBetween($from30, $todayEnd);
        $labels30 = [];
        $values30 = [];
        for ($i=0; $i<30; $i++) {
            $d = $from30->modify("+$i days")->format('Y-m-d');
            $labels30[] = $d;
            $values30[] = $map30[$d] ?? 0;
        }

        // Semaine en cours (lundi → dimanche)
        $dow = (int) $todayStart->format('N'); // 1 = lundi
        $weekStart = $todayStart->modify('-'.($dow-1).' days');
        $weekEnd   = $weekStart->modify('+6 days')->setTime(23,59,59);

        $statuses = [
            \App\Entity\Rdv::S_PLANIFIE,
            \App\Entity\Rdv::S_CONFIRME,
            \App\Entity\Rdv::S_HONORE,
            \App\Entity\Rdv::S_ANNULE,
            \App\Entity\Rdv::S_ABSENT,
        ];
        $statusWeek = [];
        foreach ($statuses as $s) {
            $statusWeek[$s] = $rdvRepo->countByStatusBetween($s, $weekStart, $weekEnd);
        }

        // Top prestations par CA sur le mois en cours
        $monthStart = $todayStart->modify('first day of this month')->setTime(0,0,0);
        $monthEnd   = $todayStart->modify('last day of this month')->setTime(23,59,59);
        $topRows    = $payRepo->topPrestationsRevenueBetween($monthStart, $monthEnd, 8);
        $topLabels  = array_column($topRows, 'libelle');
        $topValues  = array_map(fn($r) => (int) $r['total'], $topRows);

        return $this->render('dashboard/index.html.twig', [
            'from'       => $todayStart,
            'to'         => $todayEnd,
            'kpis'       => $kpis,
            'labels30'   => $labels30,
            'values30'   => $values30,
            'statusWeek' => $statusWeek,
            'topLabels'  => $topLabels,
            'topValues'  => $topValues,
        ]);
    }
}

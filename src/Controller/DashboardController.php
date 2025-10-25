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
            'honores'   => $rdvRepo->countByStatusBetween(Rdv::S_HONORE,   $todayStart, $todayEnd),
            'annules'   => $rdvRepo->countByStatusBetween(Rdv::S_ANNULE,   $todayStart, $todayEnd),
            'confirmes' => $rdvRepo->countByStatusBetween(Rdv::S_CONFIRME, $todayStart, $todayEnd),
            'absents'   => $rdvRepo->countByStatusBetween(Rdv::S_ABSENT,   $todayStart, $todayEnd),
            'caToday'   => $payRepo->sumRevenueBetween($todayStart, $todayEnd),
        ];

        // 30 jours glissants (CA)
        $from30 = $todayStart->modify('-29 days');
        $map30  = $payRepo->revenueByDayBetween($from30, $todayEnd);
        $labels30 = [];
        $values30 = [];
        for ($i=0; $i<30; $i++) {
            $date = $from30->modify("+$i days");
            $dateStr = $date->format('Y-m-d');
            // Format d/m pour l'affichage
            $labels30[] = $date->format('d/m');
            $values30[] = $map30[$dateStr] ?? 0;
        }

        // Semaine en cours (lundi → dimanche)
        $dow = (int) $todayStart->format('N'); // 1 = lundi
        $weekStart = $todayStart->modify('-'.($dow-1).' days');
        $weekEnd   = $weekStart->modify('+6 days')->setTime(23,59,59);

        $statuses = [
            Rdv::S_PLANIFIE,
            Rdv::S_CONFIRME,
            Rdv::S_HONORE,
            Rdv::S_ANNULE,
            Rdv::S_ABSENT,
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

        // 🆕 RDV Récents (pour la section "Activité Récente")
        $recentRdvs = $rdvRepo->createQueryBuilder('r')
            ->leftJoin('r.client', 'c')->addSelect('c')
            ->leftJoin('r.prestation', 'p')->addSelect('p')
            ->orderBy('r.startAt', 'DESC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult();

        // 🆕 Statistiques additionnelles
        $additionalStats = [
            // Taux de confirmation
            'tauxConfirmation' => $kpis['rdv'] > 0 
                ? round((($kpis['confirmes'] + $kpis['honores']) / $kpis['rdv']) * 100, 1)
                : 0,
            
            // Taux de conversion (RDV planifiés → honorés)
            'tauxConversion' => $kpis['rdv'] > 0 
                ? round(($kpis['honores'] / $kpis['rdv']) * 100, 1)
                : 0,
            
            // CA moyen par RDV honoré
            'caMoyenParRdv' => $kpis['honores'] > 0
                ? round($kpis['caToday'] / $kpis['honores'], 0)
                : 0,
        ];

        return $this->render('dashboard/index.html.twig', [
            'from'             => $todayStart,
            'to'               => $todayEnd,
            'kpis'             => $kpis,
            'labels30'         => $labels30,
            'values30'         => $values30,
            'statusWeek'       => $statusWeek,
            'topLabels'        => $topLabels,
            'topValues'        => $topValues,
            'recentRdvs'       => $recentRdvs,
            'additionalStats'  => $additionalStats,
        ]);
    }
}
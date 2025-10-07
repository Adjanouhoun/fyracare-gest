<?php
namespace App\Controller;

use App\Entity\Rdv;
use App\Repository\RdvRepository;
use App\Repository\PaymentRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

final class StatsController extends AbstractController
{
    #[Route('/stats', name: 'stats_index', methods: ['GET'])]
    public function index(Request $req, RdvRepository $rdvRepo, PaymentRepository $payRepo): Response
    {
        $tz   = new \DateTimeZone(\date_default_timezone_get());
        $from = $req->query->get('from') ? (new \DateTimeImmutable($req->query->get('from'), $tz))->setTime(0,0,0)
                                         : (new \DateTimeImmutable('first day of this month', $tz))->setTime(0,0,0);
        $to   = $req->query->get('to')   ? (new \DateTimeImmutable($req->query->get('to'),   $tz))->setTime(23,59,59)
                                         : (new \DateTimeImmutable('last day of this month', $tz))->setTime(23,59,59);

        // KPIs simples
        $kpis = [
            'rdv'     => $rdvRepo->countBetween($from, $to),
            'honores' => $rdvRepo->countByStatusBetween(Rdv::S_HONORE, $from, $to),
            'annules' => $rdvRepo->countByStatusBetween(Rdv::S_ANNULE, $from, $to),
            'absents' => $rdvRepo->countByStatusBetween(Rdv::S_ABSENT, $from, $to),
            'ca'      => $payRepo->sumRevenueBetween($from, $to),
        ];

        // CA par jour -> labels + values pour Apex
        $map = $payRepo->revenueByDayBetween($from, $to); // ['Y-m-d' => total]
        $labelsRev = [];
        $valuesRev = [];
        for ($d = $from; $d <= $to; $d = $d->modify('+1 day')) {
            $key = $d->format('Y-m-d');
            $labelsRev[] = $key;
            $valuesRev[] = (int)($map[$key] ?? 0);
        }

        // Top prestations par volume (exemple simple)
        $topPrestations = $payRepo->topPrestationsRevenueBetween($from, $to, 8);

        return $this->render('stats/index.html.twig', [
            'from'         => $from,
            'to'           => $to,
            'kpis'         => $kpis,
            'labelsRev'    => $labelsRev,
            'valuesRev'    => $valuesRev,
            'topPrestations' => array_map(fn($r)=>['libelle'=>$r['libelle'], 'n'=>$r['total']], $topPrestations),
        ]);
    }
}

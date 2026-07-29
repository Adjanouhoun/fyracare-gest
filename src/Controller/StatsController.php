<?php
namespace App\Controller;

use App\Entity\Rdv;
use App\Entity\CashMovement;
use App\Entity\Payment;
use App\Repository\CashMovementRepository;
use App\Repository\RdvRepository;
use App\Repository\PaymentRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_MANAGER')]
final class StatsController extends AbstractController
{
    #[Route('/stats', name: 'stats_index', methods: ['GET'])]
    public function index(
        Request $req,
        RdvRepository $rdvRepo,
        PaymentRepository $payRepo,
        CashMovementRepository $cashRepo,
    ): Response
    {
        $tz   = new \DateTimeZone(\date_default_timezone_get());
        $today = new \DateTimeImmutable('today', $tz);
        [$from, $to] = $this->resolvePeriod($req, $today, $tz);

        if ($from > $to) {
            [$from, $to] = [$to->setTime(0, 0), $from->setTime(23, 59, 59)];
        }

        $paymentsByMethod = $payRepo->sumByMethodBetween($from, $to);
        $expenses = $cashRepo->sumBetweenByTypeAndSource(
            $from,
            $to,
            CashMovement::OUT,
            CashMovement::SRC_EXPENSE,
        );
        $cashIn = $cashRepo->sumBetween($from, $to, CashMovement::IN);
        $cashOut = $cashRepo->sumBetween($from, $to, CashMovement::OUT);

        $kpis = [
            'rdv'     => $rdvRepo->countBetween($from, $to),
            'honores' => $rdvRepo->countByStatusBetween(Rdv::S_HONORE, $from, $to),
            'confirmes' => $rdvRepo->countByStatusBetween(Rdv::S_CONFIRME, $from, $to),
            'annules' => $rdvRepo->countByStatusBetween(Rdv::S_ANNULE, $from, $to),
            'absents' => $rdvRepo->countByStatusBetween(Rdv::S_ABSENT, $from, $to),
            'ca'      => $rdvRepo->sumHonoredRevenueBetween($from, $to),
            'encaissements' => $payRepo->sumRevenueBetween($from, $to),
            'resteAEncaisser' => $rdvRepo->sumOutstandingBetween($from, $to),
            'especes' => $paymentsByMethod[Payment::M_ESPECES] ?? 0,
            'mobile' => $paymentsByMethod[Payment::M_MOBILE] ?? 0,
            'depenses' => $expenses,
            'fluxCaisse' => $cashIn - $cashOut,
        ];

        $receiptsByDay = $payRepo->revenueByDayBetween($from, $to);
        $revenueByDay = $rdvRepo->honoredRevenueByDayBetween($from, $to);
        $activityByDay = $rdvRepo->statusCountsByDayBetween($from, $to);
        $labels = [];
        $receiptsValues = [];
        $revenueValues = [];
        $honoredValues = [];
        $absentValues = [];
        $cancelledValues = [];
        for ($d = $from; $d <= $to; $d = $d->modify('+1 day')) {
            $key = $d->format('Y-m-d');
            $labels[] = $d->format('d/m');
            $receiptsValues[] = (int) ($receiptsByDay[$key] ?? 0);
            $revenueValues[] = (int) ($revenueByDay[$key] ?? 0);
            $honoredValues[] = (int) ($activityByDay[$key][Rdv::S_HONORE] ?? 0);
            $absentValues[] = (int) ($activityByDay[$key][Rdv::S_ABSENT] ?? 0);
            $cancelledValues[] = (int) ($activityByDay[$key][Rdv::S_ANNULE] ?? 0);
        }

        $receivedByService = [];
        foreach ($payRepo->topPrestationsRevenueBetween($from, $to, 100) as $row) {
            $receivedByService[(string) $row['libelle']] = (int) $row['total'];
        }
        $performances = array_map(static function (array $row) use ($receivedByService): array {
            $row['encaisse'] = $receivedByService[$row['libelle']] ?? 0;
            return $row;
        }, $rdvRepo->performanceByPrestationBetween($from, $to));
        $expensesByCategory = array_map(static fn (array $row): array => [
            'name' => (string) $row['name'],
            'total' => (int) $row['total'],
        ], $cashRepo->sumByCategory($from, $to));

        if ($req->query->getString('format') === 'csv') {
            return $this->csvResponse($from, $to, $kpis, $performances);
        }

        return $this->render('stats/index.html.twig', [
            'from'         => $from,
            'to'           => $to,
            'kpis'         => $kpis,
            'labels' => $labels,
            'receiptsValues' => $receiptsValues,
            'revenueValues' => $revenueValues,
            'honoredValues' => $honoredValues,
            'absentValues' => $absentValues,
            'cancelledValues' => $cancelledValues,
            'performances' => $performances,
            'expensesByCategory' => $expensesByCategory,
            'selectedPeriod' => $req->query->getString('period'),
        ]);
    }

    /** @return array{\DateTimeImmutable, \DateTimeImmutable} */
    private function resolvePeriod(
        Request $request,
        \DateTimeImmutable $today,
        \DateTimeZone $timezone,
    ): array {
        $period = $request->query->getString('period');
        [$from, $to] = match ($period) {
            'today' => [$today, $today->setTime(23, 59, 59)],
            'week' => [
                $today->modify('monday this week'),
                $today->modify('sunday this week')->setTime(23, 59, 59),
            ],
            'previous_month' => [
                $today->modify('first day of previous month'),
                $today->modify('last day of previous month')->setTime(23, 59, 59),
            ],
            'year' => [
                $today->modify('first day of january'),
                $today->setTime(23, 59, 59),
            ],
            default => [
                $request->query->get('from')
                    ? (new \DateTimeImmutable($request->query->getString('from'), $timezone))->setTime(0, 0)
                    : $today->modify('first day of this month'),
                $request->query->get('to')
                    ? (new \DateTimeImmutable($request->query->getString('to'), $timezone))->setTime(23, 59, 59)
                    : $today->setTime(23, 59, 59),
            ],
        };

        return $from <= $to
            ? [$from, $to]
            : [$to->setTime(0, 0), $from->setTime(23, 59, 59)];
    }

    /** @param array<string, int> $kpis
     *  @param array<int, array{libelle: string, count: int, ca: int, encaisse: int}> $performances
     */
    private function csvResponse(
        \DateTimeImmutable $from,
        \DateTimeImmutable $to,
        array $kpis,
        array $performances,
    ): Response {
        $stream = fopen('php://temp', 'w+');
        if ($stream === false) {
            throw new \RuntimeException('Impossible de préparer l’export.');
        }

        fwrite($stream, "\xEF\xBB\xBF");
        fputcsv($stream, ['Rapport Fyracare', $from->format('d/m/Y').' - '.$to->format('d/m/Y')], ';');
        fputcsv($stream, [], ';');
        fputcsv($stream, ['Indicateur', 'Valeur'], ';');
        $labels = [
            'rdv' => 'Total RDV',
            'honores' => 'RDV honorés',
            'absents' => 'RDV absents',
            'annules' => 'RDV annulés',
            'ca' => 'CA réalisé (MRU)',
            'encaissements' => 'Encaissements reçus (MRU)',
            'resteAEncaisser' => 'Reste à encaisser (MRU)',
            'especes' => 'Espèces encaissées (MRU)',
            'mobile' => 'Paiements mobiles (MRU)',
            'depenses' => 'Dépenses (MRU)',
            'fluxCaisse' => 'Flux net de caisse physique (MRU)',
        ];
        foreach ($labels as $key => $label) {
            fputcsv($stream, [$label, $kpis[$key] ?? 0], ';');
        }

        fputcsv($stream, [], ';');
        fputcsv($stream, ['Prestation', 'Réalisées', 'CA réalisé (MRU)', 'Encaissé (MRU)', 'Écart (MRU)'], ';');
        foreach ($performances as $row) {
            fputcsv($stream, [
                $row['libelle'],
                $row['count'],
                $row['ca'],
                $row['encaisse'],
                $row['encaisse'] - $row['ca'],
            ], ';');
        }
        rewind($stream);
        $content = stream_get_contents($stream);
        fclose($stream);

        return new Response($content === false ? '' : $content, Response::HTTP_OK, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => sprintf(
                'attachment; filename="fyracare-statistiques-%s-%s.csv"',
                $from->format('Ymd'),
                $to->format('Ymd'),
            ),
        ]);
    }
}

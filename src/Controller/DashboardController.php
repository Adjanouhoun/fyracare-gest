<?php
namespace App\Controller;

use App\Entity\Rdv;
use App\Repository\AppointmentStatusHistoryRepository;
use App\Repository\RdvRepository;
use App\Repository\PaymentRepository;
use App\Service\SettingsProvider;
use App\Service\CashPeriodGuard;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_RECEPTION_CAISSE')]
final class DashboardController extends AbstractController
{
    #[Route('/dashboard', name: 'admin_dashboard', methods: ['GET'])]
    public function index(
        RdvRepository $rdvRepo,
        AppointmentStatusHistoryRepository $statusHistoryRepo,
        PaymentRepository $payRepo,
        SettingsProvider $settingsProvider,
        CashPeriodGuard $cashPeriodGuard,
    ): Response
    {
        $tz = new \DateTimeZone(\date_default_timezone_get());

        // Aujourd'hui
        $todayStart = (new \DateTimeImmutable('today', $tz))->setTime(0,0,0);
        $todayEnd   = (new \DateTimeImmutable('today', $tz))->setTime(23,59,59);
        $now = new \DateTimeImmutable('now', $tz);
        $reminderHours = $settingsProvider->get()->getConfirmationReminderHours();
        $confirmationDeadline = $now->modify(sprintf('+%d hours', $reminderHours));
        $awaitingConfirmation = $rdvRepo->findAwaitingConfirmation($now, $confirmationDeadline, 5);
        $awaitingConfirmationCount = $rdvRepo->countAwaitingConfirmation($now, $confirmationDeadline);
        $overdueUnresolved = $rdvRepo->findOverdueUnresolved($now, 5);
        $overdueUnresolvedCount = $rdvRepo->countOverdueUnresolved($now);
        $partiallyPaidRdvs = $rdvRepo->findPartiallyPaid(5);
        $partiallyPaidCount = $rdvRepo->countPartiallyPaid();
        $statusChangesToday = $statusHistoryRepo->countNewStatusesBetween($todayStart, $todayEnd);
        $canViewFinancials = $this->isGranted('ROLE_MANAGER');

        // KPIs du jour
        $kpis = [
            'rdv'       => $rdvRepo->countBetween($todayStart, $todayEnd),
            'honores'   => $rdvRepo->countByStatusBetween(Rdv::S_HONORE,   $todayStart, $todayEnd),
            'annules'   => $rdvRepo->countByStatusBetween(Rdv::S_ANNULE,   $todayStart, $todayEnd),
            'confirmes' => $rdvRepo->countByStatusBetween(Rdv::S_CONFIRME, $todayStart, $todayEnd),
            'absents'   => $rdvRepo->countByStatusBetween(Rdv::S_ABSENT,   $todayStart, $todayEnd),
            'aConfirmer' => $awaitingConfirmationCount,
            'enRetard' => $overdueUnresolvedCount,
            'paiementsACompleter' => $partiallyPaidCount,
        ];

        // 30 jours glissants (CA)
        $from30 = $todayStart->modify('-29 days');
        $map30  = $canViewFinancials ? $payRepo->revenueByDayBetween($from30, $todayEnd) : [];
        $labels30 = [];
        $values30 = [];
        for ($i=0; $i<30; $i++) {
            $date = $from30->modify("+$i days");
            $dateStr = $date->format('Y-m-d');
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

        // Prochains rendez-vous à traiter, plutôt qu'un historique peu actionnable.
        $recentRdvs = $rdvRepo->createQueryBuilder('r')
            ->leftJoin('r.client', 'c')->addSelect('c')
            ->leftJoin('r.prestation', 'p')->addSelect('p')
            ->andWhere('r.startAt >= :now')
            ->andWhere('r.status IN (:activeStatuses)')
            ->setParameter('now', $now)
            ->setParameter('activeStatuses', [Rdv::S_PLANIFIE, Rdv::S_CONFIRME])
            ->orderBy('r.startAt', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult();

        return $this->render('dashboard/index.html.twig', [
            'from'                => $todayStart,
            'to'                  => $todayEnd,
            'kpis'                => $kpis,
            'labels30'            => $labels30,
            'values30'            => $values30,
            'statusWeek'          => $statusWeek,
            'recentRdvs'          => $recentRdvs,
            'partiallyPaidRdvs'   => $partiallyPaidRdvs,
            'partiallyPaidCount'  => $partiallyPaidCount,
            'reminderHours'       => $reminderHours,
            'awaitingConfirmation'=> $awaitingConfirmation,
            'overdueUnresolved'   => $overdueUnresolved,
            'pendingCashClosure'  => $cashPeriodGuard->findOldestPendingDate($todayStart),
            'canViewFinancials'   => $canViewFinancials,
            'statusChangesToday' => $statusChangesToday,
        ]);
    }
}

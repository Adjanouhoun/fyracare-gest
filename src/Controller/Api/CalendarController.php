<?php

namespace App\Controller\Api;

use App\Repository\RdvRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
 
#[Route('/api/calendar', name: 'api_calendar_')]
class CalendarController extends AbstractController
{
    #[Route('/events', name: 'events', methods: ['GET'])]
    public function events(Request $request, RdvRepository $repo): JsonResponse
    {
        // FullCalendar peut envoyer ?start=...&end=...
        $start = $request->query->get('start');
        $end   = $request->query->get('end');

        // Filtre simple : entre start et end s’ils existent, sinon tout futur
        $qb = $repo->createQueryBuilder('r');
        if ($start) {
            $qb->andWhere('r.startAt >= :start')->setParameter('start', new \DateTimeImmutable($start));
        } else {
            $qb->andWhere('r.startAt >= :now')->setParameter('now', new \DateTimeImmutable());
        }
        if ($end) {
            $qb->andWhere('r.startAt <= :end')->setParameter('end', new \DateTimeImmutable($end));
        }
        $rdvs = $qb->orderBy('r.startAt', 'ASC')->getQuery()->getResult();

        $events = [];
        foreach ($rdvs as $rdv) {
            $titleParts = [];
            if (method_exists($rdv, 'getClient') && $rdv->getClient()) {
                $titleParts[] = (string) $rdv->getClient();
            }
            if (method_exists($rdv, 'getPrestation') && $rdv->getPrestation()) {
                $titleParts[] = (string) $rdv->getPrestation();
            }
            $title = implode(' • ', $titleParts) ?: 'Rendez-vous';

            // Couleur selon statut
            $status = method_exists($rdv, 'getStatus') ? (string) $rdv->getStatus() : '';
            $bg = '#6c757d'; // défaut
            if ($status === 'PLANIFIE') $bg = '#0dcaf0';
            if ($status === 'HONORE')   $bg = '#198754';
            if ($status === 'ANNULE')   $bg = '#dc3545';

            $events[] = [
                'id'    => $rdv->getId(),
                'title' => $title,
                'start' => $rdv->getStartAt()?->format(\DateTimeInterface::ATOM),
                'end'   => $rdv->getEndAt()?->format(\DateTimeInterface::ATOM),
                'url'   => $this->generateUrl('app_rdv_show', ['id' => $rdv->getId()]),
                'backgroundColor' => $bg,
                'borderColor'     => $bg,
                'textColor'       => '#fff',
            ];
        }

        return $this->json($events);
    }
}

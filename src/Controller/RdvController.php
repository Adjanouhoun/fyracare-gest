<?php

namespace App\Controller;

use App\Entity\Rdv;
use App\Entity\Client;
use App\Entity\Prestation;
use App\Form\RdvType;
use App\Repository\RdvRepository;
use App\Repository\ClientRepository;
use App\Repository\PrestationRepository;
use App\Service\AppointmentOverlapService;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/rdv')]
class RdvController extends AbstractController
{
    public function __construct(
        private AppointmentOverlapService $overlapService
    ) {}

    #[Route('/', name: 'app_rdv_index', methods: ['GET'])]
    public function index(Request $request, RdvRepository $repo, PaginatorInterface $paginator): Response
    {
        $today = new \DateTimeImmutable('today', new \DateTimeZone('Europe/Paris'));

        $pagination = $paginator->paginate(
            $repo->createQBForDay($today),
            $request->query->getInt('page', 1),
            10
        );

        return $this->render('rdv/index.html.twig', [
            'today'      => $today,
            'pagination' => $pagination,
        ]);
    }

    #[Route('/all', name: 'app_rdv_all', methods: ['GET'])]
    public function all(Request $request, RdvRepository $repo, PaginatorInterface $paginator): Response
    {
        $status = $request->query->get('status');
        $q      = trim((string)$request->query->get('q', ''));

        $qb = $repo->createQueryBuilder('r')
            ->leftJoin('r.client', 'c')->addSelect('c')
            ->leftJoin('r.prestation', 'p')->addSelect('p')
            ->orderBy('r.startAt', 'DESC');

        if ($status) {
            $qb->andWhere('r.status = :st')->setParameter('st', $status);
        }

        if ($q !== '') {
            $qb->andWhere('LOWER(c.nometprenom) LIKE :q OR LOWER(p.libelle) LIKE :q2 OR c.telephone LIKE :qraw')
               ->setParameter('q', '%'.mb_strtolower($q).'%')
               ->setParameter('q2', '%'.mb_strtolower($q).'%')
               ->setParameter('qraw', '%'.$q.'%');
        }

        $pagination = $paginator->paginate(
            $qb,
            $request->query->getInt('page', 1),
            15
        );

        return $this->render('rdv/all.html.twig', [
            'pagination' => $pagination,
            'status'     => $status,
            'q'          => $q,
            'statuses'   => [
                Rdv::S_PLANIFIE, Rdv::S_CONFIRME, Rdv::S_HONORE, Rdv::S_ANNULE, Rdv::S_ABSENT
            ],
        ]);
    }

    #[Route('/new', name: 'app_rdv_new', methods: ['GET', 'POST'])]
    public function new(
        Request $request, 
        EntityManagerInterface $em,
        ClientRepository $clientRepo
    ): Response
    {
        $rdv = new Rdv();
        
        // ✅ Pré-remplir le client si l'ID est dans l'URL
        $clientId = $request->query->get('client');
        if ($clientId) {
            $client = $clientRepo->find($clientId);
            if ($client) {
                $rdv->setClient($client);
            } else {
                $this->addFlash('warning', 'Client introuvable avec l\'ID: ' . $clientId);
            }
        }
        
        // ✅ Pré-remplir la date si présente dans l'URL
        $dateParam = $request->query->get('date');
        if ($dateParam) {
            try {
                // Nettoyer la date pour enlever le timezone si présent
                // Ex: "2025-10-21T00:30:00 02:00" ou "2025-10-21T00:30:00+02:00"
                $dateClean = preg_replace('/([T\s]\d{2}:\d{2}:\d{2})[\s\+\-].*$/', '$1', $dateParam);
                
                $date = new \DateTimeImmutable($dateClean);
                $rdv->setStartAt($date);
            } catch (\Exception $e) {
                $this->addFlash('warning', 'Format de date invalide: ' . $dateParam);
                error_log("Erreur parsing date: " . $e->getMessage() . " | Date: " . $dateParam);
            }
        }
        
        $form = $this->createForm(RdvType::class, $rdv);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Calcul automatique de endAt
            if ($rdv->getPrestation() && $rdv->getStartAt()) {
                $duree = $rdv->getPrestation()->getDureeMin();
                $end = (clone $rdv->getStartAt())->modify("+{$duree} minutes");
                $rdv->setEndAt($end);
            }

            // Vérification des chevauchements
            if (!$this->overlapService->canScheduleAppointment($rdv)) {
                $errorMessage = $this->overlapService->getOverlapErrorMessage($rdv);
                $this->addFlash('danger', $errorMessage);
            } else {
                $em->persist($rdv);
                $em->flush();
                $this->addFlash('success', 'Rendez-vous créé avec succès.');
                return $this->redirectToRoute('app_rdv_show', ['id' => $rdv->getId()]);
            }
        }

        return $this->render('rdv/new.html.twig', [
            'rdv' => $rdv,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_rdv_show', methods: ['GET'])]
    public function show(Rdv $rdv): Response
    {
        return $this->render('rdv/show.html.twig', [
            'rdv' => $rdv,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_rdv_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Rdv $rdv, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(RdvType::class, $rdv);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Recalcul de endAt
            if ($rdv->getPrestation() && $rdv->getStartAt()) {
                $duree = $rdv->getPrestation()->getDureeMin();
                $end = (clone $rdv->getStartAt())->modify("+{$duree} minutes");
                $rdv->setEndAt($end);
            }

            // Vérification des chevauchements
            if (!$this->overlapService->canScheduleAppointment($rdv)) {
                $errorMessage = $this->overlapService->getOverlapErrorMessage($rdv);
                $this->addFlash('danger', $errorMessage);
            } else {
                $em->flush();
                $this->addFlash('success', 'Rendez-vous mis à jour.');
                return $this->redirectToRoute('app_rdv_show', ['id' => $rdv->getId()]);
            }
        }

        return $this->render('rdv/edit.html.twig', [
            'rdv' => $rdv,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_rdv_delete', methods: ['POST'])]
    public function delete(Request $request, Rdv $rdv, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete'.$rdv->getId(), $request->request->get('_token'))) {
            $em->remove($rdv);
            $em->flush();
            $this->addFlash('success', 'Rendez-vous supprimé.');
        }
        return $this->redirectToRoute('app_rdv_index');
    }

    // ========== ROUTES API POUR LE CALENDRIER ==========

    /**
     * @IsGranted("IS_AUTHENTICATED_REMEMBERED")
     */
    #[Route('/api/calendar-events', name: 'api_rdv_calendar_events', methods: ['GET'])]
    public function calendarEvents(Request $request, RdvRepository $rdvRepo): JsonResponse
    {
        try {
            $start = $request->query->get('start');
            $end = $request->query->get('end');
            
            error_log("=== API CALENDAR EVENTS ===");
            error_log("Start brut: $start");
            error_log("End brut: $end");

            if (!$start || !$end) {
                return $this->json(['error' => 'Paramètres start et end requis'], 400);
            }

            // ✅ FIX : Nettoyer les dates pour enlever le timezone
            // FullCalendar envoie : 2025-09-01T00:00:00+02:00
            // On veut : 2025-09-01 00:00:00
            $startClean = preg_replace('/T(\d{2}:\d{2}:\d{2}).*$/', ' $1', $start);
            $endClean = preg_replace('/T(\d{2}:\d{2}:\d{2}).*$/', ' $1', $end);
            
            error_log("Start nettoyé: $startClean");
            error_log("End nettoyé: $endClean");

            // Conversion en DateTimeImmutable
            try {
                $startDate = new \DateTimeImmutable($startClean);
                $endDate = new \DateTimeImmutable($endClean);
            } catch (\Exception $e) {
                error_log("❌ Erreur parsing dates: " . $e->getMessage());
                return $this->json(['error' => 'Format de date invalide'], 400);
            }

            // Requête avec BETWEEN
            $qb = $rdvRepo->createQueryBuilder('r')
                ->leftJoin('r.client', 'c')
                ->addSelect('c')
                ->leftJoin('r.prestation', 'p')
                ->addSelect('p')
                ->where('r.startAt BETWEEN :start AND :end')
                ->setParameter('start', $startDate)
                ->setParameter('end', $endDate)
                ->orderBy('r.startAt', 'ASC');

            $rdvs = $qb->getQuery()->getResult();
            
            error_log("✅ RDV trouvés: " . count($rdvs));

            // Construction du JSON
            $events = [];
            foreach ($rdvs as $rdv) {
                // Client
                $client = 'Sans client';
                if ($rdv->getClient()) {
                    if (method_exists($rdv->getClient(), 'getNometprenom')) {
                        $client = $rdv->getClient()->getNometprenom();
                    } elseif (method_exists($rdv->getClient(), '__toString')) {
                        $client = (string) $rdv->getClient();
                    }
                }
                
                // Prestation
                $prestation = 'Sans prestation';
                if ($rdv->getPrestation()) {
                    if (method_exists($rdv->getPrestation(), 'getLibelle')) {
                        $prestation = $rdv->getPrestation()->getLibelle();
                    } elseif (method_exists($rdv->getPrestation(), '__toString')) {
                        $prestation = (string) $rdv->getPrestation();
                    }
                }
                
                // Statut et couleur
                $status = $rdv->getStatus() ?? 'PLANIFIE';
                $backgroundColor = match($status) {
                    'PLANIFIE' => '#0dcaf0',
                    'CONFIRME' => '#0d6efd',
                    'HONORE'   => '#198754',
                    'ANNULE'   => '#dc3545',
                    'ABSENT'   => '#6c757d',
                    default    => '#6c757d'
                };

                $events[] = [
                    'id'              => $rdv->getId(),
                    'title'           => $client . ' - ' . $prestation,
                    'start'           => $rdv->getStartAt()->format('Y-m-d\TH:i:s'),
                    'end'             => $rdv->getEndAt() ? $rdv->getEndAt()->format('Y-m-d\TH:i:s') : null,
                    'backgroundColor' => $backgroundColor,
                    'borderColor'     => $backgroundColor,
                    'textColor'       => '#ffffff',
                    'extendedProps'   => [
                        'status'      => $status,
                        'client'      => $client,
                        'prestation'  => $prestation,
                        'notes'       => $rdv->getNotes()
                    ]
                ];
            }

            error_log("📦 Events JSON: " . json_encode($events));
            
            return $this->json($events);

        } catch (\Exception $e) {
            error_log("❌ ERREUR GLOBALE: " . $e->getMessage());
            error_log("Stack: " . $e->getTraceAsString());
            
            return $this->json([
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ], 500);
        }
    }


    /**
     * API: Créer un RDV rapide depuis le calendrier
     */
    #[Route('/api/quick-create', name: 'api_rdv_quick_create', methods: ['POST'])]
    public function quickCreate(
        Request $request,
        EntityManagerInterface $em,
        ClientRepository $clientRepo,
        PrestationRepository $prestRepo
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        try {
            $rdv = new Rdv();
            
            // Client
            if (!empty($data['clientId'])) {
                $client = $clientRepo->find($data['clientId']);
                if (!$client) {
                    return $this->json(['error' => 'Client introuvable'], 404);
                }
                $rdv->setClient($client);
            } else {
                return $this->json(['error' => 'Client requis'], 400);
            }

            // Prestation
            if (!empty($data['prestationId'])) {
                $prestation = $prestRepo->find($data['prestationId']);
                if (!$prestation) {
                    return $this->json(['error' => 'Prestation introuvable'], 404);
                }
                $rdv->setPrestation($prestation);
            } else {
                return $this->json(['error' => 'Prestation requise'], 400);
            }

            // Dates
            if (!empty($data['start'])) {
                $start = new \DateTimeImmutable($data['start']);
                $rdv->setStartAt($start);
                
                // Calcul auto de la fin
                $duree = $prestation->getDureeMin();
                $end = $start->modify("+{$duree} minutes");
                $rdv->setEndAt($end);
            } else {
                return $this->json(['error' => 'Date de début requise'], 400);
            }

            // Statut
            $rdv->setStatus($data['status'] ?? Rdv::S_PLANIFIE);

            // Notes
            if (!empty($data['notes'])) {
                $rdv->setNotes($data['notes']);
            }

            // Vérification des chevauchements
            if (!$this->overlapService->canScheduleAppointment($rdv)) {
                return $this->json([
                    'error' => $this->overlapService->getOverlapErrorMessage($rdv)
                ], 409);
            }

            $em->persist($rdv);
            $em->flush();

            return $this->json([
                'success' => true,
                'id' => $rdv->getId(),
                'message' => 'Rendez-vous créé avec succès'
            ], 201);

        } catch (\Exception $e) {
            error_log("❌ Erreur quick-create: " . $e->getMessage());
            return $this->json(['error' => $e->getMessage()], 400);
        }
    }

    #[Route('/api/autocomplete/client', name: 'api_client_autocomplete', methods: ['GET'])]
    public function clientAutocomplete(Request $request, ClientRepository $repo): Response
    {
        $q = trim((string) $request->query->get('q', ''));
        $qb = $repo->createQueryBuilder('c')->orderBy('c.nometprenom', 'ASC');
        if ($q !== '') {
            $qb->andWhere('LOWER(c.nometprenom) LIKE :q OR c.telephone LIKE :qraw')
               ->setParameter('q', '%'.mb_strtolower($q).'%')
               ->setParameter('qraw', '%'.$q.'%');
        }
        $qb->setMaxResults(20);

        $results = array_map(function($c) {
            return [
                'id'   => $c->getId(),
                'text' => $c->getNometprenom().' — '.$c->getTelephone(),
            ];
        }, $qb->getQuery()->getResult());

        return $this->json($results);
    }

    #[Route('/api/autocomplete/prestation', name: 'api_prestation_autocomplete', methods: ['GET'])]
    public function prestationAutocomplete(Request $request, PrestationRepository $repo): Response
    {
        $q = trim((string) $request->query->get('q', ''));
        $qb = $repo->createQueryBuilder('p')->orderBy('p.libelle', 'ASC');
        if ($q !== '') {
            $qb->andWhere('LOWER(p.libelle) LIKE :q OR LOWER(p.description) LIKE :q')
               ->setParameter('q', '%'.mb_strtolower($q).'%');
        }
        $qb->setMaxResults(20);

        $results = array_map(function($p) {
            return [
                'id'      => $p->getId(),
                'text'    => $p->getLibelle().' — '.$p->getPrix().' MRU',
                'duree'   => $p->getDureeMin(),
                'prix'    => $p->getPrix(),
            ];
        }, $qb->getQuery()->getResult());

        return $this->json($results);
    }

    #[Route('/api/prestation/{id}', name: 'api_prestation_info', methods: ['GET'])]
    public function prestationInfo(Prestation $prestation): Response
    {
        return $this->json([
            'id'    => $prestation->getId(),
            'duree' => $prestation->getDureeMin(),
            'prix'  => $prestation->getPrix(),
        ]);
    }
}
<?php

namespace App\Controller;

use App\Entity\Rdv;
use App\Entity\Client;
use App\Entity\Prestation;
use App\Form\RdvType;
use App\Repository\RdvRepository;
use App\Repository\ClientRepository;
use App\Repository\PrestationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/rdv')]
class RdvController extends AbstractController
{
    #[Route('/', name: 'app_rdv_index', methods: ['GET'])]
    public function index(Request $request, RdvRepository $repo, PaginatorInterface $paginator): Response
    {
        $today = new \DateTimeImmutable('today', new \DateTimeZone('Europe/Paris'));

        $pagination = $paginator->paginate(
            $repo->createQBForDay($today),                 // QB des RDV du jour
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
        // filtres simples (facultatifs)
        $status = $request->query->get('status');            // PLANIFIE, CONFIRME, HONORE, ANNULE, ABSENT
        $q      = trim((string)$request->query->get('q', '')); // recherche client / prestation

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

    #[Route('/new', name: 'app_rdv_new', methods: ['GET','POST'])]
    public function new(Request $request, EntityManagerInterface $em, PrestationRepository $prestationRepo): Response {
        $rdv = new Rdv();

        $form = $this->createForm(RdvType::class, $rdv, [
            'attr' => ['data-autocomplete' => '1'],
        ]);
        $form->handleRequest($request);

        // Sécurité serveur: recalcul de endAt d'après la prestation choisie
        if ($form->isSubmitted() && $form->isValid()) {
            if ($rdv->getPrestation() && $rdv->getStartAt()) {
                $duree = $rdv->getPrestation()->getDureeMin();
                $end = (clone $rdv->getStartAt())->modify("+{$duree} minutes");
                $rdv->setEndAt($end);
            }

            $em->persist($rdv);
            $em->flush();

            $this->addFlash('success', 'Rendez-vous créé.');
            return $this->redirectToRoute('app_rdv_show', ['id' => $rdv->getId()]);
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

    #[Route('/{id}/edit', name: 'app_rdv_edit', methods: ['GET','POST'])]
    public function edit(Request $request, Rdv $rdv, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(RdvType::class, $rdv, [
            'attr' => ['data-autocomplete' => '1'],
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($rdv->getPrestation() && $rdv->getStartAt()) {
                $duree = $rdv->getPrestation()->getDureeMin();
                $end = (clone $rdv->getStartAt())->modify("+{$duree} minutes");
                $rdv->setEndAt($end);
            }

            $em->flush();
            $this->addFlash('success', 'Rendez-vous mis à jour.');
            return $this->redirectToRoute('app_rdv_show', ['id' => $rdv->getId()]);
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

    // ---------- API AUTOCOMPLETE (JSON) ----------

    #[Route('/api/autocomplete/client', name: 'api_client_autocomplete', methods: ['GET'])]
    public function clientAutocomplete(Request $request, ClientRepository $repo): Response
    {
        $q = trim((string) $request->query->get('q', ''));
        $qb = $repo->createQueryBuilder('c')->orderBy('c.nometprenom', 'ASC');
        if ($q !== '') {
            $qb->andWhere('LOWER(c.nometprenom) LIKE :q OR LOWER(c.email) LIKE :q OR c.telephone LIKE :qraw')
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
                'text'    => $p->getLibelle().' — '.$p->getPrix().' FCFA',
                'duree'   => $p->getDureeMin(),
                'prix'    => $p->getPrix(),
            ];
        }, $qb->getQuery()->getResult());

        return $this->json($results);
    }

    /**
     * Renvoie info d’une prestation (durée/prix) — utile si on a juste l’ID côté client.
     */
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

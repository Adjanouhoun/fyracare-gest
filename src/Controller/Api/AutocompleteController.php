<?php

namespace App\Controller\Api;

use App\Repository\ClientRepository;
use App\Repository\PrestationRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/autocomplete', name: 'api_autocomplete_')]
class AutocompleteController extends AbstractController
{
    #[Route('/clients', name: 'clients', methods: ['GET'])]
    public function clients(Request $request, ClientRepository $repo): JsonResponse
    {
        // Préremplissage par id (édition)
        if ($id = $request->query->get('id')) {
            $c = $repo->find($id);
            if (!$c) {
                return $this->json(['results' => []]);
            }
            return $this->json(['results' => [[
                'id'   => $c->getId(),
                'text' => trim($c->getNometprenom() . ($c->getTelephone() ? ' — '.$c->getTelephone() : '')),
            ]]]);
        }

        $q = trim((string) $request->query->get('q', ''));
        $qb = $repo->createQueryBuilder('c')
            ->setMaxResults(20)
            ->orderBy('c.nometprenom', 'ASC');

        if ($q !== '') {
            $qb->andWhere('c.nometprenom LIKE :q OR c.telephone LIKE :q OR c.email LIKE :q')
               ->setParameter('q', '%'.$q.'%');
        }

        $items = $qb->getQuery()->getResult();

        $results = array_map(fn($c) => [
            'id'   => $c->getId(),
            'text' => trim($c->getNometprenom() . ($c->getTelephone() ? ' — '.$c->getTelephone() : '')),
        ], $items);

        return $this->json(['results' => $results]);
    }

    #[Route('/prestations', name: 'prestations', methods: ['GET'])]
    public function prestations(Request $request, PrestationRepository $repo): JsonResponse
    {
        // Préremplissage par id (édition)
        if ($id = $request->query->get('id')) {
            $p = $repo->find($id);
            if (!$p) {
                return $this->json(['results' => []]);
            }
            return $this->json(['results' => [[
                'id'   => $p->getId(),
                'text' => $p->getLibelle() . ($p->getPrix() ? ' — '.$p->getPrix().' MRU' : ''),
            ]]]);
        }

        $q = trim((string) $request->query->get('q', ''));
        $qb = $repo->createQueryBuilder('p')
            ->setMaxResults(20)
            ->orderBy('p.libelle', 'ASC');

        if ($q !== '') {
            $qb->andWhere('p.libelle LIKE :q')
               ->setParameter('q', '%'.$q.'%');
        }

        $items = $qb->getQuery()->getResult();

        $results = array_map(fn($p) => [
            'id'   => $p->getId(),
            'text' => $p->getLibelle() . ($p->getPrix() ? ' — '.$p->getPrix().' MRU' : ''),
        ], $items);

        return $this->json(['results' => $results]);
    }
}

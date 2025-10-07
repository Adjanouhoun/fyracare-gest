<?php

namespace App\Controller\Api;

use App\Entity\Client;
use App\Entity\Prestation;
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
        $q = trim((string)$request->query->get('q', ''));

        $qb = $repo->createQueryBuilder('c');
        if ($q !== '') {
            $qb->andWhere('LOWER(c.nometprenom) LIKE :q OR c.telephone LIKE :q OR LOWER(c.email) LIKE :q')
               ->setParameter('q', '%'.mb_strtolower($q).'%');
        }

        $clients = $qb->orderBy('c.nometprenom', 'ASC')
                      ->setMaxResults(20)
                      ->getQuery()->getResult();

        $data = array_map(static function (Client $c) {
            return [
                'id'   => $c->getId(),
                'text' => sprintf(
                    '%s — %s%s',
                    $c->getNometprenom(),
                    $c->getTelephone(),
                    $c->getEmail() ? ' — '.$c->getEmail() : ''
                ),
            ];
        }, $clients);

        return $this->json(['results' => $data]);
    }

    #[Route('/prestations', name: 'prestations', methods: ['GET'])]
    public function prestations(Request $request, PrestationRepository $repo): JsonResponse
    {
        $q = trim((string)$request->query->get('q', ''));

        $qb = $repo->createQueryBuilder('p');
        if ($q !== '') {
            $qb->andWhere('LOWER(p.libelle) LIKE :q OR LOWER(COALESCE(p.description, \'\')) LIKE :q')
               ->setParameter('q', '%'.mb_strtolower($q).'%');
        }

        $prestations = $qb->orderBy('p.libelle', 'ASC')
                          ->setMaxResults(20)
                          ->getQuery()->getResult();

        $data = array_map(static function (Prestation $p) {
            return [
                'id'   => $p->getId(),
                'text' => sprintf(
                    '%s — %d min — %s FCFA',
                    (string)$p->getLibelle(),
                    (int)$p->getDureeMin(),
                    number_format((int)$p->getPrix(), 0, ',', ' ')
                ),
            ];
        }, $prestations);

        return $this->json(['results' => $data]);
    }
}

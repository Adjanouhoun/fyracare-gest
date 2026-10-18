<?php

namespace App\Controller;

use App\Entity\Client;
use App\Form\ClientType;
use App\Repository\ClientRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Knp\Component\Pager\PaginatorInterface;
use App\Repository\PaymentRepository;
use App\Repository\RdvRepository;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/client')]
#[IsGranted('ROLE_RECEPTION_CAISSE')]
final class ClientController extends AbstractController
{

    #[Route('/', name: 'app_client_index', methods: ['GET'])]
    public function index(Request $request, ClientRepository $repo, PaginatorInterface $paginator): Response
    {
        $q = $request->query->get('q', '');
        $qb = $repo->searchQb($q);

        $pagination = $paginator->paginate(
            $qb,
            $request->query->getInt('page', 1),
            10 // éléments par page
        );

        return $this->render('client/index.html.twig', [
            'pagination' => $pagination,
            'q' => $q,
        ]);
    }

    #[Route('/ranking', name: 'app_client_ranking', methods: ['GET'])]
    #[IsGranted('ROLE_MANAGER')]
    public function ranking(
        Request $request,
        ClientRepository $clients,
        RdvRepository $appointments,
        PaymentRepository $payments,
        PaginatorInterface $paginator,
    ): Response {
        $timezone = new \DateTimeZone(\date_default_timezone_get());
        $fromInput = $request->query->getString('from');
        $toInput = $request->query->getString('to');
        $from = $this->parseRankingDate($fromInput, $timezone)?->setTime(0, 0);
        $to = $this->parseRankingDate($toInput, $timezone)?->setTime(23, 59, 59);
        if ($from && $to && $from > $to) {
            [$from, $to] = [$to->setTime(0, 0), $from->setTime(23, 59, 59)];
            [$fromInput, $toInput] = [$toInput, $fromInput];
        }

        $query = trim($request->query->getString('q'));
        $minimumVisits = max(0, $request->query->getInt('minVisits'));
        $sort = $request->query->getString('sort', 'spent');
        if (!in_array($sort, ['spent', 'visits', 'average', 'recent'], true)) {
            $sort = 'spent';
        }

        $visitStats = [];
        foreach ($appointments->honoredVisitsByClient($from, $to) as $stat) {
            $visitStats[$stat['clientId']] = $stat;
        }
        $spendingStats = [];
        foreach ($payments->spendingByClient($from, $to) as $stat) {
            $spendingStats[$stat['clientId']] = $stat['totalSpent'];
        }

        $rows = [];
        foreach ($clients->searchQb($query)->getQuery()->getResult() as $client) {
            \assert($client instanceof Client);
            $clientId = (int) $client->getId();
            $visits = $visitStats[$clientId]['visits'] ?? 0;
            $totalSpent = $spendingStats[$clientId] ?? 0;
            if (($visits === 0 && $totalSpent === 0) || $visits < $minimumVisits) {
                continue;
            }
            $rows[] = [
                'client' => $client,
                'visits' => $visits,
                'totalSpent' => $totalSpent,
                'average' => $visits > 0 ? (int) round($totalSpent / $visits) : 0,
                'lastVisit' => $visitStats[$clientId]['lastVisit'] ?? null,
            ];
        }

        usort($rows, static function (array $left, array $right) use ($sort): int {
            $comparison = match ($sort) {
                'visits' => $right['visits'] <=> $left['visits'],
                'average' => $right['average'] <=> $left['average'],
                'recent' => ($right['lastVisit'] ?? '') <=> ($left['lastVisit'] ?? ''),
                default => $right['totalSpent'] <=> $left['totalSpent'],
            };

            return $comparison !== 0 ? $comparison : $right['totalSpent'] <=> $left['totalSpent'];
        });

        $pagination = $paginator->paginate($rows, max(1, $request->query->getInt('page', 1)), 25);
        $topBySpent = null;
        $topByVisits = null;
        foreach ($rows as $row) {
            if ($topBySpent === null || $row['totalSpent'] > $topBySpent['totalSpent']) {
                $topBySpent = $row;
            }
            if ($topByVisits === null || $row['visits'] > $topByVisits['visits']) {
                $topByVisits = $row;
            }
        }

        return $this->render('client/ranking.html.twig', [
            'pagination' => $pagination,
            'q' => $query,
            'from' => $fromInput,
            'to' => $toInput,
            'sort' => $sort,
            'minVisits' => $minimumVisits,
            'activeClients' => count($rows),
            'totalSpent' => array_sum(array_column($rows, 'totalSpent')),
            'topBySpent' => $topBySpent,
            'topByVisits' => $topByVisits,
        ]);
    }

    private function parseRankingDate(string $value, \DateTimeZone $timezone): ?\DateTimeImmutable
    {
        if ($value === '') {
            return null;
        }

        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value, $timezone);
        $errors = \DateTimeImmutable::getLastErrors();

        return $date && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))
            ? $date
            : null;
    }

    #[Route('/new', name: 'app_client_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $client = new Client();
        $form = $this->createForm(ClientType::class, $client);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $client->setCreatedAt(new \datetime());
            $entityManager->persist($client);
            $entityManager->flush();
            $this->addFlash('success', '✅ Le client a bien été <strong>créé</strong>.');
            return $this->redirectToRoute('app_client_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('client/new.html.twig', [
            'client' => $client,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_client_show', methods: ['GET'])]
    public function show(Client $client, PaymentRepository $paymentRepo,): Response
    {
         // Tous les paiements dont le RDV appartient à ce client
        $payments = $paymentRepo->createQueryBuilder('pay')
        ->leftJoin('pay.rdv', 'r')->addSelect('r')
        ->leftJoin('r.prestation', 'pr')->addSelect('pr')
        ->andWhere('r.client = :client')
        ->setParameter('client', $client)
        ->orderBy('pay.paidAt', 'DESC')
        ->getQuery()->getResult();

        return $this->render('client/show.html.twig', [
            'client' => $client,
            'payments' => $payments,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_client_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Client $client, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(ClientType::class, $client);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();
            $this->addFlash('success', '✏️ Le client a bien été <strong>modifié</strong>.');
            return $this->redirectToRoute('app_client_show', ['id' => $client->getId()]);

        }

        return $this->render('client/edit.html.twig', [
            'client' => $client,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/delete-confirm', name: 'app_client_delete_confirm', methods: ['GET'])]
    #[IsGranted(
        'ROLE_ADMIN',
        message: "Vous devez être administrateur pour effectuer cette action.",
        statusCode: 403
    )]
    public function deleteConfirm(Client $client, EntityManagerInterface $em): Response
    {
        // Récupérer tous les RDV du client
        $rdvs = $client->getRdvs()->toArray();
        
        // Récupérer tous les paiements liés aux RDV
        $payments = [];
        $rdvIds = [];
        foreach ($rdvs as $rdv) {
            $rdvIds[] = $rdv->getId();
            foreach ($rdv->getPayments() as $payment) {
                $payments[] = $payment;
            }
        }
        
        // Récupérer les mouvements de caisse liés
        $cashRepo = $em->getRepository(\App\Entity\CashMovement::class);
        $cashMovements = $cashRepo->findByRdvIds($rdvIds);
        
        return $this->render('client/delete_confirm.html.twig', [
            'client' => $client,
            'rdvs' => $rdvs,
            'payments' => $payments,
            'cashMovements' => $cashMovements,
        ]);
    }

    #[Route('/{id}', name: 'app_client_delete', methods: ['POST'])]
    #[IsGranted(
        'ROLE_ADMIN',
        message: "Vous devez être administrateur pour effectuer cette action.",
        statusCode: 403
    )]
    public function delete(Request $request, Client $client, EntityManagerInterface $entityManager): Response
    {
        if (!$this->isCsrfTokenValid('delete'.$client->getId(), $request->request->get('_token'))) {
            $this->addFlash('warning', 'Jeton de sécurité invalide.');
            return $this->redirectToRoute('app_client_index');
        }

        if (!$client->getRdvs()->isEmpty()) {
            $this->addFlash(
                'danger',
                'Ce client possède un historique de rendez-vous ou de paiements et ne peut pas être supprimé.',
            );
            return $this->redirectToRoute('app_client_show', ['id' => $client->getId()]);
        }

        $entityManager->remove($client);
        $entityManager->flush();
        $this->addFlash('success', 'Client sans historique supprimé avec succès.');

        return $this->redirectToRoute('app_client_index');
    }
}

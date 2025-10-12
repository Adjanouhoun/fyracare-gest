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

#[Route('/client')]
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

    #[Route('/{id}', name: 'app_client_delete', methods: ['POST'])]
    public function delete(Request $request, Client $client, EntityManagerInterface $entityManager): Response
    {
    if ($this->isCsrfTokenValid('delete'.$client->getId(), $request->request->get('_token'))) {
        $entityManager->remove($client);
        $entityManager->flush();
        $this->addFlash('danger', '🗑️ Le client a bien été <strong>supprimé</strong>.');
    } else {
        $this->addFlash('warning', '⚠️ Jeton CSRF invalide, suppression annulée.');
    }
    return $this->redirectToRoute('app_client_index');
}
}

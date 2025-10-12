<?php

namespace App\Controller;

use App\Entity\CashMovement;
use App\Form\DepositType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{Request, Response};
use Symfony\Component\Routing\Annotation\Route;

#[Route('/cash', name: 'cash_')]
class CashDepositController extends AbstractController
{
    #[Route('/deposit', name: 'deposit', methods: ['GET','POST'])]
    public function deposit(Request $request, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(DepositType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var array $data */
            $data = $form->getData();

            $m = new CashMovement();
            $m->setType(CashMovement::IN);
            $m->setAmount((int) round($data['amount']));
            $m->setSource($data['source']);
            $m->setNotes($data['notes']);
            // Date imposée par le système
            $m->setCreatedAt(new \DateTimeImmutable());

            if ($this->getUser() && method_exists($m, 'setCreatedBy')) {
                $m->setCreatedBy($this->getUser());
            }

            $em->persist($m);
            $em->flush();

            $this->addFlash('success', 'Dépôt enregistré 👍');
            return $this->redirectToRoute('cash_index', [
                'd' => $m->getCreatedAt()->format('Y-m-d'),
            ]);
        }

        if ($form->isSubmitted() && !$form->isValid()) {
            $this->addFlash('danger', 'Veuillez corriger les erreurs du formulaire.');
        }

        return $this->render('cash/deposit.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/deposit/{id}/edit', name: 'deposit_edit', methods: ['GET','POST'])]
    public function edit(CashMovement $movement, Request $request, EntityManagerInterface $em): Response
    {
        if ($movement->getType() !== CashMovement::IN) {
            throw $this->createNotFoundException();
        }

        // Pré-remplissage (pas de date dans le form)
        $defaults = [
            'amount' => $movement->getAmount(),
            'source' => $movement->getSource(),
            'notes'  => $movement->getNotes(),
        ];

        $form = $this->createForm(DepositType::class, $defaults);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var array $data */
            $data = $form->getData();

            $movement->setAmount((int) round($data['amount']));
            $movement->setSource($data['source']);
            $movement->setNotes($data['notes']);

            if ($this->getUser() && method_exists($movement, 'setUpdatedBy')) {
                $movement->setUpdatedBy($this->getUser());
            }

            // On ne modifie jamais createdAt
            $em->flush();

            $this->addFlash('success', 'Dépôt mis à jour ✅');
            return $this->redirectToRoute('cash_index', [
                'd' => $movement->getCreatedAt()->format('Y-m-d'),
            ]);
        }

        if ($form->isSubmitted() && !$form->isValid()) {
            $this->addFlash('danger', 'Veuillez corriger les erreurs du formulaire.');
        }

        return $this->render('cash/deposit_edit.html.twig', [
            'form'     => $form->createView(),
            'movement' => $movement,
        ]);
    }

    #[Route('/deposit/{id}/delete', name: 'deposit_delete', methods: ['POST'])]
    public function delete(CashMovement $movement, Request $request, EntityManagerInterface $em): Response
    {
        if ($movement->getType() !== CashMovement::IN) {
            throw $this->createNotFoundException();
        }

        $token = $request->request->get('_token');
        if (!$this->isCsrfTokenValid('delete_deposit_'.$movement->getId(), $token)) {
            $this->addFlash('danger', 'Jeton de sécurité invalide.');
            return $this->redirectToRoute('cash_index');
        }

        // Suppression DURE
        $em->remove($movement);
        $em->flush();

        $this->addFlash('success', 'Dépôt supprimé 🗑️');
        return $this->redirectToRoute('cash_index');
    }
}

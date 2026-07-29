<?php

namespace App\Controller;

use App\Entity\CashMovement;
use App\Entity\User;
use App\Form\DepositType;
use App\Service\CashMovementCancellationService;
use App\Service\CashPeriodGuard;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{Request, Response};
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/cash', name: 'cash_')]
#[IsGranted('ROLE_MANAGER')]
class CashDepositController extends AbstractController
{
    #[Route('/deposit', name: 'deposit', methods: ['GET','POST'])]
    public function deposit(Request $request, EntityManagerInterface $em, CashPeriodGuard $periodGuard): Response
    {
        $form = $this->createForm(DepositType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $periodGuard->assertOpen(new \DateTimeImmutable());
            } catch (\DomainException $exception) {
                $this->addFlash('danger', $exception->getMessage());
                return $this->redirectToRoute('cash_index');
            }
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

            $this->addFlash('success', 'Apport physique enregistré.');
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

    #[Route('/deposit/{id}/cancel', name: 'deposit_cancel', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function cancel(
        CashMovement $movement,
        Request $request,
        CashMovementCancellationService $cancellation,
    ): Response
    {
        if ($movement->getType() !== CashMovement::IN) {
            throw $this->createNotFoundException();
        }

        $token = $request->request->get('_token');
        if (!$this->isCsrfTokenValid('cancel_deposit_'.$movement->getId(), $token)) {
            throw $this->createAccessDeniedException('Jeton de sécurité invalide.');
        }

        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        try {
            $cancellation->cancel($movement, $user, $request->request->getString('reason'));
            $this->addFlash('success', 'Injection annulée. L’historique est conservé.');
        } catch (\DomainException $exception) {
            $this->addFlash('danger', $exception->getMessage());
        }
        return $this->redirectToRoute('cash_index');
    }
}

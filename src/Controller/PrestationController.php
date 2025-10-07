<?php

namespace App\Controller;

use App\Entity\Prestation;
use App\Form\PrestationType;
use App\Repository\PrestationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Component\HttpFoundation\JsonResponse;

#[Route('/prestation')]
class PrestationController extends AbstractController
{
    #[Route('/', name: 'app_prestation_index', methods: ['GET'])]
    public function index(
        Request $request,
        PrestationRepository $repo,
        PaginatorInterface $paginator
    ): Response {
        $q = $request->query->get('q', '');
        $status = $request->query->get('status'); // active|inactive|null
        $qb = $repo->searchQb($q, $status);

        $pagination = $paginator->paginate(
            $qb,
            $request->query->getInt('page', 1),
            10 // éléments par page
        );

        return $this->render('prestation/index.html.twig', [
            'pagination' => $pagination,
            'q' => $q,
            'status' => $status,
        ]);
    }

    #[Route('/{id}/toggle', name: 'app_prestation_toggle', methods: ['POST'])]
    public function toggle(Request $request, Prestation $prestation, EntityManagerInterface $em): JsonResponse
    {
        $token = $request->request->get('_token');
        if (!$this->isCsrfTokenValid('toggle'.$prestation->getId(), $token)) {
            return new JsonResponse(['ok' => false, 'message' => 'CSRF invalide'], 400);
        }

        $prestation->setIsActive(!$prestation->isActive());
        $em->flush();

        return new JsonResponse([
            'ok' => true,
            'active' => $prestation->isActive(),
            'id' => $prestation->getId(),
        ]);
    }


    #[Route('/new', name: 'app_prestation_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $prestation = new Prestation();
        // Valeur par défaut active
        $prestation->setIsActive(true);

        $form = $this->createForm(PrestationType::class, $prestation);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($prestation);
            $em->flush();
            $this->addFlash('success', '✅ La prestation a bien été <strong>créée</strong>.');

            return $this->redirectToRoute('app_prestation_index');
        }

        return $this->render('prestation/new.html.twig', [
            'prestation' => $prestation,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_prestation_show', methods: ['GET'])]
    public function show(Prestation $prestation): Response
    {
        return $this->render('prestation/show.html.twig', [
            'prestation' => $prestation,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_prestation_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Prestation $prestation, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(PrestationType::class, $prestation);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', '✏️ La prestation a bien été <strong>modifiée</strong>.');

            return $this->redirectToRoute('app_prestation_show', ['id' => $prestation->getId()]);
        }

        return $this->render('prestation/edit.html.twig', [
            'prestation' => $prestation,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_prestation_delete', methods: ['POST'])]
    public function delete(Request $request, Prestation $prestation, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete'.$prestation->getId(), $request->request->get('_token'))) {
            $em->remove($prestation);
            $em->flush();
            $this->addFlash('danger', '🗑️ La prestation a bien été <strong>supprimée</strong>.');
        } else {
            $this->addFlash('warning', '⚠️ Jeton CSRF invalide, suppression annulée.');
        }

        return $this->redirectToRoute('app_prestation_index');
    }
}

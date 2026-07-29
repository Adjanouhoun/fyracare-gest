<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
final class GuideController extends AbstractController
{
    #[Route('/guide', name: 'app_guide', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('guide/index.html.twig');
    }
}

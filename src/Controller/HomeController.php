<?php
// src/Controller/HomeController.php
namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    public function index(): Response
    {
        // Si connecté, aller au dashboard
        if ($this->getUser()) {
            return $this->redirectToRoute('admin_dashboard');
        }

        // Sinon, aller au login
        return $this->redirectToRoute('app_login');
    }
}

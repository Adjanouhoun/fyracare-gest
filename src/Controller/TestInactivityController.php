<?php
// src/Controller/TestInactivityController.php
namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class TestInactivityController extends AbstractController
{
    #[Route('/test-inactivity', name: 'app_test_inactivity')]
    #[IsGranted('ROLE_USER')]
    public function test(): Response
    {
        $session = $this->container->get('request_stack')->getSession();
        $lastActivity = $session->get('app_last_activity');
        $locked = $session->get('app_locked');
        
        return $this->render('test/inactivity.html.twig', [
            'last_activity' => $lastActivity ? date('Y-m-d H:i:s', $lastActivity) : 'Non défini',
            'last_activity_timestamp' => $lastActivity,
            'current_timestamp' => time(),
            'duration' => $lastActivity ? (time() - $lastActivity) : 0,
            'locked' => $locked,
            'timeout' => $_ENV['APP_INACTIVITY_SECONDS'] ?? 60
        ]);
    }
}

<?php
// src/Controller/LockScreenController.php
namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class LockController extends AbstractController
{
    #[Route('/lock', name: 'app_lock_screen')]
    public function lock(): Response
    {
        $user = $this->getUser();
        
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        return $this->render('security/lock.html.twig', [
            'user' => $user
        ]);
    }

    #[Route('/lock/unlock', name: 'app_lock_unlock', methods: ['POST'])]
    public function unlock(
        Request $request,
        UserPasswordHasherInterface $passwordHasher
    ): Response {
        $user = $this->getUser();
        
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        $session = $request->getSession();
        $password = $request->request->get('password');

        // Vérifier le mot de passe
        if (!$passwordHasher->isPasswordValid($user, $password)) {
            $this->addFlash('error', 'Mot de passe incorrect');
            return $this->redirectToRoute('app_lock_screen');
        }

        // Déverrouiller la session
        $session->remove('app_locked');
        $session->set('app_last_activity', time());

        // Récupérer la page cible ou rediriger vers le dashboard
        $targetPath = $session->get('app_lock_target', $this->generateUrl('admin_dashboard'));
        $session->remove('app_lock_target');

        $this->addFlash('success', 'Session déverrouillée avec succès');
        
        return $this->redirect($targetPath);
    }
}
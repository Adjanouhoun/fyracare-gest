<?php
namespace App\Controller;

use App\Entity\User; // ← ton entité
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;

final class LockController extends AbstractController
{
    #[Route('/lock', name: 'app_lock_screen', methods: ['GET'])]
    public function screen(): Response
    {
        return $this->render('security/lock.html.twig');
    }

    #[Route('/lock/unlock', name: 'app_lock_unlock', methods: ['POST'])]
    public function unlock(
        Request $request,
        Security $security,
        UserPasswordHasherInterface $hasher
    ): Response {
        $session = $request->getSession();

        /** @var PasswordAuthenticatedUserInterface|User|null $user */
        $user = $security->getUser();

        // Si plus d’utilisateur (session sécurité expirée) → retour login
        if (!$user instanceof PasswordAuthenticatedUserInterface) {
            return $this->redirectToRoute('app_login');
        }

        $password = (string) $request->request->get('password', '');
        if ($password === '') {
            $this->addFlash('danger', 'Veuillez saisir votre mot de passe.');
            return $this->redirectToRoute('app_lock_screen');
        }

        // Vérification du mot de passe
        if (!$hasher->isPasswordValid($user, $password)) {
            $this->addFlash('danger', 'Mot de passe incorrect.');
            return $this->redirectToRoute('app_lock_screen');
        }

        // OK → déverrouiller et rediriger
        $session->set('app_locked', false);
        $session->set('app_last_activity', time());

        $target = $session->get('app_lock_target') ?: 'admin_dashboard';
        $session->remove('app_lock_target');

        // Si $target est une URL absolue/relative, on redirige dessus, sinon sur la route dashboard
        if (is_string($target) && str_starts_with($target, '/')) {
            return $this->redirect($target);
        }

        return $this->redirectToRoute('admin_dashboard');
    }
}

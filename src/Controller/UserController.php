<?php
// src/Controller/UserController.php
namespace App\Controller;

use App\Entity\User;
use App\Form\UserType;
use App\Form\ChangeUserPasswordType;
use App\Repository\UserRepository;
use App\Service\UserAccountManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/user', name: 'app_user_')]
class UserController extends AbstractController
{
    #[Route('/', name: 'index', methods: ['GET'])]
    public function index(UserRepository $repo): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        return $this->render('user/index.html.twig', [
            'users' => $repo->findBy([], ['fullname' => 'ASC']),
        ]);
    }

    #[Route('/new', name: 'new', methods: ['GET','POST'])]
    public function new(
        Request $request,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $hasher,
        UserAccountManager $accounts,
    ): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $user = new User();
        $form = $this->createForm(UserType::class, $user, ['mode' => 'create']);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $plain = $form->get('plainPassword')->getData();
            $user->setPassword($hasher->hashPassword($user, $plain));
            $em->persist($user);
            if ($form->get('active')->getData()) {
                $em->flush();
            } else {
                $author = $this->getUser();
                if (!$author instanceof User) {
                    throw $this->createAccessDeniedException();
                }
                $em->flush();
                $accounts->deactivate($user, $author);
            }

            $this->addFlash('success', 'Utilisateur créé.');
            return $this->redirectToRoute('app_user_show', ['id' => $user->getId()]);
        }

        return $this->render('user/new.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(User $user): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        return $this->render('user/show.html.twig', [
            'user' => $user,
        ]);
    }

    #[Route('/{id}/edit', name: 'edit', methods: ['GET','POST'])]
    public function edit(
        Request $request,
        User $user,
        EntityManagerInterface $em,
        UserAccountManager $accounts,
    ): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $wasActive = $user->isActive();
        $wasAdmin = in_array('ROLE_ADMIN', $user->getRoles(), true);
        $form = $this->createForm(UserType::class, $user, ['mode' => 'edit']);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $author = $this->getUser();
            if (!$author instanceof User) {
                throw $this->createAccessDeniedException();
            }
            $willBeAdmin = in_array('ROLE_ADMIN', $user->getRoles(), true);
            if ($user === $author && $wasAdmin && !$willBeAdmin) {
                $this->addFlash('danger', 'Vous ne pouvez pas retirer votre propre rôle administrateur.');
                return $this->redirectToRoute('app_user_edit', ['id' => $user->getId()]);
            }

            $shouldBeActive = (bool) $form->get('active')->getData();
            try {
                if ($wasActive && !$shouldBeActive) {
                    $accounts->deactivate($user, $author);
                } elseif (!$wasActive && $shouldBeActive) {
                    $accounts->reactivate($user);
                } else {
                    $em->flush();
                }
            } catch (\DomainException $exception) {
                $this->addFlash('danger', $exception->getMessage());
                return $this->redirectToRoute('app_user_edit', ['id' => $user->getId()]);
            }
            $this->addFlash('success', 'Utilisateur mis à jour.');
            return $this->redirectToRoute('app_user_show', ['id' => $user->getId()]);
        }

        return $this->render('user/edit.html.twig', [
            'form' => $form,
            'user' => $user,
        ]);
    }

    
     

    #[Route('/{id}/deactivate', name: 'deactivate', methods: ['POST'])]
    public function deactivate(Request $request, User $user, UserAccountManager $accounts): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        if (!$this->isCsrfTokenValid('deactivate_user_'.$user->getId(), $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Jeton de sécurité invalide.');
        }

        $author = $this->getUser();
        if (!$author instanceof User) {
            throw $this->createAccessDeniedException();
        }

        try {
            $accounts->deactivate($user, $author);
            $this->addFlash('success', 'Compte désactivé. Son historique est conservé.');
        } catch (\DomainException $exception) {
            $this->addFlash('danger', $exception->getMessage());
        }

        return $this->redirectToRoute('app_user_index');
    }

    #[Route('/{id}/reactivate', name: 'reactivate', methods: ['POST'])]
    public function reactivate(Request $request, User $user, UserAccountManager $accounts): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        if (!$this->isCsrfTokenValid('reactivate_user_'.$user->getId(), $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Jeton de sécurité invalide.');
        }

        try {
            $accounts->reactivate($user);
            $this->addFlash('success', 'Compte réactivé.');
        } catch (\DomainException $exception) {
            $this->addFlash('danger', $exception->getMessage());
        }

        return $this->redirectToRoute('app_user_index');
    }
    #[Route('/{id}/password', name: 'password', methods: ['GET','POST'])]
    public function password(Request $request, User $user, EntityManagerInterface $em, UserPasswordHasherInterface $hasher): Response {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $form = $this->createForm(ChangeUserPasswordType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            if ($form->isValid()) {
                $plain = $form->get('plainPassword')->getData();
                $user->setPassword($hasher->hashPassword($user, $plain));
                $em->flush();

                $this->addFlash('success', 'Mot de passe mis à jour.');
                return $this->redirectToRoute('app_user_show', ['id' => $user->getId()]);
            }

            // Ajout de messages clairs selon les erreurs rencontrées
            if ($form->get('currentPassword')->getErrors(true)->count() > 0) {
                $this->addFlash('danger', 'Mot de passe actuel incorrect.');
            }
            if ($form->get('plainPassword')->getErrors(true)->count() > 0) {
                // ça couvre notamment l’"invalid_message" (non identiques) et la longueur minimale
                foreach ($form->get('plainPassword')->getErrors(true) as $err) {
                    $this->addFlash('danger', $err->getMessage());
                }
            }
        }

        return $this->render('user/password.html.twig', [
            'form' => $form,
            'user' => $user,
        ]);
    }
}

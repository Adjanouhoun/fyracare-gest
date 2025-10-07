<?php
// src/Controller/UserController.php
namespace App\Controller;

use App\Entity\User;
use App\Form\UserType;
use App\Form\ChangeUserPasswordType;
use App\Repository\UserRepository;
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
    public function new(Request $request, EntityManagerInterface $em, UserPasswordHasherInterface $hasher): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $user = new User();
        $form = $this->createForm(UserType::class, $user, ['mode' => 'create']);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $plain = $form->get('plainPassword')->getData();
            $user->setPassword($hasher->hashPassword($user, $plain));
            $em->persist($user);
            $em->flush();

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
    public function edit(Request $request, User $user, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $form = $this->createForm(UserType::class, $user, ['mode' => 'edit']);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'Utilisateur mis à jour.');
            return $this->redirectToRoute('app_user_show', ['id' => $user->getId()]);
        }

        return $this->render('user/edit.html.twig', [
            'form' => $form,
            'user' => $user,
        ]);
    }

    
     

    #[Route('/{id}/delete', name: 'delete', methods: ['POST'])]
    public function delete(Request $request, User $user, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        if ($this->isCsrfTokenValid('delete_user_'.$user->getId(), $request->request->get('_token'))) {
            $em->remove($user);
            $em->flush();
            $this->addFlash('success', 'Utilisateur supprimé.');
        }
        return $this->redirectToRoute('app_user_index');
    }
    #[Route('/{id}/password', name: 'password', methods: ['GET','POST'])]
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

<?php
// src/Controller/ProfileController.php
namespace App\Controller;

use App\Form\ProfileType;
use App\Form\ChangePasswordType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/account')]
class ProfileController extends AbstractController
{
    #[Route('', name: 'account_profile', methods: ['GET','POST'])]
    public function profile(Request $req, EntityManagerInterface $em): Response
    {
        $user = $this->getUser();
        if (!$user) { return $this->redirectToRoute('app_login'); }

        $form = $this->createForm(ProfileType::class, $user);
        $form->handleRequest($req);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'Profil mis à jour.');
        }

        return $this->render('profile/index.html.twig', ['form' => $form]);
    }

    #[Route('/password', name: 'account_password', methods: ['GET','POST'])]
    public function password(
        Request $req,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $hasher
    ): Response {
        $user = $this->getUser();
        if (!$user) { return $this->redirectToRoute('app_login'); }

        $form = $this->createForm(ChangePasswordType::class);
        $form->handleRequest($req);

        if ($form->isSubmitted() && $form->isValid()) {
            $new = $form->get('new_password')->getData();
            $confirm = $form->get('new_password_confirm')->getData();
            if ($new !== $confirm) {
                $this->addFlash('danger', 'La confirmation ne correspond pas.');
            } else {
                $user->setPassword($hasher->hashPassword($user, $new));
                $em->flush();
                $this->addFlash('success', 'Mot de passe changé avec succès.');
                return $this->redirectToRoute('account_profile');
            }
        }

        return $this->render('profile/password.html.twig', ['form' => $form]);
    }
}

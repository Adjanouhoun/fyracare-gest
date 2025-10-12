<?php

namespace App\Controller;

use App\Entity\CompanySettings;
use App\Form\CompanySettingsType;
use App\Service\SettingsProvider;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\{Request, Response, File\UploadedFile};
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/settings')]
#[IsGranted('ROLE_ADMIN')]
class SettingsController extends AbstractController
{
    #[Route('/', name: 'app_settings_edit', methods: ['GET','POST'])]
    public function edit(
        Request $request,
        EntityManagerInterface $em,
        SettingsProvider $provider
    ): Response {
        $settings = $provider->get();

        $form = $this->createForm(CompanySettingsType::class, $settings);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var UploadedFile|null $logoFile */
            $logoFile = $form->get('logoFile')->getData();
            if ($logoFile) {
                $uploadsDir = $this->getParameter('kernel.project_dir') . '/public/uploads';
                (new Filesystem())->mkdir($uploadsDir);
                $ext = $logoFile->guessExtension() ?: 'png';
                $newName = 'logo-'.date('YmdHis').'.'.$ext;
                $logoFile->move($uploadsDir, $newName);

                // supprime l’ancien si existant
                if ($settings->getLogoPath()) {
                    $old = $this->getParameter('kernel.project_dir') . '/public/' . $settings->getLogoPath();
                    if (is_file($old)) @unlink($old);
                }
                $settings->setLogoPath('uploads/'.$newName);
            }

            $em->flush();
            $this->addFlash('success', 'Paramètres mis à jour.');
            return $this->redirectToRoute('app_settings_edit');
        }

        return $this->render('settings/edit.html.twig', [
            'form' => $form->createView(),
            'settings' => $settings,
        ]);
    }
}

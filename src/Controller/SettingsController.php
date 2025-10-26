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
            /** @var UploadedFile|null $logoInvoiceFile */
            $logoInvoiceFile = $form->get('logoInvoiceFile')->getData();
            if ($logoInvoiceFile) {
                $uploadsDir = $this->getParameter('kernel.project_dir') . '/public/uploads';
                (new Filesystem())->mkdir($uploadsDir);
                $ext = $logoInvoiceFile->guessExtension() ?: 'png';
                $newName = 'logoInvoice-'.date('YmdHis').'.'.$ext;
                $logoInvoiceFile->move($uploadsDir, $newName);

                // supprime l’ancien si existant
                if ($settings->getLogoInvoicePath()) {
                    $old = $this->getParameter('kernel.project_dir') . '/public/' . $settings->getLogoInvoicePath();
                    if (is_file($old)) @unlink($old);
                }
                $settings->setLogoInvoicePath('uploads/'.$newName);

            }
            /** @var UploadedFile|null $logoAppFile */
            $logoAppFile = $form->get('logoAppFile')->getData();
            if ($logoAppFile) {
                $uploadsDir = $this->getParameter('kernel.project_dir') . '/public/uploads';
                (new Filesystem())->mkdir($uploadsDir);
                $ext = $logoAppFile->guessExtension() ?: 'png';
                $newName = 'logoApp-'.date('YmdHis').'.'.$ext;
                $logoAppFile->move($uploadsDir, $newName);

                // supprime l’ancien si existant
                if ($settings->getLogoAppPath()) {
                    $old = $this->getParameter('kernel.project_dir') . '/public/' . $settings->getLogoAppPath();
                    if (is_file($old)) @unlink($old);
                }
                $settings->setLogoAppPath('uploads/'.$newName);

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

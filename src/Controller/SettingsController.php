<?php

namespace App\Controller;

use App\Entity\CompanySettings;
use App\Form\CompanySettingsType;
use App\Service\SettingsLogoStorage;
use App\Service\SettingsProvider;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
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
        SettingsProvider $provider,
        SettingsLogoStorage $logoStorage,
    ): Response {
        $settings = $provider->get();

        $form = $this->createForm(CompanySettingsType::class, $settings);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var UploadedFile|null $logoInvoiceFile */
            $logoInvoiceFile = $form->get('logoInvoiceFile')->getData();
            /** @var UploadedFile|null $logoAppFile */
            $logoAppFile = $form->get('logoAppFile')->getData();
            $removeInvoice = (bool) $form->get('removeLogoInvoice')->getData();
            $removeApp = (bool) $form->get('removeLogoApp')->getData();

            $oldInvoicePath = $settings->getLogoInvoicePath();
            $oldAppPath = $settings->getLogoAppPath();
            $newPaths = [];

            try {
                // Un nouveau fichier est prioritaire sur la case « retirer ».
                $invoicePath = $logoInvoiceFile
                    ? $newPaths[] = $logoStorage->store($logoInvoiceFile, 'invoice')
                    : ($removeInvoice ? null : $oldInvoicePath);
                $appPath = $logoAppFile
                    ? $newPaths[] = $logoStorage->store($logoAppFile, 'app')
                    : ($removeApp ? null : $oldAppPath);

                $settings
                    ->setLogoInvoicePath($invoicePath)
                    ->setLogoAppPath($appPath);

                $em->flush();

                if ($invoicePath !== $oldInvoicePath) {
                    $logoStorage->delete($oldInvoicePath);
                }
                if ($appPath !== $oldAppPath) {
                    $logoStorage->delete($oldAppPath);
                }

                $this->addFlash('success', 'Les paramètres et les logos ont été enregistrés.');
                return $this->redirectToRoute('app_settings_edit');
            } catch (\Throwable) {
                foreach ($newPaths as $newPath) {
                    $logoStorage->delete($newPath);
                }
                $settings
                    ->setLogoInvoicePath($oldInvoicePath)
                    ->setLogoAppPath($oldAppPath);
                $this->addFlash(
                    'danger',
                    'L’enregistrement a échoué. Les logos précédents ont été conservés.',
                );
            }
        }

        return $this->render('settings/edit.html.twig', [
            'form' => $form->createView(),
            'settings' => $settings,
            'logoAppPath' => $provider->getLogoAppPath(),
            'logoInvoicePath' => $provider->getLogoInvoicePath(),
            'hasConfiguredLogoApp' => $provider->hasConfiguredLogoApp(),
            'hasConfiguredLogoInvoice' => $provider->hasConfiguredLogoInvoice(),
        ]);
    }
}

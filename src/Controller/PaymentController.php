<?php

namespace App\Controller;

use App\Entity\Payment;
use App\Entity\Rdv;
use App\Entity\User;
use App\Form\PaymentType;
use App\Repository\PaymentRepository;
use App\Service\PaymentProcessor;
use App\Service\CancellationService;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Service\SettingsProvider;
use Dompdf\Dompdf;
use Dompdf\Options;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/payment', name: 'app_payment_')]
#[IsGranted('ROLE_RECEPTION_CAISSE')]
class PaymentController extends AbstractController
{
    #[Route('/', name: 'index', methods: ['GET'])]
    public function index(Request $request, PaymentRepository $repo, PaginatorInterface $paginator): Response
    {
        $q       = trim((string) $request->query->get('q', ''));
        $method  = $request->query->get('method'); // ESPECES / MOBILE

        // Fenêtre "aujourd'hui" en Europe/Paris
        $today  = new \DateTimeImmutable('today', new \DateTimeZone('Europe/Paris'));
        $start  = new \DateTimeImmutable($today->format('Y-m-d').' 00:00:00', new \DateTimeZone('Europe/Paris'));
        $end    = new \DateTimeImmutable($today->format('Y-m-d').' 23:59:59', new \DateTimeZone('Europe/Paris'));

        $qb = $repo->createQueryBuilder('p')
            ->andWhere('p.paidAt BETWEEN :start AND :end')
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->leftJoin('p.rdv', 'r')->addSelect('r')
            ->leftJoin('r.client', 'c')->addSelect('c')
            ->leftJoin('r.prestation', 's')->addSelect('s')
            ->orderBy('p.paidAt', 'DESC');

        if ($q !== '') {
            $qb->andWhere('LOWER(c.nometprenom) LIKE :q OR LOWER(s.libelle) LIKE :q OR LOWER(p.receiptNumber) LIKE :q')
               ->setParameter('q', '%'.mb_strtolower($q).'%');
        }
        if ($method) {
            $qb->andWhere('p.methode = :m')->setParameter('m', $method);
        }

        $pagination = $paginator->paginate(
            $qb,
            $request->query->getInt('page', 1),
            10
        );

        return $this->render('payment/index.html.twig', [
            'today'      => $today,
            'pagination' => $pagination,
            'q'          => $q,
            'method'     => $method,
        ]);
    }

    #[Route('/all', name: 'all', methods: ['GET'])]
    public function all(Request $request, PaymentRepository $repo, PaginatorInterface $paginator): Response
    {
        $q       = trim((string) $request->query->get('q', ''));
        $method  = $request->query->get('method');

        $qb = $repo->createQueryBuilder('p')
            ->leftJoin('p.rdv', 'r')->addSelect('r')
            ->leftJoin('r.client', 'c')->addSelect('c')
            ->leftJoin('r.prestation', 's')->addSelect('s')
            ->orderBy('p.paidAt', 'DESC');

        if ($q !== '') {
            $qb->andWhere('LOWER(c.nometprenom) LIKE :q OR LOWER(s.libelle) LIKE :q OR LOWER(p.receiptNumber) LIKE :q')
               ->setParameter('q', '%'.mb_strtolower($q).'%');
        }
        if ($method) {
            $qb->andWhere('p.methode = :m')->setParameter('m', $method);
        }

        $pagination = $paginator->paginate(
            $qb,
            $request->query->getInt('page', 1),
            15
        );

        return $this->render('payment/all.html.twig', [
            'pagination' => $pagination,
            'q'          => $q,
            'method'     => $method,
        ]);
    }

    #[Route('/new/{rdv}', name: 'new', methods: ['GET','POST'])]
    public function new(Request $request, Rdv $rdv, PaymentProcessor $paymentProcessor): Response
    {
        // Vérifier si le paiement est déjà complet
        if ($rdv->isFullyPaid()) {
            $this->addFlash('warning', 'Ce rendez-vous est déjà entièrement payé.');
            return $this->redirectToRoute('app_rdv_show', ['id' => $rdv->getId()]);
        }

        $payment = new Payment();
        $payment->setRdv($rdv);

        // Passer l'option 'rdv' au form pour afficher le montant restant
        $form = $this->createForm(PaymentType::class, $payment, [
            'rdv' => $rdv,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $user = $this->getUser();
            if (!$user instanceof User) {
                throw $this->createAccessDeniedException();
            }

            try {
                $paymentProcessor->process($payment, $rdv, $user);

                $message = $payment->isDeposit()
                    ? sprintf(
                        'Acompte de %d MRU enregistré. Reste à payer : %d MRU',
                        $payment->getAmount(),
                        $rdv->getRemainingAmount(),
                    )
                    : 'Paiement final enregistré avec succès.';

                $this->addFlash('success', $message);
                return $this->redirectToRoute('app_payment_show', ['id' => $payment->getId()]);
            } catch (\DomainException $exception) {
                $this->addFlash('danger', $exception->getMessage());
            } catch (\Throwable) {
                $this->addFlash(
                    'danger',
                    'Le paiement n’a pas été enregistré. Aucune écriture de caisse n’a été créée.',
                );
            }
        } 

        return $this->render('payment/new.html.twig', [
            'rdv'  => $rdv,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(Payment $payment): Response
    {
        return $this->render('payment/show.html.twig', [
            'payment' => $payment,
            'rdv'     => $payment->getRdv(),
        ]);
    }

    #[Route('/{id}/receipt', name: 'receipt', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function receipt(Payment $payment): Response
    {
        return $this->render('payment/receipt.html.twig', [
            'payment' => $payment,
        ]);
    }

    #[Route('/{id}/cancel', name: 'cancel', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[IsGranted('ROLE_ADMIN')]
    public function cancel(Request $request, Payment $payment, CancellationService $cancellationService): Response
    {
        if (!$this->isCsrfTokenValid('cancel_payment_'.$payment->getId(), $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Jeton de sécurité invalide.');
        }

        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        try {
            $cancellationService->cancelPayment($payment, $user, $request->request->getString('reason'));
            $this->addFlash(
                'success',
                'Paiement annulé. Une contrepassation a été enregistrée en caisse et l’historique est conservé.',
            );
        } catch (\DomainException $exception) {
            $this->addFlash('danger', $exception->getMessage());
        } catch (\Throwable) {
            $this->addFlash('danger', 'L’annulation a échoué. Aucune écriture comptable partielle n’a été créée.');
        }

        return $this->redirectToRoute('app_payment_show', ['id' => $payment->getId()]);
    }

     
    #[Route('/{id<\d+>}/receipt.pdf', name: 'receipt_pdf', methods: ['GET'])]
    public function receiptPdf(Payment $payment, SettingsProvider $settings): Response
    {
        $s = $settings->get();

        // --- construit un Data URI pour le logo (fiable avec Dompdf)
        $logoDataUri = null;
        if ($s->getLogoInvoicePath()) {
            $abs = $this->getParameter('kernel.project_dir').'/public/'.$s->getLogoInvoicePath();
            if (is_file($abs)) {
                $mime = @mime_content_type($abs) ?: 'image/png';
                $b64  = base64_encode(file_get_contents($abs));
                $logoDataUri = 'data:'.$mime.';base64,'.$b64;
            }
        }

        $opts = new Options();
        $opts->set('defaultFont', 'DejaVu Sans');
        $opts->set('isRemoteEnabled', true);      // si jamais tu gardes des URLs absolues
        $opts->set('isHtml5ParserEnabled', true);

        $dompdf = new Dompdf($opts);

        $html = $this->renderView('payment/receipt_pdf.html.twig', [
            'payment'     => $payment,
            'settings'    => $s,
            'logoDataUri' => $logoDataUri, // <<< passe la Data URI
        ]);

        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A5', 'portrait'); 
        $dompdf->render();

        return new Response($dompdf->output(), 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="recu-'.$payment->getReceiptNumber().'.pdf"',
        ]);
    }
}

<?php

namespace App\Controller;

use App\Entity\Payment;
use App\Entity\Rdv;
use App\Form\PaymentType;
use App\Entity\CashMovement;
use App\Repository\PaymentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/payment', name: 'app_payment_')]
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
    public function new(Request $request, Rdv $rdv, EntityManagerInterface $em): Response
    {
        // Un seul paiement total par RDV
        if ($rdv->getPayments()->count() > 0) {
            $this->addFlash('warning', 'Ce rendez-vous est déjà payé.');
            return $this->redirectToRoute('app_rdv_show', ['id' => $rdv->getId()]);
        }

        $payment = new Payment();
        $payment->setRdv($rdv);

        // ⚠️ Passe l’option 'rdv' au form pour pré-remplir le montant
        $form = $this->createForm(PaymentType::class, $payment, [
            'rdv' => $rdv,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Sécurité serveur
            $payment->setAmount((int) $rdv->getPrestation()->getPrix());
            $payment->setPaidAt(new \DateTimeImmutable());

            $em->persist($payment);
            $rdv->setStatus(Rdv::S_HONORE);

            // 1) Premier flush pour obtenir l'ID
            //$em->flush();

            // 2) Générer le numéro de reçu = date du jour + ID
            //    Format exemple: 20251006-123
            $todayStr = (new \DateTimeImmutable())->format('dmY');
            $payment->setReceiptNumber(sprintf('%s-%d', $todayStr, $payment->getId()));

            // 3) Second flush pour enregistrer le reçu
            //$em->flush();

        $mv = new CashMovement();
        $mv->setType(CashMovement::IN)
        ->setAmount($payment->getAmount())
        ->setSource(CashMovement::SRC_PAYMENT)
        ->setNotes('Paiement RDV #'.$rdv->getId().' – '.$rdv->getPrestation()->getLibelle());
        $em->persist($mv);
            $em->flush();

            $this->addFlash('success', 'Paiement enregistré avec succès.');
            return $this->redirectToRoute('app_payment_show', ['id' => $payment->getId()]);
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

    #[Route('/{id}/delete', name: 'delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(Request $request, Payment $payment, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete_payment_'.$payment->getId(), $request->request->get('_token'))) {
            $em->remove($payment);
            $em->flush();
        }
        return $this->redirectToRoute('app_payment_index');
    }
}

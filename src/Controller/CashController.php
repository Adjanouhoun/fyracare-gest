<?php
namespace App\Controller;

use App\Service\CashRegisterService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Response;

#[Route('/admin/cash')]
class CashController extends AbstractController
{
    #[Route('/close-today', name:'cash_close_today')]
    public function closeToday(CashRegisterService $svc): Response
    {
        $closure = $svc->closeDay(new \DateTimeImmutable('today'));
        $this->addFlash('success', 'Clôture créée pour le '.$closure->getDay()->format('d/m/Y'));
        return $this->redirectToRoute('admin_dashboard');
    }
}

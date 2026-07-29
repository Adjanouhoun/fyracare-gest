<?php

namespace App\Controller;

use App\Service\ActivityLogReader;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/activity-log', name: 'admin_activity_log_')]
#[IsGranted('ROLE_ADMIN')]
final class ActivityLogController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(Request $request, ActivityLogReader $reader): Response
    {
        $category = strtoupper($request->query->getString('category'));
        $query = mb_strtolower(trim($request->query->getString('q')));
        $events = array_filter(
            $reader->recent(),
            static function (array $event) use ($category, $query): bool {
                if ($category !== '' && $event['category'] !== $category) {
                    return false;
                }
                if ($query === '') {
                    return true;
                }
                return str_contains(mb_strtolower(
                    $event['title'].' '.$event['details'].' '.$event['actor'],
                ), $query);
            },
        );

        $page = max(1, $request->query->getInt('page', 1));
        $perPage = 50;
        $total = count($events);

        return $this->render('admin/activity_log/index.html.twig', [
            'events' => array_slice(array_values($events), ($page - 1) * $perPage, $perPage),
            'page' => $page,
            'pageCount' => max(1, (int) ceil($total / $perPage)),
            'total' => $total,
            'category' => $category,
            'q' => $request->query->getString('q'),
        ]);
    }
}

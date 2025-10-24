<?php
// src/Controller/ErrorController.php
namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\ErrorHandler\Exception\FlattenException;
use Psr\Log\LoggerInterface;

class ErrorController extends AbstractController
{
    public function __construct(
        private RequestStack $requestStack,
        private LoggerInterface $logger
    ) {}

    public function show(FlattenException $exception): Response
    {
        $statusCode = $exception->getStatusCode();
        $statusText = $exception->getStatusText();
        
        $request = $this->requestStack->getCurrentRequest();
        
        if (!$request) {
            return new Response('Erreur: pas de requête', 500);
        }
        
        $referer = $request->headers->get('referer');
        $currentUrl = $request->getUri();
        
        // Si le referer est la même page ou n'existe pas
        if ($referer === $currentUrl || !$referer) {
            $referer = $this->generateUrl('admin_dashboard');
        }
        
        // Logger l'erreur
        $this->logger->error('Erreur HTTP ' . $statusCode, [
            'url' => $currentUrl,
            'referer' => $referer,
            'user' => $this->getUser()?->getUserIdentifier() ?? 'anonymous',
            'ip' => $request->getClientIp(),
        ]);
        
        $errorData = $this->getErrorData($statusCode);
        $suggestions = $this->getSuggestions($statusCode);
        
        return $this->render('error/show.html.twig', [
            'status_code' => $statusCode,
            'status_text' => $statusText,
            'error' => $errorData,
            'suggestions' => $suggestions,
            'exception' => $exception,
            'referer' => $referer,
            'show_back_button' => $this->shouldShowBackButton($referer)
        ]);
    }
    
    private function getErrorData(int $statusCode): array
    {
        $errors = [
            403 => [
                'title' => 'Accès Refusé',
                'message' => 'Vous n\'avez pas les permissions nécessaires pour accéder à cette ressource.',
                'icon' => 'ri-lock-line',
                'color' => 'danger'
            ],
            404 => [
                'title' => 'Page Non Trouvée',
                'message' => 'La page que vous recherchez n\'existe pas ou a été déplacée.',
                'icon' => 'ri-search-line',
                'color' => 'warning'
            ],
            500 => [
                'title' => 'Erreur Serveur',
                'message' => 'Une erreur inattendue s\'est produite. Nos équipes ont été notifiées.',
                'icon' => 'ri-error-warning-line',
                'color' => 'danger'
            ]
        ];
        
        return $errors[$statusCode] ?? [
            'title' => 'Erreur',
            'message' => 'Une erreur est survenue.',
            'icon' => 'ri-alert-line',
            'color' => 'secondary'
        ];
    }
    
    private function getSuggestions(int $statusCode): array
    {
        $user = $this->getUser();
        
        if ($statusCode === 403) {
            if (!$user) {
                return [
                    [
                        'text' => 'Se connecter',
                        'route' => 'app_login',
                        'icon' => 'ri-login-box-line',
                        'class' => 'btn-primary'
                    ]
                ];
            }
            
            return [
                [
                    'text' => 'Tableau de bord',
                    'route' => 'admin_dashboard',
                    'icon' => 'ri-dashboard-line',
                    'class' => 'btn-primary'
                ]
            ];
        }
        
        return [
            [
                'text' => 'Accueil',
                'route' => 'admin_dashboard',
                'icon' => 'ri-home-4-line',
                'class' => 'btn-primary'
            ]
        ];
    }
    
    private function shouldShowBackButton(string $referer): bool
    {
        $excludedPaths = ['/login', '/logout', '/error'];
        
        foreach ($excludedPaths as $path) {
            if (str_contains($referer, $path)) {
                return false;
            }
        }
        
        return true;
    }
}

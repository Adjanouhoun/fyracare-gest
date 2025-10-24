<?php
// src/EventSubscriber/InactivitySubscriber.php
namespace App\EventSubscriber;

use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class InactivitySubscriber implements EventSubscriberInterface
{
    private const SESSION_KEY_LAST_ACTIVITY = 'app_last_activity';
    private const SESSION_KEY_LOCKED = 'app_locked';
    private const SESSION_KEY_TARGET = 'app_lock_target';

    private int $timeout;
    private int $warningTime;
    private bool $enabled;
    private bool $logoutMode;

    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
        private Security $security,
        private LoggerInterface $logger,
        int $timeout = 60,
        int $warningTime = 10,
        bool $enabled = true,
        bool $logoutMode = false,
        array $timeoutByRole = []
    ) {
        // Récupérer les valeurs depuis .env avec fallback
        $this->timeout = (int) ($_ENV['APP_INACTIVITY_SECONDS'] ?? $timeout);
        $this->warningTime = (int) ($_ENV['APP_INACTIVITY_WARNING'] ?? $warningTime);
        $this->enabled = filter_var($_ENV['APP_INACTIVITY_ENABLED'] ?? $enabled, FILTER_VALIDATE_BOOLEAN);
        $this->logoutMode = filter_var($_ENV['APP_INACTIVITY_LOGOUT_MODE'] ?? $logoutMode, FILTER_VALIDATE_BOOLEAN);

        // Log de démarrage
        $this->logger->info('[INACTIVITY] Subscriber initialisé', [
            'timeout' => $this->timeout,
            'warning' => $this->warningTime,
            'enabled' => $this->enabled,
            'logout_mode' => $this->logoutMode
        ]);
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', -10]
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        // Système désactivé
        if (!$this->enabled) {
            $this->logger->debug('[INACTIVITY] Système désactivé');
            return;
        }

        // Uniquement requêtes principales
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $session = $request->getSession();

        if (!$session) {
            $this->logger->warning('[INACTIVITY] Pas de session disponible');
            return;
        }

        $path = $request->getPathInfo();

        // Log en mode dev
        if ($_ENV['APP_ENV'] === 'dev') {
            $this->logger->debug('[INACTIVITY] Requête détectée', [
                'path' => $path,
                'is_main' => $event->isMainRequest(),
                'method' => $request->getMethod()
            ]);
        }

        // Routes à ignorer
        if ($this->shouldIgnoreRoute($path)) {
            $this->logger->debug('[INACTIVITY] Route ignorée', ['path' => $path]);
            return;
        }

        $user = $this->security->getUser();

        // Pas d'utilisateur connecté
        if (!$user) {
            $this->logger->debug('[INACTIVITY] Pas d\'utilisateur connecté');
            return;
        }

        $this->logger->info('[INACTIVITY] Vérification pour utilisateur', [
            'user' => $user->getUserIdentifier(),
            'path' => $path
        ]);

        // Déjà verrouillé
        if ($session->get(self::SESSION_KEY_LOCKED) === true) {
            $this->logger->info('[INACTIVITY] Session déjà verrouillée, redirection');
            $lockRoute = $this->logoutMode ? 'app_login' : 'app_lock_screen';
            $event->setResponse(new RedirectResponse($this->urlGenerator->generate($lockRoute)));
            return;
        }

        $now = time();
        $lastActivity = (int) ($session->get(self::SESSION_KEY_LAST_ACTIVITY) ?? 0);

        // Première visite
        if ($lastActivity === 0) {
            $this->logger->info('[INACTIVITY] Initialisation du timer', [
                'user' => $user->getUserIdentifier(),
                'timestamp' => $now
            ]);
            $session->set(self::SESSION_KEY_LAST_ACTIVITY, $now);
            return;
        }

        $inactivityDuration = $now - $lastActivity;

        $this->logger->info('[INACTIVITY] Vérification inactivité', [
            'user' => $user->getUserIdentifier(),
            'last_activity' => date('Y-m-d H:i:s', $lastActivity),
            'now' => date('Y-m-d H:i:s', $now),
            'duration' => $inactivityDuration . 's',
            'timeout' => $this->timeout . 's',
            'remaining' => ($this->timeout - $inactivityDuration) . 's'
        ]);

        // ⚠️ INACTIVITÉ DÉPASSÉE
        if ($inactivityDuration > $this->timeout) {
            $this->logger->warning('[INACTIVITY] ⚠️ TIMEOUT DÉPASSÉ - Verrouillage de la session', [
                'user' => $user->getUserIdentifier(),
                'duration' => $inactivityDuration . 's',
                'timeout' => $this->timeout . 's'
            ]);

            $this->handleInactivity($event, $session, $request, $user);
            return;
        }

        // Avertissement
        if ($this->shouldShowWarning($inactivityDuration, $this->timeout)) {
            $remaining = $this->timeout - $inactivityDuration;
            $this->logger->info('[INACTIVITY] Affichage avertissement', [
                'remaining' => $remaining . 's'
            ]);
        }

        // ✅ Actualiser le timer
        $session->set(self::SESSION_KEY_LAST_ACTIVITY, $now);
        $this->logger->debug('[INACTIVITY] Timer actualisé', [
            'user' => $user->getUserIdentifier(),
            'new_timestamp' => $now
        ]);
    }

    private function shouldIgnoreRoute(string $path): bool
    {
        $ignoredPaths = [
            '/lock',
            '/unlock',
            '/login',
            '/logout',
            '/_wdt',
            '/_profiler',
            '/build',
            '/assets',
            '/bundles',
            '/api/',
        ];

        foreach ($ignoredPaths as $ignoredPath) {
            if (str_starts_with($path, $ignoredPath)) {
                return true;
            }
        }

        return false;
    }

    private function shouldShowWarning(int $inactivityDuration, int $userTimeout): bool
    {
        $remainingTime = $userTimeout - $inactivityDuration;
        return $remainingTime <= $this->warningTime && $remainingTime > 0;
    }

    private function handleInactivity(
        RequestEvent $event,
        $session,
        $request,
        $user
    ): void {
        try {
            $session->set(self::SESSION_KEY_TARGET, $request->getRequestUri());

            if ($this->logoutMode) {
                $this->logger->info('[INACTIVITY] Mode logout - Déconnexion');
                $session->invalidate();
                $response = new RedirectResponse($this->urlGenerator->generate('app_login'));
            } else {
                $this->logger->info('[INACTIVITY] Mode lock - Verrouillage');
                $session->set(self::SESSION_KEY_LOCKED, true);
                $lockUrl = $this->urlGenerator->generate('app_lock_screen');
                $response = new RedirectResponse($lockUrl);
            }
            
            $event->setResponse($response);
            
        } catch (\Exception $e) {
            $this->logger->error('[INACTIVITY] Erreur lors du verrouillage', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            // Fallback vers login
            $event->setResponse(new RedirectResponse($this->urlGenerator->generate('app_login')));
        }
    }
}
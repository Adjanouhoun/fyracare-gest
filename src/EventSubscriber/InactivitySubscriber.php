<?php
namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Bundle\SecurityBundle\Security;

final class InactivitySubscriber implements EventSubscriberInterface
{
    private int $timeout;

    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
        private Security $security,
        int $timeout = 10 // 15 min par défaut si APP_INACTIVITY_SECONDS non défini
    ) {
        $env = (int) ($_ENV['APP_INACTIVITY_SECONDS'] ?? 0);
        $this->timeout = $env > 0 ? $env : $timeout;
    }

    public static function getSubscribedEvents(): array
    {
        // priorité > 0 pour être exécuté tôt mais après la résolution de l'user
        return [KernelEvents::REQUEST => ['onKernelRequest', 20]];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) return;

        $request = $event->getRequest();
        $session = $request->getSession();
        if (!$session) return;

        // 1) Ignorer assets, login/logout/lock, profiler
        $path = $request->getPathInfo();
        foreach (['/lock', '/login', '/logout', '/_wdt', '/_profiler', '/build', '/assets', '/bundles'] as $p) {
            if (str_starts_with($path, $p)) return;
        }

        // 2) Ignorer ce qui n'est PAS une page HTML "interactive" (évite XHR qui reset le timer)
        // - requêtes AJAX
        if ($request->isXmlHttpRequest()) return;
        // - requêtes dont l'Accept ne contient pas du HTML
        $accept = (string) $request->headers->get('Accept', '');
        if (stripos($accept, 'text/html') === false) return;
        // - méthodes non GET (POST/PUT/...) n’impactent pas l’inactivité
        if (!in_array($request->getMethod(), ['GET'], true)) return;

        // 3) Pas d’utilisateur connecté → ne rien faire
        if (!$this->security->getUser()) return;

        // 4) Déjà verrouillé → renvoyer sur l’écran de lock
        if ($session->get('app_locked') === true) {
            $event->setResponse(new RedirectResponse($this->urlGenerator->generate('app_lock_screen')));
            return;
        }

        $now  = time();
        $last = (int) ($session->get('app_last_activity') ?? 0);

        // Première requête HTML → init
        if ($last === 0) {
            $session->set('app_last_activity', $now);
            return;
        }

        // 5) Inactivité dépassée → lock
        if (($now - $last) > $this->timeout) {
            $session->set('app_lock_target', $request->getRequestUri());
            $session->set('app_locked', true);
            $event->setResponse(new RedirectResponse($this->urlGenerator->generate('app_lock_screen')));
            return;
        }

        // 6) Actif → rafraîchir le timer
        $session->set('app_last_activity', $now);
    }
}

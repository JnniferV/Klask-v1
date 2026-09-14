<?php

namespace App\EventSubscriber;

use App\Entity\User;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class SessionTimeoutSubscriber implements EventSubscriberInterface
{
    // 4h Mercure
    private const TIMEOUT_SECONDS = 14400;
    private const TIMED_OUT_ROLES = ['ROLE_ADMIN', 'ROLE_ACCOMPANYING'];

    public function __construct(
        private readonly TokenStorageInterface $tokenStorage,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $route = $event->getRequest()->attributes->get('_route');
        if (in_array($route, ['app_login', 'app_logout', '_wdt', '_profiler'], true)) {
            return;
        }

        $user = $this->tokenStorage->getToken()?->getUser();
        if (!$user instanceof User) {
            return;
        }

        $timeout = $this->timeoutFor($user->getRoles());
        if (null === $timeout) {
            return;
        }

        $session = $event->getRequest()->getSession();
        $last = $session->get('klask_last_activity');
        if (null !== $last && time() - $last > $timeout) {
            $event->setResponse(new RedirectResponse($this->urlGenerator->generate('app_logout')));

            return;
        }

        $session->set('klask_last_activity', time());
    }

    /** @param string[] $roles */
    private function timeoutFor(array $roles): ?int
    {
        return array_intersect(self::TIMED_OUT_ROLES, $roles) ? self::TIMEOUT_SECONDS : null;
    }

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::REQUEST => ['onKernelRequest', 0]];
    }
}

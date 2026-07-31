<?php

namespace App\EventSubscriber;

use App\Domain\Service\ApiClientService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpKernel\Event\ResponseEvent;

class RefreshTokenCookieSubscriber implements EventSubscriberInterface
{
    private const RT_COOKIE = 'rt';

    public function __construct(
        private readonly ApiClientService $apiClient
    )
    {
    }

    public function onResponseEvent(ResponseEvent $event): void
    {
        if(!$event->isMainRequest()) {
            return;
        }

        $refreshToken = $this->apiClient->getRefreshToken();
        if(!$refreshToken) {
            return;
        }

        $currentCookie = $event->getRequest()->cookies->get(self::RT_COOKIE); /*
            - On ne met à jour que si le cookie actuel est différent
        */
        if($currentCookie === $refreshToken) {
            return;
        }

        $event->getResponse()->headers->setCookie(
            Cookie::create(self::RT_COOKIE)
                ->withValue($refreshToken)
                ->withExpires(time() + 2592000)
                ->withHttpOnly(true)
                ->withSecure(true)
                ->withSameSite('lax')
                ->withPath('/')
        );
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ResponseEvent::class => 'onResponseEvent'
        ];
    }
}

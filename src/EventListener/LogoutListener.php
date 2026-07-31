<?php

namespace App\EventListener;

use App\Domain\Service\ApiClientService;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Event\LogoutEvent;

final class LogoutListener
{
    public function __construct(
        private ApiClientService $apiClient,
        private UrlGeneratorInterface $urlGenerator
    )
    {
    }

    #[AsEventListener]
    public function onLogoutEvent(LogoutEvent $event): void
    {
        $request = $event->getRequest();
        $refreshToken = $request->getSession()->get('refresh_token') ?? $request->cookies->get('rt'); /*
            - On récupère le 'refresh token' ici car le 'LogoutEvent' est déclenché après que la session a déjà commencé à être invalidée par le firewall
        */
        $this->apiClient->logout($refreshToken);
        $response = new RedirectResponse($this->urlGenerator->generate('app_login'));
        $response->headers->clearCookie(
            'rt',
            '/',
            null,
            true, // Ou.. '$this->params->get('app.env') == 'prod'' et non '$event->getRequest()->isSecure()'..
            true,
            'lax'
        ); /*
            - 'clearCookie' attend les mêmes attributs que lors de la création sinon le navigateur ne reconnaît pas le cookie à supprimer
        */ 
        $event->setResponse($response);
    }
}

<?php

namespace App\Security;

use App\Domain\Service\ApiClientService;
use App\Entity\ApiUser;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractLoginFormAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\CsrfTokenBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\RememberMeBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\PasswordCredentials;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\SecurityRequestAttributes;
use Symfony\Component\Security\Http\Util\TargetPathTrait;

class ApiAuthenticator extends AbstractLoginFormAuthenticator
{
    use TargetPathTrait;

    public const LOGIN_ROUTE = 'app_login';
    private const RT_COOKIE = 'rt';

    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
        private readonly ApiClientService $apiClient
    )
    {
    }

    public function authenticate(Request $request): Passport
    {
        $email = $request->request->get('email', '');
        $password = $request->request->get('password', '');
        $csrf = $request->request->get('_csrf_token', '');

        $request->getSession()->set(SecurityRequestAttributes::LAST_USERNAME, $email);

        return new SelfValidatingPassport(
            new UserBadge($email, function (string $userIdentifier) use ($email, $password) {
                try {
                    $data = $this->apiClient->login($email, $password);
                } catch(\RuntimeException $e) {
                    throw new CustomUserMessageAuthenticationException($e->getMessage());
                }

                return new ApiUser($data['user']); /*
                    - 'login()' a déjà refusé une réponse sans profil : inutile de le revérifier ici
                */
            }),
            [
                new CsrfTokenBadge('authenticate', $csrf),
                new RememberMeBadge() /*
                    - Pour l'utiliser on doit implémenter 'PasswordAuthenticatedUserInterface' dans 'ApiUser'
                */
            ]
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        $response = new RedirectResponse(
            $this->getTargetPath($request->getSession(), $firewallName)
                ?? $this->urlGenerator->generate('tableau_de_bord.index')
        );
        $refreshToken = $this->apiClient->getRefreshToken();
        if($refreshToken) {
            $response->headers->setCookie(
                Cookie::create(self::RT_COOKIE)
                    ->withValue($refreshToken)
                    ->withExpires(time() + 2592000) /*
                        - L.. '2592000' pour '30j'
                    */
                    ->withHttpOnly(true)
                    ->withSecure(true) /*
                        - Ou.. '$this->params->get('app.env') == 'prod'' vu que '$event->getRequest()->isSecure()' peut poser problème derrière un reverse proxy en prod si les 'trusted proxies' ne sont pas configurés 'isSecure()' peut renvoyer 'false' donc cookie de refresh envoyé en clair sur http
                    */
                    ->withSameSite('lax') /*
                        - 'lax' pour un bon équilibre de sécurisé
                    */
                    ->withPath('/')
            );
        }

        return $response;
    }

    protected function getLoginUrl(Request $request): string
    {
        return $this->urlGenerator->generate(self::LOGIN_ROUTE);
    }
}

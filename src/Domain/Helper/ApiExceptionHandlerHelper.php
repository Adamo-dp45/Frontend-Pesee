<?php

namespace App\Domain\Helper;

use App\Security\Exception\ApiException;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\RouterInterface;

class ApiExceptionHandlerHelper
{
    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly RouterInterface $router
    )
    {
    }

    public function handle(ApiException $e, ?FormInterface $form = null, string $fallbackRoute = 'home', array $routeParams = [])
    {
        /**
         * @var Session
         */
        $session = $this->requestStack->getSession(); // Ou.. instanceof FlashBagAwareSessionInterface

        if($e->isNotFound()) {
            throw new NotFoundHttpException($e->getMessage());
        }

        if($e->isServerError()) {
            throw new HttpException(500, $e->getMessage());
        }

        if($e->isForbidden()) { /*
            - Le '403' permet d'indiquer que l'utilisateur est connecté mais pas autorisé
        */
            $session->getFlashBag()->add('danger', $e->getMessage()); /*
                - '$session->migrate(false)' -- Si on veut quand même déconnecté l'utilisateur le 'migrate' garde les données de 'flashes' dans une nouvelle session..
                - return new RedirectResponse($this->router->generate('app_logout'));
            */
            return new RedirectResponse($this->router->generate('app_login'));
        }
        /*
            if($e->isUnauthorized()) { -- Si on veut gérer le '401' mais on l'a fais dans 'ApiClientService'
                throw new AuthenticationExpiredException();
            }
        */
        if($form && $e->isValidationError()) { // Ou.. '$e->hasViolations()' 
            foreach($e->getViolations() as $field => $messages) {
                foreach($messages as $message) {
                    if ($field !== 'global' && $form->has($field)) {
                        $form->get($field)->addError(new FormError($message)); // L'erreur inline sous le champ
                    } else {
                        $form->addError(new FormError($message)); // L'erreur globale en haut du form via 'form_errors(form)'
                    }
                }
            }
            return null;
        }

        $session->getFlashBag()->add('danger', $e->getMessage()); /*
            - L'erreur de validation sans formulaire
        */
        return new RedirectResponse($this->router->generate($fallbackRoute, $routeParams));
    }
}
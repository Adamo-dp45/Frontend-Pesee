<?php

namespace App\Domain\Helper;

use App\Security\Exception\ApiException;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\RouterInterface;

/*
    - Le seul endroit qui décide de ce qu'on fait d'une erreur de l'API. Rend une 'Response' quand la
      suite du contrôleur n'a plus lieu d'être, ou 'null' quand les violations viennent d'être
      reposées sur le formulaire et qu'il faut le réafficher.
*/
class ApiExceptionHandlerHelper
{
    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly RouterInterface $router
    )
    {
    }

    public function handle(ApiException $e, ?FormInterface $form = null, string $routeRepli = 'home', array $parametres = []): ?RedirectResponse
    {
        /** @var Session $session */
        $session = $this->requestStack->getSession();

        if($e->isNotFound()) {
            throw new NotFoundHttpException($e->getMessage()); /*
                - Une ressource hors du périmètre répond 404, pas 403 : elle doit rester indiscernable d'une ressource inexistante, sinon l'écran confirme l'existence de données d'un autre client.
            */
        }

        // Une API en panne n'est pas un bug du site : 502 le dit, et 500 le cacherait
        if($e->isServerError()) {
            throw new HttpException(502, $e->getMessage(), $e);
        }

        /*
            - Un 403 n'est PAS une session perdue. Il dit « vous êtes bien identifié, mais ce geste
              ne vous revient pas » : renvoyer à la connexion faisait perdre la page à quelqu'un de
              déjà connecté, qui se reconnectait pour se heurter au même refus.

            - Le cas se produisait dès qu'un écran chargeait une donnée hors de son périmètre de
              rôle : le super administrateur ouvrant la fiche d'un poste, dont les pesées lui sont
              fermées, se retrouvait déconnecté.

            - La vraie expiration de session ne passe pas par ici : 'ApiClientService' intercepte le
              401, tente le rafraîchissement, et lève 'AuthenticationExpiredException' s'il échoue —
              c'est elle, et elle seule, qui déconnecte.

            - Même traduction que 'ApiExceptionSubscriber', le filet des appels hors 'catch' : le
              même refus de l'API doit produire le même écran, qu'un contrôleur l'ait rattrapé ou non.
        */
        if($e->isForbidden()) {
            throw new AccessDeniedHttpException('Votre rôle ne donne pas accès à cet écran.', $e);
        }

        /*
            - Un conflit est une saisie à corriger, pas un incident : « ce code entreprise est déjà
              utilisé » doit ramener l'utilisateur à son formulaire avec sa saisie intacte. Sans ce
              cas, il repartait sur la liste avec un bandeau rouge et tout à retaper.
            - Le message n'est pas attribué à un champ : l'API ne dit pas lequel, et le deviner sur
              son libellé reviendrait à coupler l'écran à une tournure de phrase.
        */
        if($form && $e->isConflict()) {
            $form->addError(new FormError($e->getMessage()));

            return null;
        }

        if($form && $e->isValidationError()) {
            /*
                - Un 422 ne porte pas toujours des violations par champ : une règle métier refusée ('une demande est déjà en attente', 'solde insuffisant') n'a qu'un message. Sans le cas 'sinon', la boucle tournait à vide, la méthode rendait 'null', et le formulaire se réaffichait SANS RIEN DIRE — le geste semblait n'avoir aucun effet.
            */
            if(!$e->hasViolations()) {
                $form->addError(new FormError($e->getMessage()));

                return null;
            }

            foreach($e->getViolations() as $champ => $messages) {
                foreach($messages as $message) {
                    // Sous le champ concerné quand il existe, en tête du formulaire sinon : une violation muette est pire qu'une erreur brute
                    $champ !== 'global' && $form->has($champ)
                        ? $form->get($champ)->addError(new FormError($message))
                        : $form->addError(new FormError($message));
                }
            }

            return null; // Le contrôleur réaffiche le formulaire, erreurs comprises
        }

        $session->getFlashBag()->add('danger', $e->getMessage());

        return new RedirectResponse($this->router->generate($routeRepli, $parametres));
    }
}

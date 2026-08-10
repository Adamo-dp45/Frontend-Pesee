<?php

namespace App\EventListener;

use App\Security\Exception\ApiException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\KernelEvents;

/*
    - Traduit une erreur de l'API en réponse HTTP du site.

    - Sans lui, chaque contrôleur devait entourer ses lectures d'un try/catch, et celui qui
      l'oubliait affichait une page blanche 500 là où l'API disait simplement « vous n'avez pas
      le droit ». C'est ce qui arrivait au super administrateur sur la caisse : les voters lui
      refusent l'accès — c'est voulu, il gère les clients et pas leur argent — mais le site
      répondait 500 au lieu de le dire.

    - Les erreurs de validation ne passent pas par ici : les contrôleurs les rattrapent eux-mêmes
      pour réafficher le formulaire avec les messages sous les champs.

    - À ne pas confondre avec 'ApiExceptionHandlerHelper', qui ne le remplace pas : le helper est
      APPELÉ, dans un 'catch', là où le contrôleur sait quoi faire de l'erreur — reposer les
      violations sur un formulaire, rediriger. Ce subscriber est le filet des appels qui ne sont
      dans aucun 'catch', et il y en a beaucoup : les écrans de lecture (bilan, tableau de bord,
      pesées, fiches) n'en ont aucun. Vérifié en le neutralisant : '/produits/999999' passe de 404 à
      500, et '/bilan' aussi.
*/
final class ApiExceptionSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::EXCEPTION => 'surException'];
    }

    public function surException(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();

        if (!$exception instanceof ApiException) {
            return;
        }

        $event->setThrowable(match (true) {
            $exception->isForbidden() => new AccessDeniedHttpException(
                'Votre rôle ne donne pas accès à cet écran.',
                $exception
            ),
            $exception->isNotFound() => new NotFoundHttpException($exception->getMessage(), $exception),
            $exception->isValidationError() => $exception,
            // Une API en panne n'est pas un bug du site : 502 le dit, et 500 le cacherait
            default => new HttpException(502, $exception->getMessage(), $exception),
        });
    }
}

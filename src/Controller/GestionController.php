<?php

namespace App\Controller;

use App\Entity\ApiUser;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/*
    - Le socle des écrans de gestion. Rien de métier ici : les trois gestes que chacun d'eux
      répète, et qu'il vaut mieux écrire une fois.
*/
abstract class GestionController extends AbstractController
{
    /* Tous les formulaires de gestion partagent un identifiant de jeton, déclaré dans csrf.yaml. */
    protected const JETON = 'gestion';
    protected const JETON_INVALIDE = 'Votre formulaire a expiré, veuillez réessayer.';

    /*
        - Turbo n'affiche une réponse à un POST que si elle redirige ou porte un statut d'erreur.
          Un formulaire réaffiché en 200 resterait muet.
    */
    protected const EN_ERREUR = Response::HTTP_UNPROCESSABLE_ENTITY;

    protected function jetonValide(Request $request): bool
    {
        return $this->isCsrfTokenValid(self::JETON, (string) $request->request->get('_csrf_token'));
    }

    protected function utilisateur(): ApiUser
    {
        $utilisateur = $this->getUser();
        \assert($utilisateur instanceof ApiUser);

        return $utilisateur;
    }

    /** Une chaîne vidée par l'utilisateur vaut null, pas la chaîne vide. */
    protected function ouNull(mixed $valeur): ?string
    {
        $texte = trim((string) $valeur);

        return $texte === '' ? null : $texte;
    }

    protected function entierOuNull(mixed $valeur): ?int
    {
        $texte = trim((string) $valeur);

        return $texte === '' ? null : (int) $texte;
    }

    /*
        - Les options d'une liste déroulante, indexées par identifiant.

        - Construire cette carte côté Twig ne marche pas : le filtre 'merge' se comporte comme
          'array_merge', qui renumérote les clés entières. Les options prenaient alors les valeurs
          0, 1, 2… au lieu des identifiants, et le formulaire postait un site inexistant.
    */
    /**
     * @param list<array<string, mixed>> $items
     *
     * @return array<int, string>
     */
    protected function options(array $items, string $libelle): array
    {
        $options = [];

        foreach ($items as $item) {
            $options[(int) $item['id']] = (string) $item[$libelle];
        }

        return $options;
    }
}

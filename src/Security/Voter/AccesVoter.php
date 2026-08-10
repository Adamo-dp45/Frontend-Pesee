<?php

namespace App\Security\Voter;

use App\Entity\ApiUser;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/*
    - Le MIROIR d'affichage de 'AccesVoter' côté API : mêmes rôles, mêmes ressources, mêmes actions.
      Il ne protège RIEN — l'API recalcule tout à chaque appel — il évite de proposer des boutons qui
      répondraient 403, et des écrans qui s'ouvrent pour échouer à la validation.

    - Recopier une table, c'est accepter qu'elle diverge, et le compromis est assumé : contrairement à
      Transport, où les permissions sont des lignes en base que '/api/me' renvoie au front, celles-ci
      sont figées par rôle dans le code de l'API. Il n'y a rien à lire. La recopier au même format et
      dans le même ordre est ce qui rend l'écart visible à la lecture.
      ⚠ Toute modification de 'App\Security\Voter\AccesVoter::DROITS' côté API se reporte ICI.

    - Convention d'appel reprise de Transport : 'is_granted('SITE_BLOQUER')', soit 'RESSOURCE_ACTION'.
      La ressource est le nom court de l'entité de l'API en majuscules et à plat ('MouvementCaisse' →
      'MOUVEMENTCAISSE') — c'est le vocabulaire de l'API qu'on garde, pas celui des routes, pour que
      les deux tables se lisent côte à côte.
*/
final class AccesVoter extends Voter
{
    public const VOIR = 'VOIR';
    public const CREER = 'CREER';
    public const MODIFIER = 'MODIFIER';
    public const SUPPRIMER = 'SUPPRIMER';
    public const BLOQUER = 'BLOQUER';
    public const ATTRIBUER = 'ATTRIBUER';
    public const RECHARGER = 'RECHARGER';
    public const TRAITER = 'TRAITER';
    public const PAYER = 'PAYER';
    public const PROMOUVOIR = 'PROMOUVOIR';

    /** Copie conforme de la table de l'API. Le compte machine ('ROLE_SITE') n'y figure pas : il ne se connecte pas au web. */
    private const DROITS = [
        'ROLE_SUPER_ADMIN' => [
            'Entreprise' => [self::VOIR, self::CREER, self::MODIFIER, self::BLOQUER],
            'User' => [self::VOIR, self::CREER, self::MODIFIER, self::SUPPRIMER, self::BLOQUER, self::PROMOUVOIR],
            // Il met les postes en service, donc c'est lui qu'on appelle quand l'un d'eux déraille
            'Site' => [self::VOIR, self::CREER, self::MODIFIER, self::BLOQUER],
        ],
        'ROLE_ADMIN' => [
            'Entreprise' => [self::VOIR, self::MODIFIER, self::RECHARGER],
            'User' => [self::VOIR, self::CREER, self::MODIFIER, self::SUPPRIMER, self::BLOQUER, self::PROMOUVOIR],
            'Site' => [self::VOIR, self::MODIFIER, self::BLOQUER, self::ATTRIBUER],
            'Produit' => [self::VOIR, self::CREER, self::MODIFIER, self::SUPPRIMER],
            'Fournisseur' => [self::VOIR, self::CREER, self::MODIFIER, self::SUPPRIMER],
            'Operation' => [self::VOIR],
            'Paiement' => [self::VOIR, self::PAYER],
            'MouvementCaisse' => [self::VOIR],
            'DemandeSolde' => [self::VOIR, self::TRAITER],
            'SynchronisationLot' => [self::VOIR],
        ],
        'ROLE_AGENT' => [
            'Entreprise' => [self::VOIR],
            'User' => [self::VOIR, self::CREER, self::MODIFIER, self::BLOQUER, self::PROMOUVOIR], // Sur des opérateurs seulement : c'est 'UserGuard' qui borne la cible
            'Site' => [self::VOIR],
            'Produit' => [self::VOIR],
            'Fournisseur' => [self::VOIR],
            'Operation' => [self::VOIR],
            'Paiement' => [self::VOIR],
            'MouvementCaisse' => [self::VOIR],
            'DemandeSolde' => [self::VOIR],
            'SynchronisationLot' => [self::VOIR],
        ],
        'ROLE_OPERATEUR' => [
            'Site' => [self::VOIR],
            'Produit' => [self::VOIR, self::MODIFIER],                  // Il tarifie
            'Fournisseur' => [self::VOIR, self::CREER, self::MODIFIER], // Il fixe les prix spéciaux
            'Operation' => [self::VOIR],
            'Paiement' => [self::VOIR, self::PAYER],
            'MouvementCaisse' => [self::VOIR],
            'DemandeSolde' => [self::VOIR, self::CREER, self::MODIFIER], // Il corrige et annule la sienne ; « tant qu'elle n'est pas traitée » est vérifié par l'API
        ],
    ];

    private const ACTIONS = [
        self::VOIR, self::CREER, self::MODIFIER, self::SUPPRIMER, self::BLOQUER,
        self::ATTRIBUER, self::RECHARGER, self::TRAITER, self::PAYER, self::PROMOUVOIR,
    ];

    /*
        - On reconnaît un attribut à son ACTION, pas à sa forme : 'ROLE_SUPER_ADMIN' et
          'IS_AUTHENTICATED_FULLY' portent eux aussi un tiret bas, et les intercepter ici les
          empêcherait d'atteindre les voters de Symfony.
    */
    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($this->action($attribute), self::ACTIONS, true);
    }

    protected function voteOnAttribute(
        string $attribute,
        mixed $subject,
        TokenInterface $token,
        ?Vote $vote = null
    ): bool
    {
        $user = $token->getUser();

        if(!$user instanceof ApiUser) {
            return false;
        }

        /*
            - Une ressource inconnue — une faute de frappe dans un gabarit — ne trouve aucune action et
              répond non. Le bouton disparaît au lieu de s'afficher à tout le monde : quand une règle
              d'affichage se trompe, mieux vaut qu'elle se voie que qu'elle ouvre.
        */
        return in_array($this->action($attribute), $this->actionsAutorisees($user, $this->ressource($attribute)), true);
    }

    /** @return list<string> */
    private function actionsAutorisees(ApiUser $user, string $ressource): array
    {
        foreach($user->getRoles() as $role) {
            if(!isset(self::DROITS[$role])) {
                continue; // 'ROLE_USER', que tout compte porte, n'est pas un rôle métier
            }

            foreach(self::DROITS[$role] as $nom => $actions) {
                if(strtoupper($nom) === $ressource) {
                    return $actions;
                }
            }

            return []; // Chaque compte porte exactement un rôle métier : le premier trouvé tranche
        }

        return [];
    }

    private function ressource(string $attribute): string
    {
        return explode('_', $attribute, 2)[0];
    }

    private function action(string $attribute): string
    {
        return explode('_', $attribute, 2)[1] ?? '';
    }
}

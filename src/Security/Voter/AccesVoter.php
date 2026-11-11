<?php

namespace App\Security\Voter;

use App\Entity\ApiUser;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/*
    - L'affichage, et rien d'autre. Ce voter ne protège RIEN : l'API refait le calcul complet —
      table des droits, périmètre, hiérarchie des comptes — à chaque appel. Ici on évite seulement
      de proposer des boutons qui répondraient 403, et des écrans qui s'ouvrent pour échouer.

    - Il ne DÉCIDE plus, il LIT. Les droits arrivent de '/api/me' avec le reste du profil et
      dorment en session. Ce fichier tenait jusqu'ici une copie de la table de l'API, à reporter à
      la main à chaque changement — un ⚠ en commentaire le rappelait. Une copie finit par diverger,
      et celle-ci divergeait en silence : un bouton manquant ne lève aucune erreur, il laisse
      seulement croire que le droit n'existe pas.

    - Convention d'appel inchangée : 'is_granted('SITE_BLOQUER')', soit 'RESSOURCE_ACTION'. L'API
      envoie exactement ces chaînes-là, donc il n'y a plus rien à découper ni à traduire ici, donc
      plus aucune occasion de se tromper.

    - Une session ouverte AVANT cette bascule ne porte pas de droits : tout est refusé et les
      boutons disparaissent jusqu'à la reconnexion. C'est le bon sens du refus — un écran trop
      pauvre se signale tout seul, un écran trop permissif se découvre sur un 403.
*/
final class AccesVoter extends Voter
{
    /*
        - On ne reconnaît pas nos attributs à leur forme, on écarte ceux de Symfony : 'ROLE_ADMIN'
          et 'IS_AUTHENTICATED_FULLY' portent eux aussi un tiret bas, et les intercepter ici les
          empêcherait d'atteindre les voters du framework.
        - Tout le reste nous revient, y compris une faute de frappe dans un gabarit : elle ne
          figure dans aucune liste de droits, donc le bouton disparaît. Quand une règle d'affichage
          se trompe, mieux vaut qu'elle se voie que qu'elle ouvre.
    */
    protected function supports(string $attribute, mixed $subject): bool
    {
        return !str_starts_with($attribute, 'ROLE_') && !str_starts_with($attribute, 'IS_');
    }

    protected function voteOnAttribute(
        string $attribute,
        mixed $subject,
        TokenInterface $token,
        ?Vote $vote = null
    ): bool
    {
        $user = $token->getUser();

        return $user instanceof ApiUser && in_array($attribute, $user->getDroits(), true);
    }
}

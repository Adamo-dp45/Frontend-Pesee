<?php

namespace App\Security;

use App\Entity\ApiUser;
use App\Security\Voter\AccesVoter;

/*
    - Le miroir d'affichage de 'App\Security\UserGuard' côté API. 'AccesVoter' dit ce qu'un rôle a le
      droit de faire sur la ressource « User » ; ici on répond à « sur QUI », ce qu'une table de
      droits ne sait pas exprimer.

    - Sans lui, l'écran des utilisateurs proposait « Modifier » sur toutes les lignes, y compris la
      sienne : la fiche s'ouvrait, le formulaire se remplissait, et l'API refusait à l'enregistrement.
      L'utilisateur ne découvrait le mur qu'après avoir saisi.

    - 'motifRefus()' et pas seulement un booléen : quand une ligne n'est pas gérable, l'écran a de quoi
      dire pourquoi. C'est ce qui distingue un bouton absent d'un bouton cassé.
      ⚠ Toute modification du guard de l'API se reporte ICI.
*/
final class UserGuard
{
    /** @param array<string, mixed> $cible la charge utile du compte visé, telle que l'API la renvoie */
    public function peutGerer(ApiUser $acteur, array $cible, string $action): bool
    {
        return $this->motifRefus($acteur, $cible, $action) === null;
    }

    /** @param array<string, mixed> $cible */
    public function motifRefus(ApiUser $acteur, array $cible, string $action): ?string
    {
        if($action === AccesVoter::VOIR) {
            return null; // Voir un compte de son périmètre est toujours permis, le reste se protège
        }

        if((int) ($cible['id'] ?? 0) === $acteur->getId()) {
            return 'C\'est votre propre compte : il se gère depuis votre profil.';
        }

        if($this->aLeRole($cible, ApiUser::SUPER_ADMIN)) {
            return 'Un super administrateur ne se gère pas depuis cette interface.';
        }

        /*
            - Le fondateur — celui qui a inscrit l'entreprise — n'est ni suspendable, ni supprimable,
              ni rétrogradable par un pair. Sans ce garde-fou, deux administrateurs se neutralisent et
              l'entreprise se verrouille hors de son propre outil.
        */
        $retire = in_array($action, [AccesVoter::BLOQUER, AccesVoter::SUPPRIMER, AccesVoter::PROMOUVOIR], true);

        if($retire && ($cible['fondateur'] ?? false) && !$acteur->estSuperAdmin()) {
            return 'Le compte fondateur ne peut être ni suspendu, ni supprimé, ni rétrogradé par un autre administrateur.';
        }

        // L'agent ne remonte pas la hiérarchie : il n'agit que sur des opérateurs
        if($acteur->estAgent() && !$this->aLeRole($cible, ApiUser::OPERATEUR)) {
            return 'Un agent ne gère que les comptes opérateur.';
        }

        return null;
    }

    /** @param array<string, mixed> $compte */
    private function aLeRole(array $compte, string $role): bool
    {
        return in_array($role, (array) ($compte['roles'] ?? []), true);
    }
}

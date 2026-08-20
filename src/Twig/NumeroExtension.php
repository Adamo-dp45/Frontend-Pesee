<?php

namespace App\Twig;

use Twig\Attribute\AsTwigFilter;

/*
    - L'API range les numéros au format que les agrégateurs mobile money exigent : '+2250701020304'.
      C'est le bon format à stocker et à transmettre, et le mauvais à lire — or c'est bien de vive
      voix qu'un numéro se vérifie avec un planteur avant d'engager un versement.

    - Le regroupement est donc de l'AFFICHAGE, jamais de la donnée. Rien ici ne doit remonter dans un
      formulaire ni repartir vers l'API.

    - Ce qui ne ressemble pas à un numéro ivoirien est rendu tel quel. C'est une saisie venue du poste
      de pesée que personne n'a encore corrigée : la maquiller empêcherait de la reconnaître, alors
      que c'est précisément ce qu'il faut voir pour la corriger.

    - Le pendant React est 'numero()' dans 'assets/react/components/cellules.tsx' : mêmes règles, deux
      langages, parce qu'un même écran mélange gabarits Twig et îlots React.
*/
final class NumeroExtension
{
    private const INDICATIF = '+225';
    private const LONGUEUR = 13; // 225 + dix chiffres

    #[AsTwigFilter('numero')]
    public function numero(?string $valeur): ?string
    {
        if($valeur === null || $valeur === '') {
            return $valeur;
        }

        $chiffres = preg_replace('/[^0-9]/', '', $valeur) ?? '';

        if(!str_starts_with($valeur, self::INDICATIF) || strlen($chiffres) !== self::LONGUEUR) {
            return $valeur;
        }

        return self::INDICATIF . ' ' . trim(chunk_split(substr($chiffres, 3), 2, ' '));
    }
}

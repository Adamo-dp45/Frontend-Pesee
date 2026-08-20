<?php

namespace App\Controller;

use App\Domain\Helper\ApiHelper;
use App\Domain\Helper\TableHelper;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/*
    - Le journal des décisions : qui a changé un prix, coupé un poste, suspendu un compte, vidé la
      corbeille. Le pendant de la caisse, qui ne retient que l'argent.

    - En lecture seule, et c'est le fond du sujet : un journal ne se corrige pas. Aucune route
      d'écriture n'existe ici, et l'API n'en expose aucune non plus.

    - Écran de supervision : l'opérateur n'y a pas droit. Il agit, il ne surveille pas.
*/
#[Route('/activites', name: 'activite.')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
final class ActiviteController extends GestionController
{
    /* Liste blanche des tris : un tri hors de celle de l'API la ferait répondre en erreur, ou pire, l'ignorerait en laissant croire au classement demandé. */
    private const TRIS = ['createdAt'];

    /* 'clé du formulaire' => 'filtre de l'API'. */
    private const FILTRES = ['type' => 'type'];

    /*
        - Les libellés du filtre. Ils recopient 'App\Domain\Enum\TypeActivite' côté API — comme le
          dictionnaire des états, et pour la même raison : une liste déroulante a besoin des valeurs
          AVANT d'avoir des données à afficher, donc l'API ne peut pas les fournir au fil de l'eau.
        - Les lignes affichées, elles, portent leur propre 'typeLibelle' rendu par l'API : ce qu'on
          lit dans le tableau ne dépend pas de cette table.
        ⚠ Un cas ajouté à l'énumération se reporte ici, sinon il devient infiltrable.
    */
    private const TYPES = [
        'PRODUIT_TARIFE' => 'Tarification d\'un produit',
        'FOURNISSEUR_TARIFE' => 'Prix spécial d\'un planteur',
        'SITE_BLOQUE' => 'Blocage d\'un pont bascule',
        'SITE_DEBLOQUE' => 'Déblocage d\'un pont bascule',
        'SITE_OPERATEUR' => 'Affectation de l\'opérateur',
        'SITE_IDENTIFIANTS' => 'Renouvellement des identifiants',
        'USER_SUSPENDU' => 'Suspension d\'un compte',
        'USER_REACTIVE' => 'Réactivation d\'un compte',
        'USER_ROLE' => 'Changement de rôle',
        'ENTREPRISE_DESACTIVEE' => 'Désactivation d\'une entreprise',
        'ENTREPRISE_REACTIVEE' => 'Réactivation d\'une entreprise',
        'CORBEILLE_SUPPRESSION' => 'Mise à la corbeille',
        'CORBEILLE_RESTAURATION' => 'Restauration',
        'CORBEILLE_PURGE' => 'Suppression définitive',
    ];

    public function __construct(
        private readonly ApiHelper $api,
        private readonly TableHelper $table
    )
    {
    }

    #[Route('', name: 'index', methods: ['GET'])]
    #[IsGranted('ACTIVITE_VOIR')]
    public function index(Request $request): Response
    {
        $donnees = $this->table->handleIndex('/api/activites', $request->query->all(), self::FILTRES, self::TRIS);

        return $this->render('activite/index.html.twig', $donnees + [
            'types' => self::TYPES,
        ]);
    }
}

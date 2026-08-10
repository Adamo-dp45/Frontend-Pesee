<?php

namespace App\Controller;

use App\Domain\Helper\ApiHelper;
use App\Domain\Helper\TableHelper;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/*
    - Le journal des envois des postes de pesée. Chaque ligne est un lot : ce que le poste a envoyé,
      ce qui a été créé, mis à jour, et ce qui a échoué — avec le détail des erreurs.

    - Sans cet écran, le tableau de bord dit seulement qu'un poste est « silencieux ». Il ne dit ni
      depuis quand il envoie mal, ni pourquoi, et chaque incident se règle par un appel téléphonique
      au peseur.

    - En lecture seule : un lot est un fait, il ne se corrige pas. Ce qu'on corrige, c'est la cause.
*/
#[Route('/synchronisations', name: 'synchronisation.')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
final class SynchronisationController extends GestionController
{
    /* Liste blanche des tris : un tri hors de celle de l'API la ferait répondre en erreur, ou pire, l'ignorerait en laissant croire au classement demandé. */
    private const TRIS = ['createdAt'];

    /* 'clé du formulaire' => 'filtre de l'API'. */
    private const FILTRES = ['site' => 'site'];

    public function __construct(
        private readonly ApiHelper $api,
        private readonly TableHelper $table
    )
    {
    }

    #[Route('', name: 'index', methods: ['GET'])]
    #[IsGranted('SYNCHRONISATIONLOT_VOIR')] // L'opérateur ne l'a pas : le journal des postes est un écran de supervision
    public function index(Request $request): Response
    {
        $donnees = $this->table->handleIndex('/api/synchronisation_lots', $request->query->all(), self::FILTRES, self::TRIS);

        return $this->render('synchronisation/index.html.twig', $donnees + [
            'sites' => $this->options($this->api->collection('/api/sites', ['itemsPerPage' => 100]), 'libellesite'),
        ]);
    }
}

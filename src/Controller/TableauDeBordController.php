<?php

namespace App\Controller;

use App\Domain\Helper\ApiHelper;
use App\Domain\Helper\Periode;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/*
    - L'écran d'accueil : ce que produit chaque pont bascule du réseau, vu depuis la ville.
    - Un seul appel à '/api/stats/tableau-de-bord' : sur une connexion de terrain, enchaîner six
      requêtes pour peupler un écran se paie cash.
*/
#[Route('/', name: 'tableau_de_bord.')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
final class TableauDeBordController extends AbstractController
{
    public function __construct(private readonly ApiHelper $api)
    {
    }

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        /*
            - Le super administrateur n'a pas de tableau de bord : il gère les clients, pas leurs
              pesées, et l'API le lui refuse. Plutôt que de le laisser tomber sur ce refus, on
              l'envoie chez lui. Tout ce qui pointe vers l'accueil — la connexion, les pages
              d'erreur, le menu — passe donc ici, et n'a rien à savoir de son rôle.
        */
        if ($this->isGranted('ROLE_SUPER_ADMIN')) {
            return $this->redirectToRoute('admin.entreprise.index');
        }

        $periode = Periode::depuis($request);

        $stats = $this->api->item('/api/stats/tableau-de-bord', $periode->versParametres());

        return $this->render('tableau_de_bord/index.html.twig', [
            'periode' => $periode,
            'stats' => $stats ?? [],
        ]);
    }
}

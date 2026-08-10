<?php

namespace App\Controller;

use App\Domain\Helper\ApiHelper;
use App\Domain\Service\ApiClientService;
use App\Security\Exception\ApiException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/*
    - Son propre compte. Deux gestes seulement, et volontairement séparés : corriger ses
      coordonnées, changer son mot de passe.

    - Ni rôle, ni entreprise, ni statut : l'API les refuse sur '/api/me'. On ne se promeut pas
      soi-même, et on ne se réactive pas après avoir été suspendu.
*/
#[Route('/profil', name: 'profile.')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
final class ProfileController extends GestionController
{
    public function __construct(
        private readonly ApiHelper $api,
        private readonly ApiClientService $client
    ) {
    }

    #[Route('', name: 'index', methods: ['GET', 'POST'])]
    public function index(Request $request): Response
    {
        $violations = [];
        $erreur = null;

        if ($request->isMethod('POST') && !$this->jetonValide($request)) {
            $erreur = self::JETON_INVALIDE;
        } elseif ($request->isMethod('POST')) {
            try {
                $this->api->patch('/api/me', [
                    'nom' => (string) $request->request->get('nom'),
                    'prenom' => $this->ouNull($request->request->get('prenom')),
                    'email' => (string) $request->request->get('email'),
                    'telephone' => $this->ouNull($request->request->get('telephone')),
                ]);

                /*
                    - Le profil est recopié en session à la connexion : sans ce rafraîchissement,
                      la barre latérale continuerait d'afficher l'ancien nom jusqu'à la prochaine
                      ouverture de session.
                */
                $this->client->refreshCurrentUser();

                $this->addFlash('success', 'Profil enregistré.');

                return $this->redirectToRoute('profile.index');
            } catch (ApiException $e) {
                $erreur = $e->getMessage();
                $violations = $e->getViolations();
            }
        }

        return $this->render('profile/index.html.twig', [
            'moi' => $this->api->item('/api/me'),
            'erreur' => $erreur,
            'violations' => $violations,
        ], new Response(status: $erreur === null ? Response::HTTP_OK : self::EN_ERREUR));
    }

    /*
        - Le mot de passe actuel est exigé : une session ouverte ne suffit pas. Sans cela, un poste
          laissé déverrouillé permettrait à n'importe qui de s'approprier le compte de son occupant.
    */
    #[Route('/mot-de-passe', name: 'mot_de_passe', methods: ['GET', 'POST'])]
    public function motDePasse(Request $request): Response
    {
        $violations = [];
        $erreur = null;

        if ($request->isMethod('POST') && !$this->jetonValide($request)) {
            $erreur = self::JETON_INVALIDE;
        } elseif ($request->isMethod('POST')) {
            $nouveau = (string) $request->request->get('nouveauMotDePasse');

            if ($nouveau !== (string) $request->request->get('confirmation')) {
                $erreur = 'Les deux mots de passe ne sont pas identiques.';
            } else {
                try {
                    $this->api->post('/api/me/mot-de-passe', [
                        'motDePasseActuel' => (string) $request->request->get('motDePasseActuel'),
                        'nouveauMotDePasse' => $nouveau,
                    ]);

                    $this->addFlash('success', 'Mot de passe modifié.');

                    return $this->redirectToRoute('profile.index');
                } catch (ApiException $e) {
                    $erreur = $e->getMessage();
                    $violations = $e->getViolations();
                }
            }
        }

        return $this->render('profile/mot_de_passe.html.twig', [
            'erreur' => $erreur,
            'violations' => $violations,
        ], new Response(status: $erreur === null ? Response::HTTP_OK : self::EN_ERREUR));
    }
}

<?php

namespace App\Controller;

use App\Domain\Helper\ApiExceptionHandlerHelper;
use App\Domain\Helper\ApiHelper;
use App\Domain\Helper\TableHelper;
use App\Form\SiteFormType;
use App\Security\Exception\ApiException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/*
    - Les ponts bascules. Deux métiers cohabitent sur cet écran, et les voters les séparent :

      · le super administrateur installe les postes — il crée le site, son compte machine et le
        fichier de configuration que le poste consommera ;
      · l'administrateur de l'entreprise les exploite — il affecte l'opérateur, dote la caisse,
        bloque un poste qui déraille.

    - Aucune création à la volée par la synchronisation : un poste en service a forcément été
      déclaré ici d'abord, puisque c'est d'ici que sort son fichier de configuration.
*/
#[Route('/sites', name: 'site.')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
final class SiteController extends GestionController
{
    /*
        - Liste blanche des tris : chaque ressource n'expose à son 'OrderFilter' que quelques propriétés. Laisser passer un tri inconnu ferait répondre l'API en erreur, ou pire, l'ignorerait en laissant croire au classement demandé.
    */
    private const TRIS = ['libellesite', 'code', 'solde', 'createdAt'];

    /* 'clé du formulaire' => 'filtre de l'API'. La recherche porte sur le libellé, c'est ce qu'on lit dans la colonne. */
    private const FILTRES = ['recherche' => 'libellesite', 'statut' => 'statut'];

    public function __construct(
        private readonly ApiHelper $api,
        private readonly TableHelper $table,
        private readonly ApiExceptionHandlerHelper $apiExceptionHandler
    )
    {
    }

    #[Route('', name: 'index', methods: ['GET'])]
    #[IsGranted('SITE_VOIR')]
    public function index(Request $request): Response
    {
        $donnees = $this->table->handleIndex('/api/sites', $request->query->all(), self::FILTRES, self::TRIS);

        /*
            - Chaque ligne porte ses URL, composées ici. React n'en fabrique aucune : le routeur reste la seule source des chemins, et une contrainte de route ne peut pas être contournée par une concaténation côté client.
        */
        $donnees['items'] = array_map(fn(array $site): array => $site + [
            'url' => $this->generateUrl('site.show', ['id' => $site['id']]),
            'urlBlocage' => $this->generateUrl('site.blocage', ['id' => $site['id']]),
        ], $donnees['items']);

        return $this->render('site/index.html.twig', $donnees);
    }

    /*
        - Mise en service d'un poste. La réponse porte le mot de passe du compte machine, et c'est
          la seule fois : il n'est stocké nulle part en clair. On l'affiche donc immédiatement,
          avec le fichier de configuration à télécharger.
    */
    #[Route('/nouveau', name: 'new', methods: ['GET', 'POST'])]
    #[IsGranted('SITE_CREER')]
    public function new(Request $request): Response
    {
        $form = $this->createForm(SiteFormType::class, null, [
            'entreprises' => $this->options($this->api->collection('/api/entreprises', ['itemsPerPage' => 200]), 'nom'),
            'creation' => true,
        ]);
        $form->handleRequest($request);

        if($form->isSubmitted() && $form->isValid()) {
            $saisie = $form->getData();

            try {
                $site = $this->api->post('/api/sites', array_diff_key($saisie, ['entreprise' => null]) + [
                    'entreprise' => '/api/entreprises/' . $saisie['entreprise'], // Relation en IRI
                ]);

                return $this->render('site/identifiants.html.twig', ['site' => $site]);
            } catch(ApiException $e) {
                // Remet les violations de l'API sous les champs concernés, et renvoie une Response ou null
                if($reponse = $this->apiExceptionHandler->handle($e, $form, 'site.index')) {
                    return $reponse;
                }
            }
        }

        return $this->render('site/new.html.twig', ['form' => $form], new Response(
            status: $form->isSubmitted() && !$form->isValid() ? self::EN_ERREUR : Response::HTTP_OK
        ));
    }

    #[Route('/{id<\d+>}', name: 'show', methods: ['GET', 'POST'])]
    #[IsGranted('SITE_VOIR')]
    public function show(int $id, Request $request): Response
    {
        $site = $this->api->item('/api/sites/' . $id);

        /*
            - La fiche se CONSULTE avec 'SITE_VOIR' — l'agent et l'opérateur y viennent lire le solde
              et l'opérateur du poste. Seule la carte d'identité se modifie, d'où un droit par carte
              plutôt qu'un droit sur la route entière.
        */
        $modifiable = $this->isGranted('SITE_MODIFIER');
        $attribuable = $this->isGranted('SITE_ATTRIBUER');

        /* Le code et l'entreprise n'y figurent pas : ils ne sont modifiables qu'à la création, d'où 'creation: false'. */
        $form = $this->createForm(SiteFormType::class, [
            'libellesite' => $site['libellesite'] ?? null,
            'localite' => $site['localite'] ?? null,
        ]);

        if($modifiable) {
            $form->handleRequest($request); // Sans ce garde, un POST forgé partait vers l'API pour n'en revenir qu'en 403

            if($form->isSubmitted() && $form->isValid()) {
                try {
                    $this->api->patch('/api/sites/' . $id, $form->getData());

                    $this->addFlash('success', 'Pont bascule enregistré.');

                    return $this->redirectToRoute('site.show', ['id' => $id]);
                } catch(ApiException $e) {
                    if($reponse = $this->apiExceptionHandler->handle($e, $form, 'site.index')) {
                        return $reponse;
                    }
                }
            }
        }

        return $this->render('site/show.html.twig', [
            'site' => $site,
            'form' => $form,
            'modifiable' => $modifiable,
            'attribuable' => $attribuable,
            'blocable' => $this->isGranted('SITE_BLOQUER'),
            'renouvelable' => $this->isGranted('SITE_CREER'), // Mettre en service et remettre en service, c'est le même geste
            // Pour le sélecteur d'opérateur : seul celui qui affecte a besoin de la liste
            'operateurs' => $attribuable
                ? $this->api->collection('/api/users', ['itemsPerPage' => 200])
                : [],
        ], new Response(status: $form->isSubmitted() && !$form->isValid() ? self::EN_ERREUR : Response::HTTP_OK));
    }

    #[Route('/{id<\d+>}/blocage', name: 'blocage', methods: ['POST'])]
    #[IsGranted('SITE_BLOQUER')]
    public function blocage(int $id, Request $request): Response
    {
        return $this->agir($request, $id, function () use ($id): string {
            $site = $this->api->patch('/api/sites/' . $id . '/blocage', []);

            return ($site['statut'] ?? '') === 'BLOQUE'
                ? 'Pont bascule bloqué : il ne reçoit plus de pesées et sa caisse est gelée.'
                : 'Pont bascule débloqué : la synchronisation reprend.';
        });
    }

    #[Route('/{id<\d+>}/operateur', name: 'operateur', methods: ['POST'])]
    #[IsGranted('SITE_ATTRIBUER')]
    public function operateur(int $id, Request $request): Response
    {
        return $this->agir($request, $id, function () use ($id, $request): string {
            // Valeur vide = on libère le site, l'API l'attend explicitement à null
            $this->api->patch('/api/sites/' . $id . '/operateur', [
                'operateurId' => $this->entierOuNull($request->request->get('operateurId')),
            ]);

            return 'Opérateur mis à jour.';
        });
    }

    #[Route('/{id<\d+>}/dotation', name: 'dotation', methods: ['POST'])]
    #[IsGranted('SITE_ATTRIBUER')] // Doter, c'est attribuer : l'API pose le même droit sur les deux
    public function dotation(int $id, Request $request): Response
    {
        return $this->agir($request, $id, function () use ($id, $request): string {
            $this->api->patch('/api/sites/' . $id . '/dotation', [
                'montant' => $request->request->getInt('montant'),
                'motif' => $this->ouNull($request->request->get('motif')),
            ]);

            return 'Caisse dotée. L\'argent a quitté le solde de l\'entreprise.';
        });
    }

    #[Route('/{id<\d+>}/identifiants', name: 'identifiants', methods: ['POST'])]
    #[IsGranted('SITE_CREER')]
    public function identifiants(int $id, Request $request): Response
    {
        if (!$this->jetonValide($request)) {
            $this->addFlash('danger', self::JETON_INVALIDE);

            return $this->redirectToRoute('site.show', ['id' => $id]);
        }

        try {
            return $this->render('site/identifiants.html.twig', [
                'site' => $this->api->patch('/api/sites/' . $id . '/identifiants', []),
                'renouvelle' => true,
            ]);
        } catch (ApiException $e) {
            $this->addFlash('danger', $e->getMessage());

            return $this->redirectToRoute('site.show', ['id' => $id]);
        }
    }

    /*
        - Le fichier déposé sur le poste à l'installation. Il ne contient rien de secret par
          lui-même : le mot de passe n'y est que parce qu'il vient d'être engendré et n'existe
          nulle part ailleurs.
    */
    #[Route('/{id<\d+>}/configuration', name: 'configuration', methods: ['POST'])]
    #[IsGranted('SITE_CREER')]
    public function configuration(int $id, Request $request): Response
    {
        $reponse = new JsonResponse([
            'api' => $this->getParameter('api.endpoint'),
            'code' => (string) $request->request->get('code'),
            'compte' => (string) $request->request->get('compte'),
            'motDePasse' => (string) $request->request->get('motDePasse'),
        ]);

        $reponse->setEncodingOptions(\JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES);
        $reponse->headers->set(
            'Content-Disposition',
            sprintf('attachment; filename="webpesee-%s.json"', $request->request->get('code'))
        );

        return $reponse;
    }

    /** Le tronc commun des actions de ligne : jeton, appel, message, retour à la fiche. */
    private function agir(Request $request, int $id, callable $action): Response
    {
        if (!$this->jetonValide($request)) {
            $this->addFlash('danger', self::JETON_INVALIDE);
        } else {
            try {
                $this->addFlash('success', $action());
            } catch (ApiException $e) {
                $this->addFlash('danger', $e->getMessage());
            }
        }

        return $this->redirectToRoute('site.show', ['id' => $id]);
    }
}

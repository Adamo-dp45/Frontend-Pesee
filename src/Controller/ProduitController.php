<?php

namespace App\Controller;

use App\Domain\Helper\ApiExceptionHandlerHelper;
use App\Domain\Helper\ApiHelper;
use App\Domain\Helper\TableHelper;
use App\Form\ProduitFormType;
use App\Security\Exception\ApiException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/*
    - Les produits pesés, et surtout leur prix au kilo : c'est lui qui valorise chaque pesée.
*/
#[Route('/produits', name: 'produit.')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
final class ProduitController extends GestionController
{
    /* Liste blanche des tris : un tri hors de celle de l'API la ferait répondre en erreur, ou pire, l'ignorerait en laissant croire au classement demandé. */
    private const TRIS = ['codeproduit', 'libelle', 'prix', 'createdAt'];

    /* 'clé du formulaire' => 'filtre de l'API'. */
    private const FILTRES = ['recherche' => 'libelle', 'site' => 'site', 'actif' => 'actif'];

    public function __construct(
        private readonly ApiHelper $api,
        private readonly TableHelper $table,
        private readonly ApiExceptionHandlerHelper $apiExceptionHandler
    )
    {
    }

    #[Route('', name: 'index', methods: ['GET'])]
    #[IsGranted('PRODUIT_VOIR')]
    public function index(Request $request): Response
    {
        $donnees = $this->table->handleIndex('/api/produits', $request->query->all(), self::FILTRES, self::TRIS);

        /* Chaque ligne porte ses URL, composées ici : React n'en fabrique aucune, le routeur reste la seule source des chemins. */
        $donnees['items'] = array_map(fn(array $produit): array => $produit + [
            'url' => $this->generateUrl('produit.show', ['id' => $produit['id']]),
            'urlEdit' => $this->generateUrl('produit.edit', ['id' => $produit['id']]),
            'urlDelete' => $this->generateUrl('produit.delete', ['id' => $produit['id']]),
        ], $donnees['items']);

        return $this->render('produit/index.html.twig', $donnees + [
            'sites' => $this->options($this->api->collection('/api/sites', ['itemsPerPage' => 100]), 'libellesite'),
            'modifiable' => $this->isGranted('PRODUIT_MODIFIER'),
            'supprimable' => $this->isGranted('PRODUIT_SUPPRIMER'), // L'opérateur tarifie, il ne met pas à la corbeille
        ]);
    }

    /*
        - Le poste de pesée crée ses produits à la volée, mais rien n'empêche de les déclarer ici
          avant sa mise en service : le prix est alors connu dès la première pesée, au lieu de
          rester à zéro jusqu'à ce qu'on y pense.
    */
    #[IsGranted('PRODUIT_CREER')]
    #[Route('/nouveau', name: 'new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $form = $this->createForm(ProduitFormType::class, null, [
            'sites' => $this->options($this->api->collection('/api/sites', ['itemsPerPage' => 100]), 'libellesite'),
            'creation' => true,
        ]);
        $form->handleRequest($request);

        if($form->isSubmitted() && $form->isValid()) {
            $saisie = $form->getData();

            try {
                $this->api->post('/api/produits', [
                    'libelle' => $saisie['libelle'],
                    'codeproduit' => $saisie['codeproduit'],
                    'prix' => $saisie['prix'],
                    'actif' => true,
                    'site' => '/api/sites/' . $saisie['site'], // Relation en IRI
                ]);

                $this->addFlash('success', 'Produit créé.');

                return $this->redirectToRoute('produit.index');
            } catch(ApiException $e) {
                // Remet les violations de l'API sous les champs concernés, et renvoie une Response ou null
                if($reponse = $this->apiExceptionHandler->handle($e, $form, 'produit.index')) {
                    return $reponse;
                }
            }
        }

        return $this->render('produit/new.html.twig', ['form' => $form], new Response(
            status: $form->isSubmitted() && !$form->isValid() ? self::EN_ERREUR : Response::HTTP_OK
        ));
    }

    #[Route('/{id<\d+>}', name: 'show', methods: ['GET'])]
    #[IsGranted('PRODUIT_VOIR')]
    public function show(int $id): Response
    {
        return $this->render('produit/show.html.twig', [
            'produit' => $this->api->item('/api/produits/' . $id),
            'modifiable' => $this->isGranted('PRODUIT_MODIFIER'),
            'supprimable' => $this->isGranted('PRODUIT_SUPPRIMER'),
        ]);
    }

    #[IsGranted('PRODUIT_MODIFIER')]
    #[Route('/{id<\d+>}/modifier', name: 'edit', methods: ['GET', 'POST'])]
    public function edit(int $id, Request $request): Response
    {
        $produit = $this->api->item('/api/produits/' . $id);

        /* Le formulaire est pré-rempli avec l'item de l'API : les champs absents du formulaire ne sont jamais renvoyés, donc jamais écrasés. */
        $form = $this->createForm(ProduitFormType::class, [
            'libelle' => $produit['libelle'] ?? null,
            'codeproduit' => $produit['codeproduit'] ?? null,
            'prix' => $produit['prix'] ?? 0,
            'actif' => $produit['actif'] ?? false,
        ]);
        $form->handleRequest($request);

        if($form->isSubmitted() && $form->isValid()) {
            try {
                $this->api->patch('/api/produits/' . $id, $form->getData());

                $this->addFlash('success', 'Produit enregistré.');

                return $this->redirectToRoute('produit.index');
            } catch(ApiException $e) {
                if($reponse = $this->apiExceptionHandler->handle($e, $form, 'produit.index')) {
                    return $reponse;
                }
            }
        }

        return $this->render('produit/edit.html.twig', [
            'produit' => $produit,
            'form' => $form,
        ], new Response(status: $form->isSubmitted() && !$form->isValid() ? self::EN_ERREUR : Response::HTTP_OK));
    }

    /*
        - Suppression logique, jamais de DELETE : les pesées déjà enregistrées référencent ce
          produit et doivent garder leur montant.
        - À ne pas confondre avec le drapeau 'actif', qui laisse le produit en place et cesse
          seulement de le proposer.
    */
    #[IsGranted('PRODUIT_SUPPRIMER')]
    #[Route('/{id<\d+>}/supprimer', name: 'delete', methods: ['POST'])]
    public function delete(int $id, Request $request): Response
    {
        if (!$this->jetonValide($request)) {
            $this->addFlash('danger', self::JETON_INVALIDE);

            return $this->redirectToRoute('produit.index');
        }

        try {
            $this->api->patch('/api/produits/' . $id . '/suppression', []);
            $this->addFlash('success', 'Produit supprimé. Les pesées déjà enregistrées le conservent.');
        } catch (ApiException $e) {
            $this->addFlash('danger', $e->getMessage());
        }

        return $this->redirectToRoute('produit.index');
    }

}

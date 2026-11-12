<?php

namespace App\Controller;

use App\Domain\Helper\ApiExceptionHandlerHelper;
use App\Domain\Helper\ApiHelper;
use App\Domain\Helper\TableHelper;
use App\Form\FournisseurFormType;
use App\Security\Exception\ApiException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/*
    - Ce que le web apporte et que le poste ne connaît pas : le numéro mobile money et son réseau. Sans eux, la pesée est enregistrée mais le planteur ne peut pas être payé.
*/
#[Route('/fournisseurs', name: 'fournisseur.')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
final class FournisseurController extends GestionController
{
    /* Liste blanche des tris : un tri hors de celle de l'API la ferait répondre en erreur, ou pire, l'ignorerait en laissant croire au classement demandé. */
    private const TRIS = ['codefournisseur', 'nom', 'prixtransport', 'createdAt'];

    /* 'clé du formulaire' => 'filtre de l'API'. */
    private const FILTRES = ['recherche' => 'nom', 'site' => 'site', 'statut' => 'statut'];

    private const RESEAUX = ['ORANGE', 'MTN', 'MOOV', 'WAVE'];

    public function __construct(
        private readonly ApiHelper $api,
        private readonly TableHelper $table,
        private readonly ApiExceptionHandlerHelper $apiExceptionHandler
    )
    {
    }

    #[Route('', name: 'index', methods: ['GET'])]
    #[IsGranted('FOURNISSEUR_VOIR')]
    public function index(Request $request): Response
    {
        $donnees = $this->table->handleIndex('/api/fournisseurs', $request->query->all(), self::FILTRES, self::TRIS);

        /* Chaque ligne porte ses URL, composées ici : React n'en fabrique aucune, le routeur reste la seule source des chemins. */
        $donnees['items'] = array_map(fn(array $fournisseur): array => $fournisseur + [
            'url' => $this->generateUrl('fournisseur.show', ['id' => $fournisseur['id']]),
            'urlEdit' => $this->generateUrl('fournisseur.edit', ['id' => $fournisseur['id']]),
            'urlDelete' => $this->generateUrl('fournisseur.delete', ['id' => $fournisseur['id']]),
        ], $donnees['items']);

        return $this->render('fournisseur/index.html.twig', $donnees + [
            'sites' => $this->options($this->api->collection('/api/sites', ['itemsPerPage' => 100]), 'libellesite'),
            'modifiable' => $this->isGranted('FOURNISSEUR_MODIFIER'),
            'supprimable' => $this->isGranted('FOURNISSEUR_SUPPRIMER'), // L'opérateur renseigne les numéros, il ne met pas à la corbeille
        ]);
    }

    /*
        - Le poste crée le planteur au moment de la pesée, avec son seul nom. Le déclarer ici
          d'avance permet de renseigner tout de suite son numéro mobile money : sans lui, la
          première pesée s'enregistre mais le versement est refusé.
    */
    #[IsGranted('FOURNISSEUR_CREER')] // L'opérateur en fait partie : c'est lui qui a le planteur en face de lui
    #[Route('/nouveau', name: 'new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $form = $this->createForm(FournisseurFormType::class, null, [
            'sites' => $this->options($this->api->collection('/api/sites', ['itemsPerPage' => 100]), 'libellesite'),
            'reseaux' => self::RESEAUX,
            'creation' => true,
        ]);
        $form->handleRequest($request);

        if($form->isSubmitted() && $form->isValid()) {
            $saisie = $form->getData();

            try {
                $this->api->post('/api/fournisseurs', array_diff_key($saisie, ['site' => null]) + [
                    'statut' => 'ACTIF',
                    'site' => '/api/sites/' . $saisie['site'], // Relation en IRI
                ]);

                $this->addFlash('success', 'Planteur créé.');

                return $this->redirectToRoute('fournisseur.index');
            } catch(ApiException $e) {
                // Remet les violations de l'API sous les champs concernés, et renvoie une Response ou null
                if($reponse = $this->apiExceptionHandler->handle($e, $form, 'fournisseur.index')) {
                    return $reponse;
                }
            }
        }

        return $this->render('fournisseur/new.html.twig', ['form' => $form], new Response(
            status: $form->isSubmitted() && !$form->isValid() ? self::EN_ERREUR : Response::HTTP_OK
        ));
    }

    #[Route('/{id<\d+>}', name: 'show', methods: ['GET'])]
    #[IsGranted('FOURNISSEUR_VOIR')]
    public function show(int $id): Response
    {
        $fournisseur = $this->api->item('/api/fournisseurs/' . $id);

        return $this->render('fournisseur/show.html.twig', [
            'fournisseur' => $fournisseur,
            // Ses dernières pesées : c'est ce qu'on vient vérifier sur la fiche d'un planteur
            'pesees' => $this->api->collection('/api/operations', [
                'fournisseur' => $id,
                'itemsPerPage' => 10,
                'order[peseeAt2]' => 'desc',
            ]),
            'modifiable' => $this->isGranted('FOURNISSEUR_MODIFIER'),
            'supprimable' => $this->isGranted('FOURNISSEUR_SUPPRIMER'),
            // La mise en compte : l'administrateur l'ouvre et l'arrête, l'opérateur la demande
            'preleveur' => $this->isGranted('FOURNISSEUR_PRELEVER'),
            'mouvements' => $this->api->collection('/api/mouvement_epargnes', [
                'compte.fournisseur' => $id,
                'itemsPerPage' => 10,
                'order[createdAt]' => 'desc',
            ]),
            // Pour la liste déroulante d'ouverture. Le prix est dans le libellé : c'est lui qui borne le taux
            'produits' => $this->produitsTarifes((int) ($fournisseur['site']['id'] ?? 0)),
        ]);
    }

    /*
        - Ouvrir ou arrêter la mise en compte d'un planteur SUR UN PRODUIT. Un taux vide arrête le
          prélèvement de ce produit : ce qui a déjà été mis de côté reste au planteur, seul le
          robinet se ferme. Les autres cultures ne bougent pas.
        - Réservé à l'administrateur. L'opérateur, lui, passe par une demande — il a le planteur
          devant lui, mais décider de ce qu'un homme touche au guichet n'est pas son geste.
    */
    #[IsGranted('FOURNISSEUR_PRELEVER')]
    #[Route('/{id<\d+>}/epargne', name: 'epargne', methods: ['POST'])]
    public function epargne(int $id, Request $request): Response
    {
        if(!$this->jetonValide($request)) {
            $this->addFlash('danger', self::JETON_INVALIDE);

            return $this->redirectToRoute('fournisseur.show', ['id' => $id]);
        }

        $saisie = trim((string) $request->request->get('taux'));
        $taux = $saisie === '' ? null : (int) $saisie;
        $produit = (int) $request->request->get('produit');

        try {
            $this->api->patch('/api/fournisseurs/' . $id . '/epargne', ['produit' => $produit, 'taux' => $taux]);

            $this->addFlash('success', $taux === null
                ? 'Mise en compte arrêtée sur ce produit. L\'épargne déjà constituée reste au planteur.'
                : sprintf('Mise en compte fixée à %d F par kilo. Elle s\'appliquera aux pesées à venir de ce produit.', $taux));
        } catch(ApiException $e) {
            $this->addFlash('danger', $e->getMessage());
        }

        return $this->redirectToRoute('fournisseur.show', ['id' => $id]);
    }

    #[IsGranted('FOURNISSEUR_MODIFIER')]
    #[Route('/{id<\d+>}/modifier', name: 'edit', methods: ['GET', 'POST'])]
    public function edit(int $id, Request $request): Response
    {
        $fournisseur = $this->api->item('/api/fournisseurs/' . $id);

        /* Pré-rempli avec l'item de l'API : les champs absents du formulaire ne sont jamais renvoyés, donc jamais écrasés. */
        $form = $this->createForm(FournisseurFormType::class, [
            'nom' => $fournisseur['nom'] ?? null,
            'prenom' => $fournisseur['prenom'] ?? null,
            'codefournisseur' => $fournisseur['codefournisseur'] ?? null,
            'contact1' => $fournisseur['contact1'] ?? null,
            'contact2' => $fournisseur['contact2'] ?? null,
            'reseau' => $fournisseur['reseau'] ?? null,
            'prixtransport' => $fournisseur['prixtransport'] ?? null, // Champ vidé = on ne lui paie pas de transport
        ], ['reseaux' => self::RESEAUX]);
        $form->handleRequest($request);

        if($form->isSubmitted() && $form->isValid()) {
            try {
                $this->api->patch('/api/fournisseurs/' . $id, $form->getData());

                $this->addFlash('success', 'Planteur enregistré.');

                return $this->redirectToRoute('fournisseur.index');
            } catch(ApiException $e) {
                if($reponse = $this->apiExceptionHandler->handle($e, $form, 'fournisseur.index')) {
                    return $reponse;
                }
            }
        }

        return $this->render('fournisseur/edit.html.twig', [
            'fournisseur' => $fournisseur,
            'form' => $form,
        ], new Response(status: $form->isSubmitted() && !$form->isValid() ? self::EN_ERREUR : Response::HTTP_OK));
    }

    /*
        - Suppression logique : le planteur sort des écrans mais reste en base. Ses pesées et
          ses versements le référencent, et un DELETE emporterait leur historique.
    */
    #[IsGranted('FOURNISSEUR_SUPPRIMER')]
    #[Route('/{id<\d+>}/supprimer', name: 'delete', methods: ['POST'])]
    public function delete(int $id, Request $request): Response
    {
        if (!$this->jetonValide($request)) {
            $this->addFlash('danger', self::JETON_INVALIDE);

            return $this->redirectToRoute('fournisseur.index');
        }

        try {
            $this->api->patch('/api/fournisseurs/' . $id . '/suppression', []);
            $this->addFlash('success', 'Planteur supprimé. Ses pesées et ses versements sont conservés.');
        } catch (ApiException $e) {
            $this->addFlash('danger', $e->getMessage());
        }

        return $this->redirectToRoute('fournisseur.index');
    }

    /*
        - Les produits sur lesquels un prélèvement peut s'ouvrir, LE PRIX DANS LE LIBELLÉ : c'est lui
          qui borne le taux, et le cacher reviendrait à faire deviner l'administrateur puis à le
          faire refuser par l'API.

        - DU PONT BASCULE DU PLANTEUR, et de lui seul. Les référentiels sont locaux : « Cacao »
          existe une fois par poste, avec son propre prix. Un administrateur voit tous les sites, et
          sans ce filtre la liste lui proposait le café de Gagnoa sur un planteur de Soubré — que
          l'API refuse, mais trop tard.

        - Les produits non tarifés sont écartés pour la même raison : l'API les refuse — leurs pesées
          ne valent rien, un prélèvement dessus ne mettrait jamais un franc de côté.
    */
    /** @return array<int, string> */
    private function produitsTarifes(int $site): array
    {
        $options = [];

        foreach($this->api->collection('/api/produits', ['site' => $site, 'itemsPerPage' => 200]) as $produit) {
            $prix = (int) ($produit['prix'] ?? 0);

            if($prix <= 0) {
                continue;
            }

            $options[(int) $produit['id']] = sprintf('%s — %s F le kilo', (string) $produit['libelle'], number_format($prix, 0, ',', ' '));
        }

        return $options;
    }
}

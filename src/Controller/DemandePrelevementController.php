<?php

namespace App\Controller;

use App\Domain\Helper\ApiExceptionHandlerHelper;
use App\Domain\Helper\ApiHelper;
use App\Domain\Helper\TableHelper;
use App\Form\DemandePrelevementFormType;
use App\Form\TraiterPrelevementFormType;
use App\Security\Exception\ApiException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/*
    - Les demandes de mise en compte : le chemin par lequel un opérateur transmet ce que son
      planteur lui a demandé au guichet, sans avoir à appeler l'administrateur.

    - L'approbation ne déplace AUCUN argent, contrairement à celle d'une demande de solde : elle
      ouvre le prélèvement, et ce sont les pesées suivantes qui mettront de côté. L'écran de
      traitement le dit, pour qu'on n'attende pas un mouvement qui ne viendra pas.
*/
#[Route('/mises-en-compte', name: 'prelevement.')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
final class DemandePrelevementController extends GestionController
{
    /* Liste blanche des tris : un tri hors de celle de l'API la ferait répondre en erreur, ou pire, l'ignorerait en laissant croire au classement demandé. */
    private const TRIS = ['createdAt', 'tauxDemande'];

    /* 'clé du formulaire' => 'filtre de l'API'. */
    private const FILTRES = ['statut' => 'statut', 'site' => 'site'];

    public function __construct(
        private readonly ApiHelper $api,
        private readonly TableHelper $table,
        private readonly ApiExceptionHandlerHelper $apiExceptionHandler
    )
    {
    }

    #[Route('', name: 'index', methods: ['GET'])]
    #[IsGranted('DEMANDEPRELEVEMENT_VOIR')]
    public function index(Request $request): Response
    {
        $donnees = $this->table->handleIndex('/api/demande_prelevements', $request->query->all(), self::FILTRES, self::TRIS);

        /*
            - Le droit et l'état sont deux conditions distinctes : le premier vient de la table de
              l'API, le second de la demande elle-même. Une demande traitée n'offre plus rien.
        */
        $traiteur = $this->isGranted('DEMANDEPRELEVEMENT_TRAITER');
        $demandeur = $this->isGranted('DEMANDEPRELEVEMENT_MODIFIER');

        $donnees['items'] = array_map(function(array $demande) use ($traiteur, $demandeur): array {
            $enAttente = ($demande['statut'] ?? '') === 'EN_ATTENTE';

            return $demande + [
                'urlTraitement' => $this->generateUrl('prelevement.traiter', ['id' => $demande['id']]),
                'urlEdit' => $this->generateUrl('prelevement.edit', ['id' => $demande['id']]),
                'urlAnnulation' => $this->generateUrl('prelevement.annuler', ['id' => $demande['id']]),
                'traitable' => $enAttente && $traiteur,
                'corrigeable' => $enAttente && $demandeur,
            ];
        }, $donnees['items']);

        return $this->render('prelevement/index.html.twig', $donnees + [
            'sites' => $this->options($this->api->collection('/api/sites', ['itemsPerPage' => 100]), 'libellesite'),
            'peutDemander' => $this->isGranted('DEMANDEPRELEVEMENT_CREER'),
        ]);
    }

    /* Une seule demande en attente par planteur ET PAR PRODUIT : l'API le refuse au-delà. */
    #[IsGranted('DEMANDEPRELEVEMENT_CREER')]
    #[Route('/nouvelle', name: 'new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $form = $this->createForm(DemandePrelevementFormType::class, null, [
            'planteurs' => $this->planteurs(),
            'produits' => $this->produits(),
            'creation' => true,
        ]);
        $form->handleRequest($request);

        if($form->isSubmitted() && $form->isValid()) {
            $saisie = $form->getData();

            try {
                $this->api->post('/api/demande_prelevements', [
                    'fournisseur' => '/api/fournisseurs/' . $saisie['fournisseur'], // Relations en IRI
                    'produit' => '/api/produits/' . $saisie['produit'],
                    'tauxDemande' => $saisie['tauxDemande'],
                    'motif' => $saisie['motif'],
                ]);

                $this->addFlash('success', 'Demande envoyée à votre administrateur.');

                return $this->redirectToRoute('prelevement.index');
            } catch(ApiException $e) {
                // Remet les violations de l'API sous les champs concernés, et renvoie une Response ou null
                if($reponse = $this->apiExceptionHandler->handle($e, $form, 'prelevement.index')) {
                    return $reponse;
                }
            }
        }

        return $this->render('prelevement/new.html.twig', ['form' => $form], new Response(
            status: $form->isSubmitted() && !$form->isValid() ? self::EN_ERREUR : Response::HTTP_OK
        ));
    }

    #[IsGranted('DEMANDEPRELEVEMENT_MODIFIER')]
    #[Route('/{id<\d+>}/modifier', name: 'edit', methods: ['GET', 'POST'])]
    public function edit(int $id, Request $request): Response
    {
        $demande = $this->api->item('/api/demande_prelevements/' . $id);

        $form = $this->createForm(DemandePrelevementFormType::class, [
            'tauxDemande' => $demande['tauxDemande'] ?? 0,
            'motif' => $demande['motif'] ?? null,
        ]);
        $form->handleRequest($request);

        if($form->isSubmitted() && $form->isValid()) {
            try {
                $this->api->patch('/api/demande_prelevements/' . $id, $form->getData());

                $this->addFlash('success', 'Demande corrigée.');

                return $this->redirectToRoute('prelevement.index');
            } catch(ApiException $e) {
                if($reponse = $this->apiExceptionHandler->handle($e, $form, 'prelevement.index')) {
                    return $reponse;
                }
            }
        }

        return $this->render('prelevement/edit.html.twig', [
            'demande' => $demande,
            'form' => $form,
        ], new Response(status: $form->isSubmitted() && !$form->isValid() ? self::EN_ERREUR : Response::HTTP_OK));
    }

    #[IsGranted('DEMANDEPRELEVEMENT_MODIFIER')] // Annuler sa demande, c'est encore la modifier : l'API pose le même droit
    #[Route('/{id<\d+>}/annulation', name: 'annuler', methods: ['POST'])]
    public function annuler(int $id, Request $request): Response
    {
        if(!$this->jetonValide($request)) {
            $this->addFlash('danger', self::JETON_INVALIDE);
        } else {
            try {
                $this->api->patch('/api/demande_prelevements/' . $id . '/annulation', []);
                $this->addFlash('success', 'Demande annulée. Le planteur peut en déposer une nouvelle.');
            } catch(ApiException $e) {
                $this->addFlash('danger', $e->getMessage());
            }
        }

        return $this->redirectToRoute('prelevement.index');
    }

    /*
        - Les deux issues au même endroit : elles portent sur le même objet et s'excluent. Le taux
          n'est PAS modifiable ici — il vient du planteur, par la bouche de l'opérateur, et le
          retoucher depuis un bureau reviendrait à décider de l'épargne de quelqu'un d'autre.
    */
    #[Route('/{id<\d+>}/traitement', name: 'traiter', methods: ['GET', 'POST'])]
    #[IsGranted('DEMANDEPRELEVEMENT_TRAITER')]
    public function traiter(int $id, Request $request): Response
    {
        $demande = $this->api->item('/api/demande_prelevements/' . $id);

        $form = $this->createForm(TraiterPrelevementFormType::class, ['decision' => 'approbation']);
        $form->handleRequest($request);

        if($form->isSubmitted() && $form->isValid()) {
            $saisie = $form->getData();

            try {
                if($saisie['decision'] === 'rejet') {
                    $this->api->patch('/api/demande_prelevements/' . $id . '/rejet', ['motifRejet' => $saisie['motifRejet']]);
                    $this->addFlash('success', 'Demande rejetée.');
                } else {
                    $this->api->patch('/api/demande_prelevements/' . $id . '/approbation', []);
                    $this->addFlash('success', 'Mise en compte ouverte. Elle s\'appliquera aux pesées à venir.');
                }

                return $this->redirectToRoute('prelevement.index');
            } catch(ApiException $e) {
                // Le motif de rejet manquant remonte de l'API : son message est plus précis que tout ce qu'on redirait ici
                if($reponse = $this->apiExceptionHandler->handle($e, $form, 'prelevement.index')) {
                    return $reponse;
                }
            }
        }

        return $this->render('prelevement/traiter.html.twig', [
            'demande' => $demande,
            'form' => $form,
        ], new Response(status: $form->isSubmitted() && !$form->isValid() ? self::EN_ERREUR : Response::HTTP_OK));
    }

    /*
        - TOUS les planteurs du périmètre, et plus seulement ceux qui n'épargnent pas encore. Depuis
          que le prélèvement porte sur un produit, un planteur qui met déjà de côté sur son cacao
          reste parfaitement légitime à en demander un sur son hévéa.

        - Le couple exact, lui, est tenu par l'API, qui refuse un doublon avec un message nommant le
          produit. L'écran n'a plus de quoi le pré-filtrer de toute façon : la liste des planteurs ne
          porte pas leurs prélèvements, pour ne pas faire une requête par ligne.
    */
    /** @return array<int, string> */
    private function planteurs(): array
    {
        return $this->options($this->api->collection('/api/fournisseurs', ['itemsPerPage' => 200]), 'nomComplet');
    }

    /*
        - Les produits du périmètre, LE PRIX DANS LE LIBELLÉ. L'opérateur doit saisir un taux
          strictement inférieur à ce prix ; le lui cacher reviendrait à le laisser deviner, puis se
          faire refuser.

        - Les produits NON TARIFÉS sont écartés : l'API refuse un prélèvement dessus — leurs pesées
          ne valent rien, et rien ne serait jamais mis de côté. Les proposer à 0 F revenait à offrir
          un choix dont aucune valeur n'aurait été acceptée.

        - Pas de filtre par pont bascule ici, contrairement à la fiche planteur : le planteur ne se
          choisit que dans le même formulaire, et son poste n'est donc pas encore connu. Le périmètre
          de l'opérateur borne déjà la liste, et l'API refuse le couple dépareillé en nommant le
          poste du planteur.
    */
    /** @return array<int, string> */
    private function produits(): array
    {
        $options = [];

        foreach($this->api->collection('/api/produits', ['itemsPerPage' => 200]) as $produit) {
            $prix = (int) ($produit['prix'] ?? 0);

            if($prix <= 0) {
                continue;
            }

            $options[(int) $produit['id']] = sprintf('%s — %s F le kilo', (string) $produit['libelle'], number_format($prix, 0, ',', ' '));
        }

        return $options;
    }
}

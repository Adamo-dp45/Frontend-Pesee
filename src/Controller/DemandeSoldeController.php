<?php

namespace App\Controller;

use App\Domain\Helper\ApiHelper;
use App\Domain\Helper\ApiExceptionHandlerHelper;
use App\Domain\Helper\TableHelper;
use App\Form\DemandeSoldeFormType;
use App\Form\TraiterDemandeFormType;
use App\Security\Exception\ApiException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/*
    - Les demandes de solde : le chemin par lequel un opérateur à court d'argent en réclame à son
      administrateur, sans avoir à l'appeler.

    - L'approbation passe par la même dotation que celle de la fiche du site : l'argent se déplace
      de l'entreprise vers la caisse, en deux écritures. Le montant accordé peut être inférieur au
      montant demandé.
*/
#[Route('/demandes-de-solde', name: 'demande.')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
final class DemandeSoldeController extends GestionController
{
    /* Liste blanche des tris : un tri hors de celle de l'API la ferait répondre en erreur, ou pire, l'ignorerait en laissant croire au classement demandé. */
    private const TRIS = ['createdAt', 'montantDemande'];

    /* 'clé du formulaire' => 'filtre de l'API'. Pas de recherche libre : une demande se retrouve par son poste et son état. */
    private const FILTRES = ['statut' => 'statut', 'site' => 'site'];

    public function __construct(
        private readonly ApiHelper $api,
        private readonly TableHelper $table,
        private readonly ApiExceptionHandlerHelper $apiExceptionHandler
    )
    {
    }

    #[Route('', name: 'index', methods: ['GET'])]
    #[IsGranted('DEMANDESOLDE_VOIR')]
    public function index(Request $request): Response
    {
        $donnees = $this->table->handleIndex('/api/demande_soldes', $request->query->all(), self::FILTRES, self::TRIS);

        /*
            - Chaque ligne porte ses URL et ce qu'on a le droit d'y faire. Une demande TRAITÉE n'offre plus rien : elle a donné lieu à un mouvement de caisse, la rouvrir reviendrait à réécrire ce sur quoi l'administrateur s'est engagé.
            - Le droit et l'état sont deux conditions distinctes : le premier vient de la table de l'API, le second de la demande elle-même.
        */
        $traiteur = $this->isGranted('DEMANDESOLDE_TRAITER');
        $demandeur = $this->isGranted('DEMANDESOLDE_MODIFIER');

        $donnees['items'] = array_map(function(array $demande) use ($traiteur, $demandeur): array {
            $enAttente = ($demande['statut'] ?? '') === 'EN_ATTENTE';

            return $demande + [
                'urlTraitement' => $this->generateUrl('demande.traiter', ['id' => $demande['id']]),
                'urlEdit' => $this->generateUrl('demande.edit', ['id' => $demande['id']]),
                'urlAnnulation' => $this->generateUrl('demande.annuler', ['id' => $demande['id']]),
                'traitable' => $enAttente && $traiteur,
                'corrigeable' => $enAttente && $demandeur,
            ];
        }, $donnees['items']);

        return $this->render('demande/index.html.twig', $donnees + [
            'sites' => $this->options($this->api->collection('/api/sites', ['itemsPerPage' => 100]), 'libellesite'),
            'peutDemander' => $this->isGranted('DEMANDESOLDE_CREER'),
        ]);
    }

    /* Une seule demande en attente par pont bascule : l'API le refuse au-delà. */
    #[IsGranted('DEMANDESOLDE_CREER')]
    #[Route('/nouvelle', name: 'new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $form = $this->createForm(DemandeSoldeFormType::class, null, [
            'sites' => $this->options($this->api->collection('/api/sites', ['itemsPerPage' => 100]), 'libellesite'),
            'creation' => true,
        ]);
        $form->handleRequest($request);

        if($form->isSubmitted() && $form->isValid()) {
            $saisie = $form->getData();

            try {
                $this->api->post('/api/demande_soldes', [
                    'site' => '/api/sites/' . $saisie['site'], // Relation en IRI
                    'montantDemande' => $saisie['montantDemande'],
                    'motif' => $saisie['motif'],
                ]);

                $this->addFlash('success', 'Demande envoyée à votre administrateur.');

                return $this->redirectToRoute('demande.index');
            } catch(ApiException $e) {
                // Remet les violations de l'API sous les champs concernés, et renvoie une Response ou null
                if($reponse = $this->apiExceptionHandler->handle($e, $form, 'demande.index')) {
                    return $reponse;
                }
            }
        }

        return $this->render('demande/new.html.twig', ['form' => $form], new Response(
            status: $form->isSubmitted() && !$form->isValid() ? self::EN_ERREUR : Response::HTTP_OK
        ));
    }

    /*
        - Corriger sa demande tant qu'elle attend. Une fois traitée, l'API refuse : la modifier reviendrait à réécrire celle sur laquelle l'administrateur a déjà versé.
    */
    #[IsGranted('DEMANDESOLDE_MODIFIER')]
    #[Route('/{id<\d+>}/modifier', name: 'edit', methods: ['GET', 'POST'])]
    public function edit(int $id, Request $request): Response
    {
        $demande = $this->api->item('/api/demande_soldes/' . $id);

        $form = $this->createForm(DemandeSoldeFormType::class, [
            'montantDemande' => $demande['montantDemande'] ?? 0,
            'motif' => $demande['motif'] ?? null,
        ]);
        $form->handleRequest($request);

        if($form->isSubmitted() && $form->isValid()) {
            try {
                $this->api->patch('/api/demande_soldes/' . $id, $form->getData());

                $this->addFlash('success', 'Demande corrigée.');

                return $this->redirectToRoute('demande.index');
            } catch(ApiException $e) {
                if($reponse = $this->apiExceptionHandler->handle($e, $form, 'demande.index')) {
                    return $reponse;
                }
            }
        }

        return $this->render('demande/edit.html.twig', [
            'demande' => $demande,
            'form' => $form,
        ], new Response(status: $form->isSubmitted() && !$form->isValid() ? self::EN_ERREUR : Response::HTTP_OK));
    }

    /*
        - L'opérateur retire sa demande devenue sans objet — une dotation directe est arrivée entre-temps, ou il s'est trompé de poste. Sans elle, il faut mobiliser un administrateur pour rejeter une demande que plus personne ne veut, et le poste reste bloqué pour en déposer une autre.
    */
    #[IsGranted('DEMANDESOLDE_MODIFIER')] // Annuler sa demande, c'est encore la modifier : l'API pose le même droit
    #[Route('/{id<\d+>}/annulation', name: 'annuler', methods: ['POST'])]
    public function annuler(int $id, Request $request): Response
    {
        if(!$this->jetonValide($request)) {
            $this->addFlash('danger', self::JETON_INVALIDE);
        } else {
            try {
                $this->api->patch('/api/demande_soldes/' . $id . '/annulation', []);
                $this->addFlash('success', 'Demande annulée. Le pont bascule peut en déposer une nouvelle.');
            } catch(ApiException $e) {
                $this->addFlash('danger', $e->getMessage());
            }
        }

        return $this->redirectToRoute('demande.index');
    }

    /*
        - Les deux issues au même endroit : elles portent sur le même objet et s'excluent. Approuver DÉPLACE DE L'ARGENT — le solde de l'entreprise est affiché à côté pour décider en connaissance de cause.
    */
    #[Route('/{id<\d+>}/traitement', name: 'traiter', methods: ['GET', 'POST'])]
    #[IsGranted('DEMANDESOLDE_TRAITER')]
    public function traiter(int $id, Request $request): Response
    {
        $demande = $this->api->item('/api/demande_soldes/' . $id);

        $form = $this->createForm(TraiterDemandeFormType::class, [
            'decision' => 'approbation',
            'montantAccorde' => $demande['montantDemande'] ?? null, // Pré-rempli au montant demandé : c'est le cas courant
        ]);
        $form->handleRequest($request);

        if($form->isSubmitted() && $form->isValid()) {
            $saisie = $form->getData();
            $rejet = $saisie['decision'] === 'rejet';

            try {
                if($rejet) {
                    $this->api->patch('/api/demande_soldes/' . $id . '/rejet', ['motifRejet' => $saisie['motifRejet']]);
                    $this->addFlash('success', 'Demande rejetée.');
                } else {
                    $this->api->patch('/api/demande_soldes/' . $id . '/approbation', ['montantAccorde' => $saisie['montantAccorde']]);
                    $this->addFlash('success', 'Demande approuvée, la caisse est dotée.');
                }

                return $this->redirectToRoute('demande.index');
            } catch(ApiException $e) {
                /*
                    - Le motif de rejet manquant et le solde insuffisant remontent tous deux de l'API : c'est elle qui fait autorité, et son message est plus précis que tout ce qu'on redirait ici.
                */
                if($reponse = $this->apiExceptionHandler->handle($e, $form, 'demande.index')) {
                    return $reponse;
                }
            }
        }

        return $this->render('demande/traiter.html.twig', [
            'demande' => $demande,
            'entreprise' => $this->api->item('/api/me/entreprise'),
            'form' => $form,
        ], new Response(status: $form->isSubmitted() && !$form->isValid() ? self::EN_ERREUR : Response::HTTP_OK));
    }
}

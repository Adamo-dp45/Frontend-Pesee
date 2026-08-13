<?php

namespace App\Controller;

use App\Domain\Helper\ApiHelper;
use App\Domain\Helper\ApiExceptionHandlerHelper;
use App\Domain\Helper\TableHelper;
use App\Domain\Service\ExportService;
use App\Form\PaiementFormType;
use App\Security\Exception\ApiException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/*
    - Les versements aux planteurs, par mobile money.

    - Un versement porte toujours sur une pesée précise, et une pesée peut être payée en plusieurs
      fois : c'est ce qui fait passer son règlement de « non payée » à « partielle » puis « soldée ».

    - Rien n'est automatique : même quand le numéro du planteur arrive avec la pesée, c'est un
      opérateur qui déclenche le versement, le planteur devant lui.
*/
#[Route('/paiements', name: 'paiement.')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
final class PaiementController extends GestionController
{
    /* Liste blanche des tris : un tri hors de celle de l'API la ferait répondre en erreur, ou pire, l'ignorerait en laissant croire au classement demandé. */
    private const TRIS = ['createdAt', 'montant'];

    /* 'clé du formulaire' => 'filtre de l'API'. La recherche porte sur la référence : c'est elle qu'on relit sur un reçu. */
    private const FILTRES = ['recherche' => 'reference', 'statut' => 'statut', 'site' => 'site'];

    private const RESEAUX = ['ORANGE', 'MTN', 'MOOV', 'WAVE'];

    public function __construct(
        private readonly ApiHelper $api,
        private readonly TableHelper $table,
        private readonly ApiExceptionHandlerHelper $apiExceptionHandler,
        private readonly ExportService $export
    )
    {
    }

    #[Route('', name: 'index', methods: ['GET'])]
    #[IsGranted('PAIEMENT_VOIR')]
    public function index(Request $request): Response
    {
        $donnees = $this->table->handleIndex('/api/paiements', $request->query->all(), self::FILTRES, self::TRIS);

        /* Chaque ligne porte l'URL de sa pesée, composée ici : React n'en fabrique aucune, le routeur reste la seule source des chemins. */
        $donnees['items'] = array_map(fn(array $paiement): array => $paiement + [
            'urlPesee' => isset($paiement['operation']['id'])
                ? $this->generateUrl('pesee.voir', ['id' => $paiement['operation']['id']])
                : null,
            'urlRecu' => $this->generateUrl('paiement.recu', ['id' => $paiement['id']]),
        ], $donnees['items']);

        return $this->render('paiement/index.html.twig', $donnees + [
            'sites' => $this->options($this->api->collection('/api/sites', ['itemsPerPage' => 100]), 'libellesite'),
        ]);
    }

    /*
        - Verser sur une pesée. L'écran affiche ce qui reste réellement dû : le montant déjà versé,
          mais aussi celui des versements en cours, sinon on peut engager deux fois la même somme
          en attendant la confirmation du premier.
    */
    #[Route('/nouveau/{operation<\d+>}', name: 'new', methods: ['GET', 'POST'])]
    #[IsGranted('PAIEMENT_PAYER')]
    public function new(int $operation, Request $request): Response
    {
        $pesee = $this->api->item('/api/operations/' . $operation);

        /*
            - Le numéro et le réseau sont pré-remplis avec ceux du planteur : c'est le cas courant, et
              les retaper devant lui est le meilleur moyen de se tromper d'un chiffre. Ils restent
              modifiables — un planteur peut donner un autre numéro le jour même.
        */
        $form = $this->createForm(PaiementFormType::class, [
            /*
                - Le reste se calcule sur les versements CONFIRMÉS, les seuls que l'API expose ici. Ceux qui sont en vol n'y figurent pas : l'API les décomptera et refusera un montant trop élevé même s'il semble tenir à l'écran. C'est voulu — on ne peut pas engager deux fois la même somme.
            */
            'montant' => max(0, ($pesee['montantdu'] ?? 0) - ($pesee['montantpaye'] ?? 0)),
            'numeroDestinataire' => $pesee['fournisseur']['contact1'] ?? null,
            'reseau' => $pesee['fournisseur']['reseau'] ?? null,
        ], ['reseaux' => self::RESEAUX]);
        $form->handleRequest($request);

        if($form->isSubmitted() && $form->isValid()) {
            try {
                $this->api->post('/api/paiements', $form->getData() + ['operationId' => $operation]);

                $this->addFlash('success', 'Versement engagé. Il sera confirmé par la passerelle.');

                return $this->redirectToRoute('paiement.index');
            } catch(ApiException $e) {
                // Remet les violations de l'API sous les champs concernés, et renvoie une Response ou null
                if($reponse = $this->apiExceptionHandler->handle($e, $form, 'paiement.index')) {
                    return $reponse;
                }
            }
        }

        return $this->render('paiement/new.html.twig', [
            'pesee' => $pesee,
            'form' => $form,
            'caisse' => $this->caisseDuPoste($pesee['site']['id'] ?? null),
        ], new Response(status: $form->isSubmitted() && !$form->isValid() ? self::EN_ERREUR : Response::HTTP_OK));
    }


    /*
        - L'état de la caisse du poste d'où partira le versement. C'est le chiffre qui manque le plus
          sur cet écran : sans lui, l'opérateur saisit un montant, valide, et découvre seulement
          alors que le poste n'a pas de quoi payer.

        - On prend la ligne de '/api/stats/caisse' plutôt que la fiche du site, parce qu'elle porte
          aussi le montant IMMOBILISÉ — ce qui est déjà sorti pour des versements en vol. Le solde
          brut, seul, promettrait un argent déjà engagé.

        - L'appel est gardé : tous les rôles qui peuvent payer voient la caisse, mais une 403 non
          gardée serait traitée comme une session perdue et renverrait à l'écran de connexion.

        @return array{siteId: int, site: string, solde: int, immobilise: int, operateur: string|null}|null
    */
    private function caisseDuPoste(?int $siteId): ?array
    {
        if($siteId === null || !$this->isGranted('MOUVEMENTCAISSE_VOIR')) {
            return null;
        }

        foreach($this->api->item('/api/stats/caisse')['parSite'] ?? [] as $ligne) {
            if(($ligne['siteId'] ?? null) === $siteId) {
                return $ligne;
            }
        }

        return null; // Poste hors du périmètre de la trésorerie : l'écran s'en passe plutôt que de mentir
    }

    /*
        - Le reçu remis au planteur. Généré à la demande et jamais stocké : il ne fait que remettre en
          forme ce que porte l'API, et le régénérer donne le même document.
        - Ouvert dans un onglet ('INLINE') plutôt que téléchargé : sur un poste de terrain, on imprime
          directement depuis le lecteur.
    */
    #[Route('/{id<\d+>}/recu.pdf', name: 'recu', methods: ['GET'])]
    #[IsGranted('PAIEMENT_VOIR')]
    public function recu(int $id): Response
    {
        return $this->export->recu($this->api->item('/api/paiements/' . $id));
    }
}

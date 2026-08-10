<?php

namespace App\Controller;

use App\Domain\Helper\ApiHelper;
use App\Domain\Helper\TableHelper;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/*
    - La fiche d'une pesée : ce que le poste a enregistré, ce qu'elle vaut, et où en est son
      règlement.

    - Elle porte son échéancier, parce qu'une pesée se règle en plusieurs fois : c'est le seul
      endroit où l'on voit d'un coup ce qui a été versé, ce qui est encore en vol chez la
      passerelle, et ce qui reste réellement dû.

    - Pas de liste ici : c'est le bilan, avec ses filtres calqués sur l'écran du poste.
*/
#[Route('/pesees', name: 'pesee.')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
final class PeseeController extends GestionController
{
    /* Liste blanche des tris : un tri hors de celle de l'API la ferait répondre en erreur, ou pire, l'ignorerait en laissant croire au classement demandé. */
    private const TRIS = ['peseeAt2', 'montantdu', 'numticket'];

    /* 'clé du formulaire' => 'filtre de l'API'. */
    private const FILTRES = ['recherche' => 'numticket', 'site' => 'site', 'fournisseur' => 'fournisseur'];

    public function __construct(
        private readonly ApiHelper $api,
        private readonly TableHelper $table
    )
    {
    }

    /*
        - Ce qu'il reste à payer, et rien d'autre. L'opérateur a le planteur devant lui : il ne va pas
          dérouler les dix filtres du bilan, qui est un écran d'analyse. Ici la liste est bornée
          d'avance aux pesées TERMINÉES et NON SOLDÉES de son périmètre.

        - Les deux bornes sont des filtres FIXES : elles définissent l'écran, elles ne se décochent pas.
          Une pesée soldée ou encore sur le pont n'a rien à régler.
    */
    #[Route('/a-payer', name: 'a_payer', methods: ['GET'])]
    #[IsGranted('PAIEMENT_PAYER')] // L'écran n'existe que pour verser : le consulter sans pouvoir payer n'a pas de sens
    public function aPayer(Request $request): Response
    {
        $donnees = $this->table->handleRelated(
            '/api/operations',
            $request->query->all(),
            /*
                - Un filtre à plusieurs valeurs se passe en TABLEAU sous la clé simple, jamais sous
                  'statutReglement[]' : le client HTTP échappe les crochets de la clé puis ajoute ses
                  propres indices, et l'API reçoit un paramètre qu'elle ne reconnaît pas. Elle
                  l'ignore alors en silence — la liste rendait aussi les pesées déjà soldées.
            */
            ['statut' => 'TERMINEE', 'statutReglement' => ['NON_PAYE', 'PARTIEL']],
            self::FILTRES,
            self::TRIS
        );

        /* Chaque ligne porte ses URL, composées ici : React n'en fabrique aucune, le routeur reste la seule source des chemins. */
        $donnees['items'] = array_map(fn(array $pesee): array => $pesee + [
            'url' => $this->generateUrl('pesee.voir', ['id' => $pesee['id']]),
            'urlPaiement' => $this->generateUrl('paiement.new', ['operation' => $pesee['id']]),
        ], $donnees['items']);

        return $this->render('pesee/a_payer.html.twig', $donnees + [
            'sites' => $this->options($this->api->collection('/api/sites', ['itemsPerPage' => 100]), 'libellesite'),
        ]);
    }

    #[Route('/{id<\d+>}', name: 'voir', methods: ['GET'])]
    #[IsGranted('OPERATION_VOIR')]
    public function voir(int $id): Response
    {
        $pesee = $this->api->item('/api/operations/' . $id);

        // Les versements complets : la pesée n'en porte que les références
        $paiements = $this->api->collection('/api/paiements', [
            'operation' => $id,
            'itemsPerPage' => 50,
            'order[createdAt]' => 'desc',
        ]);

        return $this->render('pesee/voir.html.twig', [
            'pesee' => $pesee,
            'paiements' => $paiements,
            'enVol' => $this->enVol($paiements),
            'payable' => $this->isGranted('PAIEMENT_PAYER'),
        ]);
    }

    /*
        - Ce qui a quitté la caisse sans être confirmé par la passerelle. Sans ce chiffre, la fiche
          affiche « 0 F versé » alors qu'un versement est parti : on croit pouvoir engager la
          totalité, et l'API refuse.
    */
    /**
     * @param list<array<string, mixed>> $paiements
     */
    private function enVol(array $paiements): int
    {
        $total = 0;

        foreach ($paiements as $versement) {
            if (in_array($versement['statut'] ?? '', ['EN_ATTENTE', 'ENVOYE'], true)) {
                $total += (int) ($versement['montant'] ?? 0);
            }
        }

        return $total;
    }
}

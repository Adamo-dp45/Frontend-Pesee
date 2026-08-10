<?php

namespace App\Controller;

use App\Domain\Builder\TableQueryBuilder;
use App\Domain\Helper\ApiHelper;
use App\Domain\Helper\Periode;
use App\Domain\Helper\TableHelper;
use App\Domain\Service\ExportService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/*
    - La page Bilan : la liste filtrable des pesées, et ses exports.

    - Les filtres reprennent ceux de l'écran « BILAN STANDARD » du logiciel de pesée, pour que les
      utilisateurs retrouvent ce qu'ils connaissent. S'y ajoute le pont bascule, qui n'existe pas
      côté poste puisque chacun ne voit que le sien.

    - L'état de la page vit dans l'URL, pas dans du JavaScript : un filtre appliqué se partage par
      copier-coller, se met en favori, et le bouton retour du navigateur fonctionne.
*/
#[Route('/bilan', name: 'bilan.')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
final class BilanController extends AbstractController
{
    /*
        - Liste blanche des tris, calquée sur l'`OrderFilter` de la ressource. Laisser passer un tri inconnu ferait répondre l'API en erreur, ou pire, l'ignorerait en laissant croire au classement demandé.
    */
    private const TRIS = ['peseeAt2', 'poidsnet', 'montantdu', 'numticket'];

    /*
        - 'clé du formulaire' => 'filtre de l'API'. Les sept premiers sont les champs libres de l'écran « BILAN STANDARD », dans l'ordre où ils y figurent.
        - 'vehicule' s'appelle 'immatriculation' côté API : c'est le nom de la colonne. La table de correspondance porte la traduction, il n'y a nulle part ailleurs à y penser.
    */
    private const FILTRES = [
        'recherche' => 'numticket', // Le geste le plus courant : l'opérateur a le ticket papier en main et tape son numéro
        'mouvement' => 'mouvement',
        'destination' => 'destination',
        'provenance' => 'provenance',
        'client' => 'client',
        'transporteur' => 'transporteur',
        'chauffeur' => 'chauffeur',
        'vehicule' => 'immatriculation',
        'site' => 'site',
        'produit' => 'produit',
        'fournisseur' => 'fournisseur',
        'statutReglement' => 'statutReglement',
    ];

    public function __construct(
        private readonly ApiHelper $api,
        private readonly TableHelper $table,
        private readonly TableQueryBuilder $tableQuery,
        private readonly ExportService $export
    )
    {
    }

    #[Route('', name: 'index', methods: ['GET'])]
    #[IsGranted('OPERATION_VOIR')]
    public function index(Request $request): Response
    {
        $periode = Periode::depuis($request);

        /*
            - La période passe en filtre FIXE : elle est calculée par le contrôleur, pas relayée depuis l'URL, et elle ne doit pas pouvoir être écrasée par un paramètre inattendu.
        */
        $donnees = $this->table->handleRelated(
            '/api/operations',
            $request->query->all(),
            $this->bornes($periode),
            self::FILTRES,
            self::TRIS
        );

        /*
            - Chaque ligne porte ses URL, composées ici. React n'en fabrique aucune, et c'est le contrôleur qui décide si une pesée est payable : terminée et non soldée. Une pesée en cours n'a rien à régler, elle n'a pas encore de poids net.
        */
        $donnees['items'] = array_map(function(array $pesee): array {
            $reste = ($pesee['montantdu'] ?? 0) - ($pesee['montantpaye'] ?? 0);

            return $pesee + [
                'url' => $this->generateUrl('pesee.voir', ['id' => $pesee['id']]),
                'urlPaiement' => ($pesee['statut'] ?? '') === 'TERMINEE' && $reste > 0
                    ? $this->generateUrl('paiement.new', ['operation' => $pesee['id']])
                    : null,
            ];
        }, $donnees['items']);

        return $this->render('bilan/index.html.twig', $donnees + [
            'periode' => $periode,
            // Les totaux ne portent QUE la période : un agrégat filtré ligne à ligne ne serait plus un total
            'production' => $this->api->item('/api/stats/production', $periode->versParametres()),
            'referentiels' => $this->api->item('/api/stats/referentiels'),
            'sites' => $this->options($this->api->collection('/api/sites', ['itemsPerPage' => 100])),
            'payable' => $this->isGranted('PAIEMENT_PAYER'),
        ]);
    }

    /*
        - Exports SERVEUR, et c'est le point important : ils repaginent l'API pour sortir l'intégralité du résultat filtré. L'ancien code exportait en lisant le tableau HTML affiché, donc uniquement la page en cours — un bilan de trois mille pesées produisait un fichier de vingt-cinq lignes.
        - Ils reçoivent les MÊMES paramètres que la liste, construits par le même code : un export ne peut pas diverger de ce qui est à l'écran.
    */
    #[Route('/export.xlsx', name: 'export_excel', methods: ['GET'])]
    #[IsGranted('OPERATION_VOIR')]
    public function exportExcel(Request $request): Response
    {
        $periode = Periode::depuis($request);

        return $this->export->excel($this->requete($request, $periode), $periode);
    }

    #[Route('/export.pdf', name: 'export_pdf', methods: ['GET'])]
    #[IsGranted('OPERATION_VOIR')]
    public function exportPdf(Request $request): Response
    {
        $periode = Periode::depuis($request);
        // « Imprimer » ou « Imprimer avec gain », comme sur le poste
        $avecMontants = $request->query->getBoolean('gain');

        return $this->export->pdf($this->requete($request, $periode), $periode, $avecMontants);
    }

    /** Les paramètres d'API du résultat filtré, identiques à ceux de la liste. */
    private function requete(Request $request, Periode $periode): array
    {
        return $this->tableQuery->buildParams($request->query->all(), self::FILTRES, self::TRIS) + $this->bornes($periode);
    }

    /** @return array<string, string> */
    private function bornes(Periode $periode): array
    {
        // Les bornes couvrent la journée entière, comme le fait l'écran « Bilan standard » du poste
        return [
            'peseeAt2[after]' => $periode->du,
            'peseeAt2[before]' => $periode->au . ' 23:59:59',
        ];
    }

    /** @return array<int, string> 'id du site' => 'libellé', pour le filtre déroulant */
    private function options(array $sites): array
    {
        $options = [];

        foreach($sites as $site) {
            $options[(int) $site['id']] = (string) $site['libellesite'];
        }

        return $options;
    }
}

<?php

namespace App\Controller\Admin;

use App\Controller\GestionController;
use App\Domain\Helper\ApiHelper;
use App\Security\Exception\ApiException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/*
    - La corbeille, tous clients confondus. Réservée au super administrateur, et c'est le seul écran
      de l'application dans ce cas : restaurer ou purger un référentiel touche à l'historique des
      pesées d'un client entier. C'est un geste de dépannage — celui qu'on demande à qui a mis les
      postes en service, pas un bouton à portée de clic dans l'écran quotidien.

    - Deux types y entrent, produits et planteurs : ce sont les seuls que quoi que ce soit mette à la
      corbeille. Les pesées, les paiements et le grand livre portent bien un 'deletedAt' par héritage,
      mais rien ne le renseigne jamais — ce sont des historiques, pas des brouillons.
*/
#[Route('/admin/corbeille', name: 'admin.corbeille.')]
#[IsGranted('ROLE_SUPER_ADMIN')]
final class CorbeilleController extends GestionController
{
    public function __construct(
        private readonly ApiHelper $api
    )
    {
    }

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $filtres = array_filter([
            'type' => $this->ouNull($request->query->get('type')),
            'entreprise' => $this->entierOuNull($request->query->get('entreprise')),
        ], static fn(mixed $valeur) => $valeur !== null);

        $corbeille = $this->api->item('/api/corbeille', $filtres);

        /*
            - La corbeille n'est PAS une collection paginée : l'API rend un objet — des compteurs, une
              liste plafonnée, et pour chaque ligne si elle est restaurable ou purgeable. On ne peut
              donc pas passer par 'TableHelper', qui attend une page d'API Platform.
            - Les métadonnées sont composées ici pour que la table React s'affiche comme les autres.
              Ce n'est pas un faux-semblant : tout tient réellement sur une page.
        */
        $elements = $corbeille['elements'] ?? [];
        $total = count($elements);

        return $this->render('admin/corbeille/index.html.twig', [
            'corbeille' => $corbeille,
            'items' => array_map(fn(array $element): array => $element + [
                'urlRestauration' => $this->generateUrl('admin.corbeille.restaurer', ['type' => $element['type'], 'id' => $element['id']]),
                'urlPurge' => $this->generateUrl('admin.corbeille.purger', ['type' => $element['type'], 'id' => $element['id']]),
            ], $elements),
            'meta' => [
                'total' => $total,
                'page' => 1,
                'perPage' => max(1, $total),
                'totalPages' => 1,
                'from' => $total > 0 ? 1 : 0,
                'to' => $total,
            ],
            'queryParams' => $request->query->all(),
            'entreprises' => $corbeille['entreprises'] ?? [],
        ]);
    }

    /*
        - Remettre en service. L'API refuse si un homonyme vivant est né entre-temps sur le même pont
          bascule : la liste l'annonce déjà, mais un écran ouvert depuis dix minutes peut avoir vieilli.
    */
    #[Route('/{type}/{id<\d+>}/restauration', name: 'restaurer', methods: ['POST'])]
    public function restaurer(string $type, int $id, Request $request): Response
    {
        return $this->agir($request, function () use ($type, $id): string {
            $this->api->patch(sprintf('/api/corbeille/%s/%d/restauration', $type, $id), []);

            return 'Élément restauré. Il réapparaît dans les écrans de son entreprise.';
        });
    }

    /*
        - Suppression définitive : le seul geste de l'application qui efface réellement une ligne.
          Partout ailleurs on marque, on n'efface pas. L'API la refuse tant qu'une pesée référence
          l'élément — sans quoi le montant survivrait sans son référentiel.
    */
    #[Route('/{type}/{id<\d+>}', name: 'purger', methods: ['POST'])]
    public function purger(string $type, int $id, Request $request): Response
    {
        return $this->agir($request, function () use ($type, $id): string {
            $this->api->delete(sprintf('/api/corbeille/%s/%d', $type, $id));

            return 'Élément supprimé définitivement. Il ne peut plus être récupéré.';
        });
    }

    /** Le tronc commun des deux gestes : jeton, appel, message, retour à la liste. */
    private function agir(Request $request, callable $action): Response
    {
        if (!$this->jetonValide($request)) {
            $this->addFlash('danger', self::JETON_INVALIDE);
        } else {
            try {
                $this->addFlash('success', $action());
            } catch (ApiException $e) {
                // Le refus de l'API porte son motif — homonyme vivant, pesées rattachées — on le relaie tel quel
                $this->addFlash('danger', $e->getMessage());
            }
        }

        return $this->redirectToRoute('admin.corbeille.index');
    }
}

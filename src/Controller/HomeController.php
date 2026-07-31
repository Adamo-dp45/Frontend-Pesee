<?php

namespace App\Controller;

use App\Domain\Helper\ApiExceptionHandlerHelper;
use App\Domain\Helper\ApiHelper;
use App\Security\Exception\ApiException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('IS_AUTHENTICATED_FULLY')]
final class HomeController extends AbstractController
{
    public function __construct(
        private readonly ApiHelper $api,
        private readonly ApiExceptionHandlerHelper $apiExceptionHandler
    )
    {
    }

    #[Route('/', name: 'home', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $dateDebut = $request->query->get('date_debut', new \DateTime('first day of this month')->format('Y-m-d'));
        $dateFin = $request->query->get('date_fin', new \DateTime('last day of this month')->format('Y-m-d'));

        try {
            $data = $this->api->item('/api/dashboard', [
                'date_debut' => $dateDebut,
                'date_fin' => $dateFin
            ]);
        } catch(ApiException $e) {
            $response = $this->apiExceptionHandler->handle($e);
            if($response) {
                return $response;
            }
        }

        return $this->render('home/index.html.twig', [
            'data' => $data,
            'date_debut' => $dateDebut,
            'date_fin' => $dateFin
        ]);
    }


    #[Route('/bilan', name: 'bilan', methods: ['GET'])]
    public function bilan(Request $request): Response
    {
        if(!$this->isGranted('ROLE_OPERATEUR') && !$this->isGranted('ROLE_AGENT') && !$this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException();
        }

        try {
            $sites = $this->api->collection('/api/sites');
        } catch(ApiException $e) {
            $response = $this->apiExceptionHandler->handle($e);
            if($response) {
                return $response;
            }
        }

        $filtres = array_filter([
            'page' => $request->query->get('page', 1),
            'limit' => $request->query->get('limit', 20),
            'datepesee1' => $request->query->get('date_debut'),
            'datepesee2' => $request->query->get('date_fin'),
            'code' => $request->query->get('code'),
            'produit' => $request->query->get('produit'),
            'transporteur' => $request->query->get('transporteur'),
            'client' => $request->query->get('client'),
            'fournisseur' => $request->query->get('fournisseur'),
            'mouvement' => $request->query->get('mouvement'),
            'destination' => $request->query->get('destination'),
            'provenance' => $request->query->get('provenance'),
            'immatriculation'=> $request->query->get('immatriculation')
        ]);

        $result = $this->api->getOperations($filtres);
        $referentiels = [];
        $codeSelectionne = $filtres['code'] ?? null; /*
            - Si un site est sélectionné, on pré-charge les référentiels  pour que les selects soient déjà peuplés au chargement
        */
        if($codeSelectionne) {
            $types = ['mouvement', 'client', 'fournisseur', 'transporteur', 'produit', 'destination', 'provenance', 'vehicule'];
            foreach($types as $type) {
                $referentiels[$type] = $this->api->getReferentiel($type, $codeSelectionne);
            }
        }

        return $this->render('home/bilan.html.twig', [
            'sites' => $sites,
            'operations' => $result['data'] ?? [],
            'pagination' => $result['pagination'] ?? [],
            'filtres' => $filtres,
            'referentiels' => $referentiels,
            'codeSelectionne' => $codeSelectionne
        ]);
    }
}

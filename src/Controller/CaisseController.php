<?php

namespace App\Controller;

use App\Domain\Helper\ApiHelper;
use App\Domain\Helper\ApiExceptionHandlerHelper;
use App\Domain\Helper\TableHelper;
use App\Form\RechargeFormType;
use App\Security\Exception\ApiException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/*
    - La caisse à deux niveaux : l'entreprise détient un solde global, elle en dote ses ponts
      bascules, et c'est depuis la caisse d'un site que les planteurs sont payés.

    - Le grand livre est la pièce maîtresse. Chaque écriture porte le solde avant et après :
      deux lignes qui se suivent doivent se chaîner. C'est ce qui rend une erreur détectable.

    - Le montant immobilisé explique l'écart entre ce qu'un opérateur croit avoir et ce qu'il peut
      réellement engager : des versements sont sortis de la caisse sans être encore confirmés.
*/
#[Route('/caisse', name: 'caisse.')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
final class CaisseController extends GestionController
{
    /* Liste blanche des tris : un tri hors de celle de l'API la ferait répondre en erreur, ou pire, l'ignorerait en laissant croire au classement demandé. */
    private const TRIS = ['createdAt', 'montant'];

    /*
        - 'clé du formulaire' => 'filtre de l'API'. Le filtre entrées/sorties s'appelle 'mouvement' et non 'sens' : ce dernier porte déjà le sens du TRI. Les confondre faisait qu'un clic sur un en-tête envoyait 'sens=desc' à l'API, qui l'attend en CREDIT ou DEBIT — et la liste se vidait.
    */
    private const FILTRES = ['compte' => 'compte', 'mouvement' => 'sens', 'site' => 'site'];

    public function __construct(
        private readonly ApiHelper $api,
        private readonly TableHelper $table,
        private readonly ApiExceptionHandlerHelper $apiExceptionHandler
    )
    {
    }

    #[Route('', name: 'index', methods: ['GET'])]
    #[IsGranted('MOUVEMENTCAISSE_VOIR')]
    public function index(Request $request): Response
    {
        $donnees = $this->table->handleIndex('/api/mouvement_caisses', $request->query->all(), self::FILTRES, self::TRIS);

        return $this->render('caisse/index.html.twig', $donnees + [
            'caisse' => $this->api->item('/api/stats/caisse'),
            'sites' => $this->options($this->api->collection('/api/sites', ['itemsPerPage' => 100]), 'libellesite'),
            'rechargeable' => $this->isGranted('ENTREPRISE_RECHARGER'),
            // Doter part de la fiche du poste : sans le droit, la colonne n'y mène que pour consulter
            'dotable' => $this->isGranted('SITE_ATTRIBUER'),
        ]);
    }

    /*
        - Recharge du solde de l'entreprise : une entrée d'argent venue de l'extérieur. Saisie à la
          main par l'administrateur, et historisée comme tout le reste.
        - Le super administrateur n'y a pas accès : il gère les clients, pas leur argent.
    */
    #[Route('/recharge', name: 'recharge', methods: ['GET', 'POST'])]
    #[IsGranted('ENTREPRISE_RECHARGER')]
    public function recharge(Request $request): Response
    {
        $form = $this->createForm(RechargeFormType::class);
        $form->handleRequest($request);

        if($form->isSubmitted() && $form->isValid()) {
            try {
                $this->api->post('/api/me/entreprise/recharge', $form->getData());

                $this->addFlash('success', 'Solde rechargé.');

                return $this->redirectToRoute('caisse.index');
            } catch(ApiException $e) {
                // Remet les violations de l'API sous les champs concernés, et renvoie une Response ou null
                if($reponse = $this->apiExceptionHandler->handle($e, $form, 'caisse.index')) {
                    return $reponse;
                }
            }
        }

        return $this->render('caisse/recharge.html.twig', [
            'entreprise' => $this->api->item('/api/me/entreprise'),
            'form' => $form,
        ], new Response(status: $form->isSubmitted() && !$form->isValid() ? self::EN_ERREUR : Response::HTTP_OK));
    }
}

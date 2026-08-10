<?php

namespace App\Controller\Admin;

use App\Controller\GestionController;
use App\Domain\Helper\ApiHelper;
use App\Domain\Helper\ApiExceptionHandlerHelper;
use App\Domain\Helper\TableHelper;
use App\Form\EntrepriseFormType;
use App\Form\InscriptionFormType;
use App\Security\Exception\ApiException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/*
    - Les clients, vus par le super administrateur. Son périmètre s'arrête là : il provisionne les entreprises, leurs ponts bascules et leurs comptes, mais n'a accès ni aux pesées, ni à la caisse, ni aux statistiques. Ce n'est pas son argent.

    - Désactiver une entreprise coupe l'accès de tous ses comptes d'un coup. Rien n'est supprimé : l'historique reste, et la réactivation rend tout le monde à son travail.
*/
/*
    - 'ROLE_SUPER_ADMIN' au niveau de la classe, et non 'ENTREPRISE_VOIR' : c'est une SECTION, pas une
      permission. L'administrateur d'une entreprise a bien le droit de voir la sienne — mais depuis
      sa caisse et son profil, pas depuis l'écran qui liste les clients de la plateforme. Chaque
      action porte en plus son droit, pour que la table de l'API reste la référence.
*/
#[Route('/admin/entreprises', name: 'admin.entreprise.')]
#[IsGranted('ROLE_SUPER_ADMIN')]
final class EntrepriseController extends GestionController
{
    /* Liste blanche des tris : un tri hors de celle de l'API la ferait répondre en erreur, ou pire, l'ignorerait en laissant croire au classement demandé. */
    private const TRIS = ['nom', 'createdAt'];

    /* 'clé du formulaire' => 'filtre de l'API'. */
    private const FILTRES = ['recherche' => 'nom', 'statut' => 'statut'];

    public function __construct(
        private readonly ApiHelper $api,
        private readonly TableHelper $table,
        private readonly ApiExceptionHandlerHelper $apiExceptionHandler
    )
    {
    }

    #[Route('', name: 'index', methods: ['GET'])]
    #[IsGranted('ENTREPRISE_VOIR')]
    public function index(Request $request): Response
    {
        $donnees = $this->table->handleIndex('/api/entreprises', $request->query->all(), self::FILTRES, self::TRIS);

        /* Chaque ligne porte ses URL, composées ici : React n'en fabrique aucune, le routeur reste la seule source des chemins. */
        $donnees['items'] = array_map(fn(array $entreprise): array => $entreprise + [
            'url' => $this->generateUrl('admin.entreprise.edit', ['id' => $entreprise['id']]),
            'urlActivation' => $this->generateUrl('admin.entreprise.activation', ['id' => $entreprise['id']]),
        ], $donnees['items']);

        return $this->render('admin/entreprise/index.html.twig', $donnees + [
            'creable' => $this->isGranted('ENTREPRISE_CREER'),
            'modifiable' => $this->isGranted('ENTREPRISE_MODIFIER'),
            'blocable' => $this->isGranted('ENTREPRISE_BLOQUER'),
        ]);
    }

    /*
        - L'inscription d'un client : l'entreprise ET son premier administrateur en un seul geste.
          C'est le parcours normal, et la seule façon d'obtenir un client immédiatement utilisable —
          'new()' ci-dessous ne crée que l'entreprise, sans personne pour y entrer.

        - Le compte engendré est marqué FONDATEUR par l'API. Ce n'est pas décoratif : ses pairs ne
          pourront ni le suspendre ni le rétrograder, ce qui évite qu'une entreprise se verrouille
          hors de son propre outil.

        - L'écran est réservé au super administrateur par le garde de la classe. L'endpoint de l'API,
          lui, reste PUBLIC : il est prévu pour une inscription en libre-service, et c'est ce qui
          explique qu'il soit limité en débit par adresse IP.
    */
    #[Route('/inscription', name: 'register', methods: ['GET', 'POST'])]
    #[IsGranted('ENTREPRISE_CREER')]
    public function inscription(Request $request): Response
    {
        $form = $this->createForm(InscriptionFormType::class);
        $form->handleRequest($request);

        if($form->isSubmitted() && $form->isValid()) {
            try {
                // Les clés du formulaire sont celles de 'RegisterInput' : rien à remapper
                $fondateur = $this->api->post('/api/register', $form->getData());

                $this->addFlash('success', sprintf(
                    '%s est inscrite. %s peut se connecter avec %s.',
                    $fondateur['entreprise']['nom'] ?? 'L\'entreprise',
                    $fondateur['nomComplet'] ?? 'Son administrateur',
                    $fondateur['email'] ?? 'l\'adresse saisie'
                ));

                return $this->redirectToRoute('admin.entreprise.index');
            } catch(ApiException $e) {
                // Remet les violations de l'API sous les champs concernés, et renvoie une Response ou null
                if($reponse = $this->apiExceptionHandler->handle($e, $form, 'admin.entreprise.index')) {
                    return $reponse;
                }
            }
        }

        return $this->render('admin/entreprise/inscription.html.twig', ['form' => $form], new Response(
            status: $form->isSubmitted() && !$form->isValid() ? self::EN_ERREUR : Response::HTTP_OK
        ));
    }

    /*
        - Modification de l'identité d'un client. Ni son code, ni son solde : le premier sert de
          référence partout ailleurs, le second ne se pose pas, il résulte des mouvements de caisse.
    */
    #[Route('/{id<\d+>}/modifier', name: 'edit', methods: ['GET', 'POST'])]
    #[IsGranted('ENTREPRISE_MODIFIER')]
    public function edit(int $id, Request $request): Response
    {
        $entreprise = $this->api->item('/api/entreprises/' . $id);

        $form = $this->createForm(EntrepriseFormType::class, [
            'nom' => $entreprise['nom'] ?? null,
            'adresse' => $entreprise['adresse'] ?? null,
            'contact1' => $entreprise['contact1'] ?? null,
            'contact2' => $entreprise['contact2'] ?? null,
            'email' => $entreprise['email'] ?? null,
        ]);
        $form->handleRequest($request);

        if($form->isSubmitted() && $form->isValid()) {
            try {
                $this->api->patch('/api/entreprises/' . $id, $form->getData());

                $this->addFlash('success', 'Entreprise enregistrée.');

                return $this->redirectToRoute('admin.entreprise.index');
            } catch(ApiException $e) {
                // Remet les violations de l'API sous les champs concernés, et renvoie une Response ou null
                if($reponse = $this->apiExceptionHandler->handle($e, $form, 'admin.entreprise.index')) {
                    return $reponse;
                }
            }
        }

        return $this->render('admin/entreprise/formulaire.html.twig', [
            'entreprise' => $entreprise,
            'form' => $form,
        ], new Response(status: $form->isSubmitted() && !$form->isValid() ? self::EN_ERREUR : Response::HTTP_OK));
    }

    #[Route('/{id<\d+>}/activation', name: 'activation', methods: ['POST'])]
    #[IsGranted('ENTREPRISE_BLOQUER')]
    public function activation(int $id, Request $request): Response
    {
        if (!$this->jetonValide($request)) {
            $this->addFlash('danger', self::JETON_INVALIDE);
        } else {
            try {
                $entreprise = $this->api->patch('/api/entreprises/' . $id . '/activation', []);

                $this->addFlash('success', ($entreprise['statut'] ?? '') === 'DESACTIVEE'
                    ? 'Entreprise désactivée. Tous ses comptes perdent l\'accès.'
                    : 'Entreprise réactivée.');
            } catch (ApiException $e) {
                $this->addFlash('danger', $e->getMessage());
            }
        }

        return $this->redirectToRoute('admin.entreprise.index');
    }
}

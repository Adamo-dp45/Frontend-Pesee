<?php

namespace App\Controller;

use App\Domain\Helper\ApiHelper;
use App\Domain\Helper\ApiExceptionHandlerHelper;
use App\Domain\Helper\TableHelper;
use App\Form\UtilisateurFormType;
use App\Security\Exception\ApiException;
use App\Security\UserGuard;
use App\Security\Voter\AccesVoter;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/*
    - Les comptes de l'entreprise. Les comptes machine des postes n'apparaissent pas ici : ils se
      gèrent depuis la fiche du pont bascule, et le voter de l'API les refuse de toute façon.

    - Le compte fondateur — celui qui a inscrit l'entreprise — ne peut être ni suspendu ni
      supprimé par un pair. Sans ce garde-fou, deux administrateurs peuvent se suspendre
      mutuellement et l'entreprise se verrouille hors de son propre outil.
*/
#[Route('/utilisateurs', name: 'utilisateur.')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
final class UtilisateurController extends GestionController
{
    /* Liste blanche des tris : un tri hors de celle de l'API la ferait répondre en erreur, ou pire, l'ignorerait en laissant croire au classement demandé. */
    private const TRIS = ['nom', 'email', 'createdAt'];

    /* 'clé du formulaire' => 'filtre de l'API'. */
    private const FILTRES = ['recherche' => 'nom', 'statut' => 'statut'];

    /** Un agent ne peut créer et gérer que des opérateurs. */
    private const ROLES = [
        'ROLE_ADMIN' => 'Administrateur',
        'ROLE_AGENT' => 'Agent',
        'ROLE_OPERATEUR' => 'Opérateur',
    ];

    public function __construct(
        private readonly ApiHelper $api,
        private readonly TableHelper $table,
        private readonly ApiExceptionHandlerHelper $apiExceptionHandler,
        private readonly UserGuard $userGuard
    )
    {
    }

    #[Route('', name: 'index', methods: ['GET'])]
    #[IsGranted('USER_VOIR')]
    public function index(Request $request): Response
    {
        $donnees = $this->table->handleIndex('/api/users', $request->query->all(), self::FILTRES, self::TRIS);

        $moi = $this->utilisateur();

        /*
            - Chaque ligne porte ses URL et ce qu'on a le droit d'y faire. La décision se prend ici, où l'on connaît le compte connecté : React ne calcule aucun droit, il rend ce qu'on lui donne.
            - Un fondateur ne se suspend que par le super administrateur, et personne ne se modifie ni ne se suspend soi-même — sinon deux administrateurs se verrouillent mutuellement hors de leur propre outil.
        */
        $donnees['items'] = array_map(fn(array $compte): array => $compte + [
            'url' => $this->generateUrl('utilisateur.show', ['id' => $compte['id']]),
            'urlEdit' => $this->generateUrl('utilisateur.edit', ['id' => $compte['id']]),
            'urlActivation' => $this->generateUrl('utilisateur.activation', ['id' => $compte['id']]),
            'modifiable' => $this->peut($compte, AccesVoter::MODIFIER),
            'suspendable' => $this->peut($compte, AccesVoter::BLOQUER),
            'moiMeme' => (int) ($compte['id'] ?? 0) === $moi->getId(),
        ], $donnees['items']);

        return $this->render('utilisateur/index.html.twig', $donnees + [
            'multiEntreprise' => $moi->estSuperAdmin(),
        ]);
    }

    #[IsGranted('USER_CREER')]
    #[Route('/nouveau', name: 'new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $form = $this->createForm(UtilisateurFormType::class, null, [
            'roles' => $this->rolesAttribuables(),
            'creation' => true,
        ]);
        $form->handleRequest($request);

        if($form->isSubmitted() && $form->isValid()) {
            $saisie = $form->getData();

            try {
                $this->api->post('/api/users', array_diff_key($saisie, ['role' => null]) + [
                    'roles' => [$saisie['role']], // L'API attend un tableau, le formulaire un choix unique
                ]);

                $this->addFlash('success', 'Compte créé.');

                return $this->redirectToRoute('utilisateur.index');
            } catch(ApiException $e) {
                // Remet les violations de l'API sous les champs concernés, et renvoie une Response ou null
                if($reponse = $this->apiExceptionHandler->handle($e, $form, 'utilisateur.index')) {
                    return $reponse;
                }
            }
        }

        return $this->render('utilisateur/new.html.twig', ['form' => $form], new Response(
            status: $form->isSubmitted() && !$form->isValid() ? self::EN_ERREUR : Response::HTTP_OK
        ));
    }

    /*
        - La fiche d'un compte : ce qu'il est, et les deux gestes qui le concernent — son rôle et son
          accès. Ils sont ici et non dans le formulaire d'identité, et ce n'est pas cosmétique :
          mélangés aux coordonnées, corriger un nom suffisait à changer un rôle au passage.
    */
    #[Route('/{id<\d+>}', name: 'show', methods: ['GET'])]
    #[IsGranted('USER_VOIR')]
    public function show(int $id): Response
    {
        $compte = $this->api->item('/api/users/' . $id);

        return $this->render('utilisateur/show.html.twig', [
            'compte' => $compte,
            'roles' => $this->rolesAttribuables(),
            'roleActuel' => $this->roleActuel($compte),
            'moiMeme' => (int) ($compte['id'] ?? 0) === $this->utilisateur()->getId(),
            'modifiable' => $this->peut($compte, AccesVoter::MODIFIER),
            'promouvable' => $this->peut($compte, AccesVoter::PROMOUVOIR),
            // Suspendre un fondateur est le seul recours du super administrateur quand une entreprise se verrouille : personne d'autre ne peut le faire
            'suspendable' => $this->peut($compte, AccesVoter::BLOQUER),
            // Ce que l'écran affiche quand il n'y a rien à proposer : un bouton absent sans explication ressemble à une panne
            'motif' => $this->userGuard->motifRefus($this->utilisateur(), $compte, AccesVoter::MODIFIER),
        ]);
    }

    #[Route('/{id<\d+>}/modifier', name: 'edit', methods: ['GET', 'POST'])]
    #[IsGranted('USER_MODIFIER')]
    public function edit(int $id, Request $request): Response
    {
        $compte = $this->api->item('/api/users/' . $id);

        /*
            - Le droit sur la ressource ne suffit pas : encore faut-il pouvoir agir sur CE compte. Sans
              ce renvoi, une URL tapée à la main ouvre un formulaire qui ne pourra jamais être
              enregistré, et l'API ne le dit qu'après la saisie.
        */
        if(($motif = $this->userGuard->motifRefus($this->utilisateur(), $compte, AccesVoter::MODIFIER)) !== null) {
            $this->addFlash('danger', $motif);

            return $this->redirectToRoute('utilisateur.show', ['id' => $id]);
        }

        /* Le rôle et le mot de passe ne sont pas dans ce formulaire : le premier a son propre verbe, le second se réinitialise. Absents du formulaire, ils ne peuvent pas être écrasés. */
        $form = $this->createForm(UtilisateurFormType::class, [
            'nom' => $compte['nom'] ?? null,
            'prenom' => $compte['prenom'] ?? null,
            'email' => $compte['email'] ?? null,
            'telephone' => $compte['telephone'] ?? null,
        ]);
        $form->handleRequest($request);

        if($form->isSubmitted() && $form->isValid()) {
            try {
                $this->api->patch('/api/users/' . $id, $form->getData());

                $this->addFlash('success', 'Compte enregistré.');

                return $this->redirectToRoute('utilisateur.index');
            } catch(ApiException $e) {
                if($reponse = $this->apiExceptionHandler->handle($e, $form, 'utilisateur.index')) {
                    return $reponse;
                }
            }
        }

        /* Ni rôle ni accès ici : ils vivent sur la fiche, avec leurs propres droits et leur propre verbe. */
        return $this->render('utilisateur/edit.html.twig', [
            'compte' => $compte,
            'form' => $form,
        ], new Response(status: $form->isSubmitted() && !$form->isValid() ? self::EN_ERREUR : Response::HTTP_OK));
    }

    /*
        - Changer un rôle est un acte à part : on ne se promeut pas soi-même, on ne rétrograde pas
          un fondateur, et un agent n'attribue que le rôle opérateur. L'API porte ces règles ; ici
          on se contente de lui transmettre le choix.
    */
    #[Route('/{id<\d+>}/role', name: 'role', methods: ['POST'])]
    #[IsGranted('USER_PROMOUVOIR')]
    public function role(int $id, Request $request): Response
    {
        if (!$this->jetonValide($request)) {
            $this->addFlash('danger', self::JETON_INVALIDE);
        } else {
            try {
                $this->api->patch('/api/users/' . $id . '/role', [
                    'role' => (string) $request->request->get('role'),
                ]);
                $this->addFlash('success', 'Rôle mis à jour.');
            } catch (ApiException $e) {
                $this->addFlash('danger', $e->getMessage());
            }
        }

        return $this->redirectToRoute('utilisateur.show', ['id' => $id]);
    }

    /** @param array<string, mixed> $compte */
    private function roleActuel(array $compte): string
    {
        // 'ROLE_USER' est porté par tout le monde : ce n'est pas un rôle métier
        foreach ((array) ($compte['roles'] ?? []) as $role) {
            if ($role !== 'ROLE_USER') {
                return (string) $role;
            }
        }

        return '';
    }

    /* Bascule actif / suspendu. L'effet est immédiat : l'API relit le compte à chaque requête. */
    #[Route('/{id<\d+>}/activation', name: 'activation', methods: ['POST'])]
    #[IsGranted('USER_BLOQUER')]
    public function activation(int $id, Request $request): Response
    {
        if (!$this->jetonValide($request)) {
            $this->addFlash('danger', self::JETON_INVALIDE);
        } else {
            try {
                $compte = $this->api->patch('/api/users/' . $id . '/activation', []);

                $this->addFlash('success', ($compte['statut'] ?? '') === 'SUSPENDU'
                    ? 'Compte suspendu. Sa session en cours est coupée.'
                    : 'Compte réactivé.');
            } catch (ApiException $e) {
                $this->addFlash('danger', $e->getMessage());
            }
        }

        return $this->redirectToRoute('utilisateur.index');
    }

    /*
        - Les deux questions de l'API, dans le même ordre : le rôle porte-t-il cette action sur la
          ressource « User » (la table de 'AccesVoter'), et peut-on l'exercer sur CE compte-là (la
          hiérarchie de 'UserGuard') ? Une seule des deux suffit à afficher un bouton qui échouera.

        @param array<string, mixed> $compte
    */
    private function peut(array $compte, string $action): bool
    {
        return $this->isGranted('USER_' . $action)
            && $this->userGuard->peutGerer($this->utilisateur(), $compte, $action);
    }

    /** @return array<string, string> */
    private function rolesAttribuables(): array
    {
        // Un agent gère les opérateurs, pas ses pairs ni ses supérieurs
        return $this->utilisateur()->estAgent()
            ? ['ROLE_OPERATEUR' => self::ROLES['ROLE_OPERATEUR']]
            : self::ROLES;
    }
}

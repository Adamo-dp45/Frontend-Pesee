<?php

namespace App\Controller;

use App\Domain\Helper\ApiHelper;
use App\Security\Exception\ApiException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class SecurityController extends AbstractController
{
    /* Les deux formulaires publics partagent le même identifiant de jeton, déclaré dans csrf.yaml. */
    private const JETON = 'motdepasse';
    private const JETON_INVALIDE = 'Votre formulaire a expiré, veuillez réessayer.';

    /*
        - Turbo n'affiche une réponse à un POST que si elle redirige ou porte un statut d'erreur.
          Un formulaire réaffiché en 200 restait donc muet : l'utilisateur cliquait, rien ne
          bougeait, pas même le message d'erreur.
    */
    private const FORMULAIRE_EN_ERREUR = Response::HTTP_UNPROCESSABLE_ENTITY;

    #[Route(path: '/connexion', name: 'app_login')]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        $error = $authenticationUtils->getLastAuthenticationError();
        $lastUsername = $authenticationUtils->getLastUsername();

        return $this->render('security/login.html.twig', ['last_username' => $lastUsername, 'error' => $error]);
    }

    #[Route(path: '/deconnexion', name: 'app_logout')]
    public function logout(): void
    {
        throw new \LogicException('This method can be blank - it will be intercepted by the logout key on your firewall.');
    }

    /*
        - L'API répond 202 que l'adresse existe ou non : on affiche donc toujours le même message,
          sinon ce formulaire public devient un moyen de savoir qui a un compte.
    */
    #[Route(path: '/mot-de-passe-oublie', name: 'mot_de_passe.oubli', methods: ['GET', 'POST'])]
    public function motDePasseOublie(Request $request, ApiHelper $api): Response
    {
        $email = (string) $request->request->get('email', '');
        $erreur = null;

        if ($request->isMethod('POST')) {
            if (!$this->jetonValide($request)) {
                $erreur = self::JETON_INVALIDE;
            } else {
                try {
                    $api->post('/api/mot-de-passe/oubli', [
                        'email' => $email,
                        // L'API ne connaît pas les routes du site : c'est nous qui lui donnons la cible
                        'urlReinitialisation' => $this->generateUrl(
                            'mot_de_passe.reinitialisation',
                            [],
                            UrlGeneratorInterface::ABSOLUTE_URL
                        ),
                    ]);

                    // Redirection après succès : Turbo refuse d'afficher une réponse 200 à un POST,
                    // et rafraîchir la page ne renverrait pas un second e-mail.
                    // L'adresse ne repart pas dans l'URL : elle finirait dans l'historique et les journaux.
                    return $this->redirectToRoute('mot_de_passe.oubli', ['envoye' => 1]);
                } catch (ApiException $e) {
                    $erreur = $e->getMessage();
                }
            }
        }

        return $this->render('security/mot_de_passe_oublie.html.twig', [
            'email' => $email,
            'envoye' => $request->query->getBoolean('envoye'),
            'erreur' => $erreur,
        ], new Response(status: $erreur === null ? Response::HTTP_OK : self::FORMULAIRE_EN_ERREUR));
    }

    /* Le jeton arrive en paramètre d'URL : c'est sous cette forme que l'API l'envoie par e-mail. */
    #[Route(path: '/reinitialiser-mot-de-passe', name: 'mot_de_passe.reinitialisation', methods: ['GET', 'POST'])]
    public function reinitialiserMotDePasse(Request $request, ApiHelper $api): Response
    {
        $token = (string) $request->query->get('token', '');
        $erreur = null;

        if ($request->isMethod('POST')) {
            $token = (string) $request->request->get('token', '');
            $motDePasse = (string) $request->request->get('motDePasse', '');

            if (!$this->jetonValide($request)) {
                $erreur = self::JETON_INVALIDE;
            } elseif ($motDePasse !== (string) $request->request->get('confirmation', '')) {
                $erreur = 'Les deux mots de passe ne sont pas identiques.';
            } else {
                try {
                    $api->post('/api/mot-de-passe/reinitialisation', [
                        'token' => $token,
                        'motDePasse' => $motDePasse,
                    ]);

                    $this->addFlash('success', 'Mot de passe modifié, vous pouvez vous connecter.');

                    return $this->redirectToRoute('app_login');
                } catch (ApiException $e) {
                    $erreur = $e->getMessage();
                }
            }
        }

        return $this->render('security/reinitialiser_mot_de_passe.html.twig', [
            'token' => $token,
            'erreur' => $erreur,
        ], new Response(status: $erreur === null ? Response::HTTP_OK : self::FORMULAIRE_EN_ERREUR));
    }

    private function jetonValide(Request $request): bool
    {
        return $this->isCsrfTokenValid(self::JETON, (string) $request->request->get('_csrf_token'));
    }
}

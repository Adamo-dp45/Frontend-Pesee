<?php

namespace App\Tests\Domain;

use App\Domain\Helper\ApiExceptionHandlerHelper;
use App\Security\Exception\ApiException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\RouterInterface;

/*
    - Ce helper décide de ce qu'on fait d'un refus de l'API, et chacune de ses branches répond à une
      question différente de l'utilisateur. Se tromper de branche ne casse rien de visible : ça
      change l'écran sur lequel il atterrit.

    - Le cas qui a motivé ces tests : un 403 renvoyait à la page de CONNEXION. Quelqu'un de déjà
      identifié perdait sa page, se reconnectait, et se heurtait au même refus. Le même 403 hors
      d'un 'catch' produisait pourtant, lui, une page 403 — deux écrans pour un seul refus.
*/
final class ApiExceptionHandlerHelperTest extends TestCase
{
    private const REPLI = 'produit.index';

    /*
        - Le cœur du correctif : « vous n'avez pas le droit » n'est pas « votre session a expiré ».
          Seule 'AuthenticationExpiredException', levée par 'ApiClientService' quand le
          rafraîchissement du jeton échoue, doit déconnecter.
    */
    public function testUnRefusDeDroitNeDeconnectePas(): void
    {
        $this->expectException(AccessDeniedHttpException::class);

        $this->helper()->handle(new ApiException('Accès refusé.', 403), null, self::REPLI);
    }

    /** Une ressource hors périmètre reste indiscernable d'une ressource inexistante. */
    public function testUneRessourceIntrouvableRestePerdue(): void
    {
        $this->expectException(NotFoundHttpException::class);

        $this->helper()->handle(new ApiException('Introuvable.', 404), null, self::REPLI);
    }

    /** Une API en panne n'est pas un bug du site : 502 le dit, 500 le cacherait. */
    public function testUneApiEnPanneRepondEn502(): void
    {
        try {
            $this->helper()->handle(new ApiException('Boum.', 503), null, self::REPLI);
            self::fail('Une erreur serveur aurait dû interrompre le contrôleur');
        } catch (HttpException $e) {
            self::assertSame(502, $e->getStatusCode());
        }
    }

    /*
        - Un conflit et une violation sont des SAISIES à corriger : on rend la main au contrôleur
          ('null') pour qu'il réaffiche le formulaire, saisie intacte.
    */
    public function testUnConflitRamemeAuFormulaire(): void
    {
        $form = $this->createMock(FormInterface::class);
        $form->expects(self::once())->method('addError');

        self::assertNull($this->helper()->handle(new ApiException('Code déjà utilisé.', 409), $form, self::REPLI));
    }

    public function testUneRegleMetierRefuseeSAfficheEnTeteDuFormulaire(): void
    {
        $form = $this->createMock(FormInterface::class);
        $form->expects(self::once())->method('addError');

        // Un 422 sans violations par champ : « une demande est déjà en attente », « solde insuffisant »
        self::assertNull($this->helper()->handle(new ApiException('Solde insuffisant.', 422), $form, self::REPLI));
    }

    /*
        - Tout le reste — 429 en tête — reste un renvoi avec un message : ce n'est ni une saisie à
          corriger, ni un écran interdit.
    */
    public function testLeResteRenvoieAvecUnMessage(): void
    {
        $reponse = $this->helper()->handle(new ApiException('Trop de tentatives.', 429), null, self::REPLI);

        self::assertNotNull($reponse);
        self::assertSame('/produits', $reponse->getTargetUrl());
    }

    private function helper(): ApiExceptionHandlerHelper
    {
        $requete = new Request();
        $requete->setSession(new Session(new MockArraySessionStorage()));

        $pile = new RequestStack();
        $pile->push($requete);

        // Un STUB et non un mock : on ne vérifie rien sur le routeur, on lui fait juste rendre une URL
        $routeur = $this->createStub(RouterInterface::class);
        $routeur->method('generate')->willReturn('/produits');

        return new ApiExceptionHandlerHelper($pile, $routeur);
    }
}

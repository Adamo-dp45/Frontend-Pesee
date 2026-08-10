<?php

namespace App\Tests\Security;

use App\Entity\ApiUser;
use App\Security\UserGuard;
use App\Security\Voter\AccesVoter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

/*
    - 'AccesVoter' et 'UserGuard' recopient les règles de l'API pour décider ce que l'écran propose.
      Une table recopiée peut diverger, et une divergence ne se voit pas : elle affiche un bouton qui
      finira en 403, ou en cache un que l'utilisateur a le droit d'avoir. Ces cas figent donc le
      contenu de la table de l'API, ligne par ligne.

    - Quand la table de l'API change, c'est CE fichier qui doit tomber en premier.
*/
final class AccesVoterTest extends TestCase
{
    private const COMPTES = [
        'super' => ['ROLE_SUPER_ADMIN', 'ROLE_USER'],
        'admin' => ['ROLE_ADMIN', 'ROLE_USER'],
        'agent' => ['ROLE_AGENT', 'ROLE_USER'],
        'operateur' => ['ROLE_OPERATEUR', 'ROLE_USER'],
    ];

    /** @return iterable<string, array{string, string, bool}> */
    public static function droits(): iterable
    {
        /*
            - Le super administrateur provisionne : il met les postes en service et gère les comptes,
              mais ne touche ni aux pesées, ni à l'argent, ni au journal des synchronisations.
        */
        yield 'le super administrateur met un poste en service' => ['super', 'SITE_CREER', true];
        // Il installe les postes, donc c'est lui qu'on appelle quand l'un d'eux déraille
        yield 'le super administrateur bloque un poste' => ['super', 'SITE_BLOQUER', true];
        yield 'le super administrateur n\'affecte pas d\'opérateur' => ['super', 'SITE_ATTRIBUER', false];
        yield 'le super administrateur crée une entreprise' => ['super', 'ENTREPRISE_CREER', true];
        yield 'le super administrateur ne recharge aucun solde' => ['super', 'ENTREPRISE_RECHARGER', false];
        yield 'le super administrateur ne voit pas les pesées' => ['super', 'OPERATION_VOIR', false];
        yield 'le super administrateur ne voit pas la caisse' => ['super', 'MOUVEMENTCAISSE_VOIR', false];

        // L'administrateur exploite son entreprise de bout en bout, sauf mettre un poste en service
        yield 'l\'administrateur ne met pas un poste en service' => ['admin', 'SITE_CREER', false];
        // L'inscription d'un client est réservée au super administrateur : c'est ce droit qui garde l'écran
        yield 'l\'administrateur n\'inscrit pas de client' => ['admin', 'ENTREPRISE_CREER', false];
        yield 'l\'administrateur bloque un poste' => ['admin', 'SITE_BLOQUER', true];
        yield 'l\'administrateur dote une caisse' => ['admin', 'SITE_ATTRIBUER', true];
        yield 'l\'administrateur recharge le solde' => ['admin', 'ENTREPRISE_RECHARGER', true];
        yield 'l\'administrateur traite une demande de solde' => ['admin', 'DEMANDESOLDE_TRAITER', true];
        yield 'l\'administrateur ne dépose pas de demande de solde' => ['admin', 'DEMANDESOLDE_CREER', false];
        yield 'l\'administrateur verse à un planteur' => ['admin', 'PAIEMENT_PAYER', true];
        yield 'l\'administrateur met un produit à la corbeille' => ['admin', 'PRODUIT_SUPPRIMER', true];

        // L'agent supervise et encadre les opérateurs : il ne touche ni aux prix ni à l'argent
        yield 'l\'agent crée un compte' => ['agent', 'USER_CREER', true];
        yield 'l\'agent change un rôle' => ['agent', 'USER_PROMOUVOIR', true];
        yield 'l\'agent ne tarifie pas' => ['agent', 'PRODUIT_MODIFIER', false];
        yield 'l\'agent ne crée pas de planteur' => ['agent', 'FOURNISSEUR_CREER', false];
        yield 'l\'agent ne verse pas' => ['agent', 'PAIEMENT_PAYER', false];
        yield 'l\'agent lit le journal des synchronisations' => ['agent', 'SYNCHRONISATIONLOT_VOIR', true];

        /*
            - L'opérateur tient la caisse : il tarifie, crée ses planteurs et verse. Le journal des
              synchronisations est un écran de supervision, il ne lui est pas ouvert — l'entrée de
              menu le menait droit sur un refus de l'API.
        */
        yield 'l\'opérateur tarifie un produit' => ['operateur', 'PRODUIT_MODIFIER', true];
        yield 'l\'opérateur ne met pas un produit à la corbeille' => ['operateur', 'PRODUIT_SUPPRIMER', false];
        yield 'l\'opérateur crée un planteur' => ['operateur', 'FOURNISSEUR_CREER', true];
        yield 'l\'opérateur ne supprime pas un planteur' => ['operateur', 'FOURNISSEUR_SUPPRIMER', false];
        yield 'l\'opérateur verse à un planteur' => ['operateur', 'PAIEMENT_PAYER', true];
        yield 'l\'opérateur dépose une demande de solde' => ['operateur', 'DEMANDESOLDE_CREER', true];
        yield 'l\'opérateur ne traite pas les demandes' => ['operateur', 'DEMANDESOLDE_TRAITER', false];
        yield 'l\'opérateur ne lit pas le journal des synchronisations' => ['operateur', 'SYNCHRONISATIONLOT_VOIR', false];
        yield 'l\'opérateur ne voit pas les comptes' => ['operateur', 'USER_VOIR', false];
        yield 'l\'opérateur ne modifie pas un poste' => ['operateur', 'SITE_MODIFIER', false];
        yield 'l\'opérateur voit ses pesées' => ['operateur', 'OPERATION_VOIR', true];
    }

    #[DataProvider('droits')]
    public function testLaTableRecopieCelleDeLApi(string $qui, string $attribut, bool $attendu): void
    {
        self::assertSame($attendu, $this->accorde($qui, $attribut));
    }

    /*
        - Les rôles Symfony et les attributs du framework doivent traverser ce voter sans être
          interceptés : 'ROLE_SUPER_ADMIN' porte lui aussi un tiret bas, et le reconnaître comme une
          permission couperait l'accès aux sections gardées par un rôle.
    */
    #[DataProvider('attributsEtrangers')]
    public function testIlSAbstientSurCeQuiNEstPasUnePermission(string $attribut): void
    {
        self::assertSame(
            AccesVoter::ACCESS_ABSTAIN,
            (new AccesVoter())->vote($this->token('admin'), null, [$attribut])
        );
    }

    /** @return iterable<string, array{string}> */
    public static function attributsEtrangers(): iterable
    {
        yield 'un rôle' => ['ROLE_ADMIN'];
        yield 'un rôle composé' => ['ROLE_SUPER_ADMIN'];
        yield 'un attribut du framework' => ['IS_AUTHENTICATED_FULLY'];
        yield 'l\'accès public' => ['PUBLIC_ACCESS'];
    }

    /** Une faute de frappe dans un gabarit cache le bouton au lieu de l'ouvrir à tout le monde. */
    public function testUneRessourceInconnueEstRefusee(): void
    {
        self::assertFalse($this->accorde('admin', 'PONTBASCULE_VOIR'));
    }

    /* ---- La hiérarchie de gestion des comptes ------------------------------------------------ */

    /** @return iterable<string, array{string, array<string, mixed>, string, bool}> */
    public static function gestionDesComptes(): iterable
    {
        $fondateur = ['id' => 9, 'fondateur' => true, 'roles' => ['ROLE_ADMIN', 'ROLE_USER']];
        $pair = ['id' => 11, 'fondateur' => false, 'roles' => ['ROLE_ADMIN', 'ROLE_USER']];
        $operateur = ['id' => 10, 'fondateur' => false, 'roles' => ['ROLE_OPERATEUR', 'ROLE_USER']];
        $superAdmin = ['id' => 12, 'fondateur' => false, 'roles' => ['ROLE_SUPER_ADMIN', 'ROLE_USER']];
        $luiMeme = ['id' => 2, 'fondateur' => false, 'roles' => ['ROLE_ADMIN', 'ROLE_USER']]; // Même identifiant que le compte 'admin'

        // Le cas signalé : le bouton « Modifier » s'affichait sur sa propre ligne
        yield 'on ne se modifie pas soi-même' => ['admin', $luiMeme, AccesVoter::MODIFIER, false];
        yield 'on se consulte soi-même' => ['admin', $luiMeme, AccesVoter::VOIR, true];
        yield 'on ne se suspend pas soi-même' => ['admin', $luiMeme, AccesVoter::BLOQUER, false];

        yield 'un administrateur modifie un pair' => ['admin', $pair, AccesVoter::MODIFIER, true];

        /*
            - Le fondateur reste modifiable — corriger son numéro de téléphone n'a rien de dangereux —
              mais ni suspendable ni rétrogradable par un pair, sinon deux administrateurs se
              neutralisent et l'entreprise se verrouille hors de son propre outil.
        */
        yield 'le fondateur se corrige' => ['admin', $fondateur, AccesVoter::MODIFIER, true];
        yield 'le fondateur ne se suspend pas entre pairs' => ['admin', $fondateur, AccesVoter::BLOQUER, false];
        yield 'le fondateur ne se rétrograde pas entre pairs' => ['admin', $fondateur, AccesVoter::PROMOUVOIR, false];
        yield 'le super administrateur suspend le fondateur' => ['super', $fondateur, AccesVoter::BLOQUER, true];

        yield 'un super administrateur ne se gère pas d\'ici' => ['admin', $superAdmin, AccesVoter::MODIFIER, false];

        // L'agent encadre les opérateurs, il ne remonte pas la hiérarchie
        yield 'l\'agent gère un opérateur' => ['agent', $operateur, AccesVoter::MODIFIER, true];
        yield 'l\'agent ne gère pas un administrateur' => ['agent', $pair, AccesVoter::MODIFIER, false];
    }

    /**
     * @param array<string, mixed> $cible
     */
    #[DataProvider('gestionDesComptes')]
    public function testLaHierarchieRecopieLeGuardDeLApi(string $qui, array $cible, string $action, bool $attendu): void
    {
        self::assertSame($attendu, (new UserGuard())->peutGerer($this->compte($qui), $cible, $action));
    }

    /** Un refus muet ressemble à une panne : l'écran doit pouvoir dire pourquoi. */
    public function testUnRefusPorteSonMotif(): void
    {
        $motif = (new UserGuard())->motifRefus(
            $this->compte('admin'),
            ['id' => 2, 'roles' => ['ROLE_ADMIN']], // Lui-même
            AccesVoter::MODIFIER
        );

        self::assertNotNull($motif);
        self::assertStringContainsString('votre profil', $motif);
    }

    /* ---- Montage ----------------------------------------------------------------------------- */

    private function accorde(string $qui, string $attribut): bool
    {
        return (new AccesVoter())->vote($this->token($qui), null, [$attribut]) === AccesVoter::ACCESS_GRANTED;
    }

    private function token(string $qui): UsernamePasswordToken
    {
        $compte = $this->compte($qui);

        return new UsernamePasswordToken($compte, 'main', $compte->getRoles());
    }

    private function compte(string $qui): ApiUser
    {
        // L'identifiant de l'administrateur est fixé à 2 : les cas « sur soi-même » s'y adossent
        $ids = ['super' => 1, 'admin' => 2, 'agent' => 3, 'operateur' => 4];

        return new ApiUser([
            'id' => $ids[$qui],
            'email' => $qui . '@webpesee.test',
            'roles' => self::COMPTES[$qui],
        ]);
    }
}

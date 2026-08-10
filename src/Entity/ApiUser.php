<?php

namespace App\Entity;

use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/*
    - L'utilisateur connecté côté web, hydraté depuis la charge utile de '/api/me' conservée en
      session. Ce n'est pas une entité Doctrine : ce projet n'a aucune base à lui.
*/
class ApiUser implements UserInterface, PasswordAuthenticatedUserInterface
{
    public const ADMIN = 'ROLE_ADMIN';
    public const AGENT = 'ROLE_AGENT';
    public const OPERATEUR = 'ROLE_OPERATEUR';
    public const SUPER_ADMIN = 'ROLE_SUPER_ADMIN';

    /**
     * @param array<string, mixed> $data
     */
    public function __construct(
        private readonly array $data
    ) {
    }

    public function getId(): int
    {
        return (int) ($this->data['id'] ?? 0);
    }

    public function getNom(): string
    {
        return (string) ($this->data['nom'] ?? '');
    }

    public function getPrenom(): string
    {
        return (string) ($this->data['prenom'] ?? '');
    }

    public function getNomComplet(): string
    {
        return (string) ($this->data['nomComplet'] ?? trim($this->getPrenom() . ' ' . $this->getNom()));
    }

    public function getEmail(): string
    {
        return (string) ($this->data['email'] ?? '');
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getEntreprise(): ?array
    {
        return $this->data['entreprise'] ?? null;
    }

    public function getNomEntreprise(): ?string
    {
        return $this->getEntreprise()['nom'] ?? null;
    }

    /**
     * @return list<string>
     */
    public function getRoles(): array
    {
        return $this->data['roles'] ?? ['ROLE_USER'];
    }

    public function aLeRole(string $role): bool
    {
        return in_array($role, $this->getRoles(), true);
    }

    /*
        - Qui est ce compte, et rien de plus. Ce qu'il a le DROIT de faire se demande à
          'AccesVoter' — 'is_granted('PRODUIT_MODIFIER')' — qui recopie la table de l'API.

        - Ces méthodes servaient aussi à répondre « peut-il tarifer ? », « peut-il encaisser ? ».
          Elles énuméraient les rôles à la main, à côté de la table de l'API, et c'est ainsi qu'un
          opérateur s'est retrouvé sans le bouton « Nouveau planteur » que l'API lui accordait.
    */
    public function estAdmin(): bool
    {
        return $this->aLeRole(self::ADMIN);
    }

    public function estAgent(): bool
    {
        return $this->aLeRole(self::AGENT);
    }

    public function estOperateur(): bool
    {
        return $this->aLeRole(self::OPERATEUR);
    }

    public function estSuperAdmin(): bool
    {
        return $this->aLeRole(self::SUPER_ADMIN);
    }

    /**
     * Le libellé du rôle, pour l'afficher sous le nom dans la barre latérale.
     */
    public function getLibelleRole(): string
    {
        return match (true) {
            $this->aLeRole(self::SUPER_ADMIN) => 'Super administrateur',
            $this->aLeRole(self::ADMIN) => 'Administrateur',
            $this->aLeRole(self::AGENT) => 'Agent',
            $this->aLeRole(self::OPERATEUR) => 'Opérateur',
            default => 'Utilisateur',
        };
    }

    public function getUserIdentifier(): string
    {
        return $this->getEmail();
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->data;
    }

    /*
        - Requis par le 'remember_me', qui signe son cookie avec. En renvoyant null, Symfony
          retombe sur 'getUserIdentifier()'.
    */
    public function getPassword(): ?string
    {
        return null;
    }
}

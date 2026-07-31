<?php

namespace App\Security;

use App\Domain\Service\ApiClientService;
use App\Entity\ApiUser;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

class ApiUserProvider implements UserProviderInterface
{
    public function __construct(
        private readonly ApiClientService $apiClient,
        private readonly RequestStack $requestStack
    )
    {
    }

    /**
     * Permet de recharger l'utilisateur à chaque requête
     * @param UserInterface $user
     * @throws UnsupportedUserException
     * @throws UserNotFoundException
     * @return ApiUser
     */
    public function refreshUser(UserInterface $user): UserInterface
    {
        if(!$user instanceof ApiUser) {
            throw new UnsupportedUserException(sprintf('Invalid user class "%s".', $user::class));
        }

        $userData = $this->apiClient->getCurrentUser(); /*
            - On relit depuis la session pour éviter un appel 'api' à chaque hit et est mis à jour au login et au refresh
        */
        if(!$userData) {
            throw new UserNotFoundException('Session utilisateur introuvable.');
        }

        return new ApiUser($userData);
    }

    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        /*
            $userData = $this->apiClient->getCurrentUser();
            if(!$userData || ($userData['email'] ?? '') !== $identifier) {
                throw new UserNotFoundException(sprintf('Utilisateur "%s" introuvable.', $identifier));
            }
            return new ApiUser($userData);
        */
        $userData = $this->apiClient->getCurrentUser();

        if($userData && ($userData['email'] ?? '') === $identifier) { /*
            - La session encore vivante donc cas normal
        */
            return new ApiUser($userData);
        }

        $request = $this->requestStack->getCurrentRequest();
        $refreshToken = $request?->cookies->get('rt'); /*
            - La session morte mais cookie 'rt' présent donc on tente un refresh
        */
        if($refreshToken) {
            try {
                $this->apiClient->refreshTokenFromCookie($refreshToken); /*
                    - On reconstruit la session
                */
                $userData = $this->apiClient->getCurrentUser();
                if($userData) {
                    return new ApiUser($userData);
                }
            } catch(\Throwable) { /*
                - Le refresh token expiré donc on laisse tomber et login
            */
            }
        }

        throw new UserNotFoundException(sprintf('Utilisateur "%s" introuvable.', $identifier));
    }

    public function supportsClass(string $class): bool
    {
        return $class === ApiUser::class || is_subclass_of($class, ApiUser::class);
    }
}
<?php

namespace App\Security;

use App\Entity\User;
use App\Repository\UserRepository;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

class AppUserProvider implements UserProviderInterface
{
    public function __construct(private readonly UserRepository $userRepository)
    {
    }

    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        // une seule requête avec JOIN (group, establishment, authority
        $user = $this->userRepository->findByIdentifierEager($identifier);

        if ($user === null) {
            throw new UserNotFoundException(sprintf('Utilisateur "%s" introuvable.', $identifier));
        }

        return $user;
    }

    public function refreshUser(UserInterface $user): UserInterface
    {
        return $this->loadUserByIdentifier($user->getUserIdentifier());
    }

    public function supportsClass(string $class): bool
    {
        return $class === User::class || is_subclass_of($class, User::class);
    }
}

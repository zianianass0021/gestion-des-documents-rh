<?php

namespace App\Security;

use App\Entity\Employe;
use App\Repository\EmployeRepository;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

class UserProvider implements UserProviderInterface
{
    public function __construct(
        private EmployeRepository $employeRepository
    ) {
    }

    public function refreshUser(UserInterface $user): UserInterface
    {
        if (!$user instanceof Employe) {
            throw new UnsupportedUserException(sprintf('Invalid user class "%s".', get_class($user)));
        }

        return $this->loadUserByIdentifier($user->getUserIdentifier());
    }

    public function supportsClass(string $class): bool
    {
        return Employe::class === $class || is_subclass_of($class, Employe::class);
    }

    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        $user = $this->employeRepository->findOneBy(['username' => $identifier]);

        if (!$user) {
            throw new UserNotFoundException(sprintf('User with username "%s" not found.', $identifier));
        }

        // Vérifier que l'utilisateur est actif
        if (!$user->isActive()) {
            throw new UserNotFoundException('User account is inactive.');
        }

        return $user;
    }
}

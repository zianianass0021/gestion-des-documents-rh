<?php

namespace App\Service;

use App\Entity\Employe;
use App\Entity\Module;
use App\Repository\ModuleRepository;
use App\Repository\UserModuleRepository;
use Doctrine\ORM\EntityManagerInterface;

class ModulePermissionService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ModuleRepository $moduleRepository,
        private UserModuleRepository $userModuleRepository
    ) {
    }

    /**
     * Check if user has access to a specific module
     */
    public function hasAccess(Employe $user, string $moduleCode): bool
    {
        // Administrateur RH has access to everything
        if (in_array('ROLE_ADMINISTRATEUR_RH', $user->getRoles())) {
            return true;
        }

        // For Responsable RH, check module permissions
        if (in_array('ROLE_RESPONSABLE_RH', $user->getRoles())) {
            return $user->hasModule($moduleCode);
        }

        // Managers and employees have access to all modules (they are also employees)
        if (in_array('ROLE_MANAGER', $user->getRoles()) || in_array('ROLE_EMPLOYEE', $user->getRoles())) {
            return true;
        }

        // Other roles - default behavior
        return true;
    }

    /**
     * Check if user has access to a route
     */
    public function hasAccessToRoute(Employe $user, string $routeName): bool
    {
        return $user->hasAccessToRoute($routeName);
    }

    /**
     * Get all modules assigned to a user
     * @return Module[]
     */
    public function getUserModules(Employe $user): array
    {
        if (in_array('ROLE_ADMINISTRATEUR_RH', $user->getRoles())) {
            // Administrateur RH sees all active modules
            return $this->moduleRepository->findAllActiveOrdered();
        }

        $modules = [];
        foreach ($user->getUserModules() as $userModule) {
            $module = $userModule->getModule();
            if ($module && $module->isActive()) {
                $modules[] = $module;
            }
        }

        // Sort by sortOrder
        usort($modules, fn($a, $b) => $a->getSortOrder() <=> $b->getSortOrder());

        return $modules;
    }

    /**
     * Assign modules to a user (replaces existing assignments)
     * @param Module[] $modules
     */
    public function assignModules(Employe $user, array $modules): void
    {
        // Remove existing assignments
        $this->userModuleRepository->removeAllForUser($user);

        // Add new assignments
        foreach ($modules as $module) {
            if ($module instanceof Module && $module->isActive()) {
                $userModule = new \App\Entity\UserModule();
                $userModule->setUser($user);
                $userModule->setModule($module);
                $this->entityManager->persist($userModule);
            }
        }

        $this->entityManager->flush();
    }

    /**
     * Get all available modules
     * @return Module[]
     */
    public function getAllModules(): array
    {
        return $this->moduleRepository->findAllOrdered();
    }

    /**
     * Get all active modules
     * @return Module[]
     */
    public function getActiveModules(): array
    {
        return $this->moduleRepository->findAllActiveOrdered();
    }
}


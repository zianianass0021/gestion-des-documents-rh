<?php

namespace App\Twig;

use App\Entity\Employe;
use App\Service\ModulePermissionService;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class ModuleExtension extends AbstractExtension
{
    public function __construct(
        private ModulePermissionService $modulePermissionService
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('has_module', [$this, 'hasModule']),
            new TwigFunction('get_user_modules', [$this, 'getUserModules']),
            new TwigFunction('has_access_to_route', [$this, 'hasAccessToRoute']),
        ];
    }

    public function hasModule(?Employe $user, string $moduleCode): bool
    {
        if (!$user) {
            return false;
        }

        return $this->modulePermissionService->hasAccess($user, $moduleCode);
    }

    public function getUserModules(?Employe $user): array
    {
        if (!$user) {
            return [];
        }

        return $this->modulePermissionService->getUserModules($user);
    }

    public function hasAccessToRoute(?Employe $user, string $routeName): bool
    {
        if (!$user) {
            return false;
        }

        return $this->modulePermissionService->hasAccessToRoute($user, $routeName);
    }
}


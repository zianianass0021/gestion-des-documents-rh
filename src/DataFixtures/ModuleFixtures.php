<?php

namespace App\DataFixtures;

use App\Entity\Module;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class ModuleFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $modules = [
            [
                'code' => 'parametrage',
                'label' => 'Paramétrage',
                'description' => 'Gestion des organisations et placards',
                'icon' => 'fas fa-cog',
                'routePrefix' => 'responsable_manage_organisations',
                'sortOrder' => 1,
            ],
            [
                'code' => 'organisations',
                'label' => 'Organisations',
                'description' => 'Gestion des organisations',
                'icon' => 'fas fa-sitemap',
                'routePrefix' => 'responsable_manage_organisations',
                'sortOrder' => 2,
            ],
            [
                'code' => 'placards',
                'label' => 'Placards',
                'description' => 'Gestion des placards',
                'icon' => 'fas fa-archive',
                'routePrefix' => 'responsable_manage_placards',
                'sortOrder' => 3,
            ],
            [
                'code' => 'employes',
                'label' => 'Employés',
                'description' => 'Gestion des employés et de leurs contrats',
                'icon' => 'fas fa-user-tie',
                'routePrefix' => 'responsable_manage_employes',
                'sortOrder' => 4,
            ],
            [
                'code' => 'documents',
                'label' => 'Documents',
                'description' => 'Gestion des dossiers et documents',
                'icon' => 'fas fa-file-alt',
                'routePrefix' => 'responsable_manage_dossiers',
                'sortOrder' => 5,
            ],
            [
                'code' => 'demandes',
                'label' => 'Demandes',
                'description' => 'Gestion des demandes des employés',
                'icon' => 'fas fa-envelope',
                'routePrefix' => 'responsable_manage_demandes',
                'sortOrder' => 6,
            ],
            [
                'code' => 'reclamations',
                'label' => 'Réclamations',
                'description' => 'Gestion des réclamations',
                'icon' => 'fas fa-exclamation-triangle',
                'routePrefix' => 'responsable_manage_reclamations',
                'sortOrder' => 7,
            ],
        ];

        foreach ($modules as $moduleData) {
            $module = new Module();
            $module->setCode($moduleData['code']);
            $module->setLabel($moduleData['label']);
            $module->setDescription($moduleData['description']);
            $module->setIcon($moduleData['icon']);
            $module->setRoutePrefix($moduleData['routePrefix']);
            $module->setSortOrder($moduleData['sortOrder']);
            $module->setIsActive(true);
            $module->setCreatedAt(new \DateTime());

            $manager->persist($module);
        }

        $manager->flush();
    }
}


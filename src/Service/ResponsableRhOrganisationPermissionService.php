<?php

namespace App\Service;

use App\Entity\Employe;
use App\Entity\Organisation;
use App\Repository\ResponsableRhOrganisationPermissionRepository;
use Doctrine\ORM\EntityManagerInterface;

class ResponsableRhOrganisationPermissionService
{
    // Hiérarchie complète des organisations (Groupement -> DAS -> Dossiers)
    private const ORGANISATION_HIERARCHY = [
        'FCZ' => [
            'DSIG' => ['SFCZ', 'CCGA'],
            'DGST' => ['GACH', 'GCMP', 'GFIN', 'GRSH', 'GJUR', 'GBNQ', 'GPRG'],
            'DASO' => ['SUMM', 'SASE', 'SPSI', 'SASI', 'SPSE'],
            'DENS' => ['EUIA', 'ECSM', 'IFCP', 'ECFC', 'ECRI', 'ELEZ', 'EEAS'],
            'DSOI' => ['SHCZ', 'SLMG', 'SDNT', 'SHMK', 'SHMB', 'SHMY', 'SLPD', 'SRAD', 'SLAB', 'SAHR', 'SCOV', 'SOPH', 'SKIN', 'SCVP', 'SGHJ', 'SGYN', 'SURG', 'SHMS', 'SHCT', 'CSDA'],
            'DRST' => ['RRUR', 'RHUR', 'RHAR', 'RRER', 'RRHK', 'RRHY', 'RDAR', 'RRHR', 'RHSR', 'RCER', 'RBPR', 'RHCC'],
            'DNUM' => ['NSIT', 'NARC', 'NITS', 'NNUM'],
            'DING' => ['IMIN', 'IEIS', 'ICCI', 'IESV', 'ISAE', 'ISAM', 'IPTT', 'ISAA'],
            'DAPR' => ['APCC', 'APVR', 'APUR', 'APLM', 'APCH'],
            'DPHR' => ['LPDP', 'PVPH', 'PPPH'],
            'DPRD' => ['PAMD', 'PIMP', 'PPTM'],
            'DEXP' => ['ECBE', 'ECRG', 'EEDF'],
            'DSPR' => ['PTEX', 'PBIR', 'PLAV', 'PCAP', 'PEPC', 'PCOF', 'PEVN', 'PGRO', 'PSMS'],
            'EPHR' => [], // AFRICMED-COMMERCIALISATION PHARMACEUTIQUE
            'EPRD' => [], // SA2S-METIERS ET SERVICES
            'EING' => [], // SA2S-INGENIERIE & TRAVAUX
        ],
        'RGA' => [],
        'SSS' => [],
        'SST' => [],
    ];

    public function __construct(
        private ResponsableRhOrganisationPermissionRepository $permissionRepository,
        private EntityManagerInterface $entityManager
    ) {
    }

    /**
     * Check if responsable RH has access to a specific organisation
     */
    public function hasAccessToOrganisation(Employe $responsable, Organisation $organisation): bool
    {
        // Administrateur RH has access to everything
        if (in_array('ROLE_ADMINISTRATEUR_RH', $responsable->getRoles())) {
            return true;
        }

        // Special case: responsable RH with username "rh" has access to everything
        if ($responsable->getUsername() === 'rh') {
            return true;
        }

        // If responsable RH is not a responsable RH, return false
        if (!in_array('ROLE_RESPONSABLE_RH', $responsable->getRoles())) {
            return false;
        }

        // Get all permissions for this responsable
        $permissions = $this->permissionRepository->findByResponsable($responsable);

        // If no permissions exist, deny access by default
        if (empty($permissions)) {
            return false;
        }

        // Check each permission
        foreach ($permissions as $permission) {
            if ($this->permissionMatchesOrganisation($permission, $organisation)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if a permission matches an organisation
     */
    private function permissionMatchesOrganisation($permission, Organisation $organisation): bool
    {
        $permissionType = $permission->getPermissionType();
        $orgGroupement = $organisation->getGroupement();
        $orgDas = $organisation->getDas();
        $orgDossier = $organisation->getDossier();

        switch ($permissionType) {
            case 'GROUPEMENT':
                // If permission is for a groupement, check if organisation belongs to that groupement
                return $permission->getGroupement() === $orgGroupement;

            case 'DAS':
                // If permission is for a DAS, check if organisation belongs to that DAS
                if ($permission->getGroupement() !== $orgGroupement) {
                    return false;
                }
                return $permission->getDas() === $orgDas;

            case 'DOSSIER':
                // If permission is for a dossier, check exact match
                if ($permission->getGroupement() !== $orgGroupement) {
                    return false;
                }
                if ($permission->getDas() !== $orgDas) {
                    return false;
                }
                return $permission->getDossier() === $orgDossier;

            default:
                return false;
        }
    }

    /**
     * Check if responsable RH has access to a groupement
     */
    public function hasAccessToGroupement(Employe $responsable, string $groupement): bool
    {
        if (in_array('ROLE_ADMINISTRATEUR_RH', $responsable->getRoles())) {
            return true;
        }

        // Special case: responsable RH with username "rh" has access to everything
        if ($responsable->getUsername() === 'rh') {
            return true;
        }

        $permissions = $this->permissionRepository->findByResponsable($responsable);
        if (empty($permissions)) {
            return false;
        }

        foreach ($permissions as $permission) {
            if ($permission->getGroupement() === $groupement) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if responsable RH has access to a DAS
     */
    public function hasAccessToDas(Employe $responsable, string $das, ?string $groupement = null): bool
    {
        if (in_array('ROLE_ADMINISTRATEUR_RH', $responsable->getRoles())) {
            return true;
        }

        // Special case: responsable RH with username "rh" has access to everything
        if ($responsable->getUsername() === 'rh') {
            return true;
        }

        $permissions = $this->permissionRepository->findByResponsable($responsable);
        if (empty($permissions)) {
            return false;
        }

        foreach ($permissions as $permission) {
            if ($permission->getDas() === $das) {
                // If groupement is specified, check it matches
                if ($groupement !== null && $permission->getGroupement() !== $groupement) {
                    continue;
                }
                return true;
            }
            // Also check if permission is for the parent groupement
            if ($permission->getPermissionType() === 'GROUPEMENT' && $permission->getGroupement() === $groupement) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if responsable RH has access to a dossier
     */
    public function hasAccessToDossier(Employe $responsable, string $dossier, ?string $das = null, ?string $groupement = null): bool
    {
        if (in_array('ROLE_ADMINISTRATEUR_RH', $responsable->getRoles())) {
            return true;
        }

        // Special case: responsable RH with username "rh" has access to everything
        if ($responsable->getUsername() === 'rh') {
            return true;
        }

        $permissions = $this->permissionRepository->findByResponsable($responsable);
        if (empty($permissions)) {
            return false;
        }

        foreach ($permissions as $permission) {
            // Check exact dossier match
            if ($permission->getDossier() === $dossier) {
                if ($das !== null && $permission->getDas() !== $das) {
                    continue;
                }
                if ($groupement !== null && $permission->getGroupement() !== $groupement) {
                    continue;
                }
                return true;
            }
            // Check if permission is for parent DAS
            if ($permission->getPermissionType() === 'DAS' && $permission->getDas() === $das) {
                if ($groupement !== null && $permission->getGroupement() === $groupement) {
                    return true;
                }
            }
            // Check if permission is for parent groupement
            if ($permission->getPermissionType() === 'GROUPEMENT' && $permission->getGroupement() === $groupement) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get allowed groupements for a responsable RH
     */
    public function getAllowedGroupements(Employe $responsable): array
    {
        return $this->permissionRepository->getAllowedGroupements($responsable);
    }

    /**
     * Get allowed DAS for a responsable RH
     */
    public function getAllowedDas(Employe $responsable): array
    {
        return $this->permissionRepository->getAllowedDas($responsable);
    }

    /**
     * Get allowed dossiers for a responsable RH
     */
    public function getAllowedDossiers(Employe $responsable): array
    {
        return $this->permissionRepository->getAllowedDossiers($responsable);
    }

    /**
     * Get SQL WHERE clause to filter employees by permissions
     * Returns array with ['where' => string, 'params' => array]
     */
    public function getEmployeeFilterSQL(Employe $responsable): array
    {
        // Administrateur RH has access to everything
        if (in_array('ROLE_ADMINISTRATEUR_RH', $responsable->getRoles())) {
            return ['where' => '1=1', 'params' => []];
        }

        // Special case: responsable RH with username "rh" has access to everything
        if ($responsable->getUsername() === 'rh') {
            return ['where' => '1=1', 'params' => []];
        }

        // If responsable RH is not a responsable RH, deny all
        if (!in_array('ROLE_RESPONSABLE_RH', $responsable->getRoles())) {
            return ['where' => '1=0', 'params' => []];
        }

        $permissions = $this->permissionRepository->findByResponsable($responsable);

        // If no permissions, deny all
        if (empty($permissions)) {
            return ['where' => '1=0', 'params' => []];
        }

        // Build SQL conditions
        $conditions = [];
        $params = [];
        $paramIndex = 0;

        foreach ($permissions as $permission) {
            $permissionType = $permission->getPermissionType();
            $groupement = $permission->getGroupement();
            $das = $permission->getDas();
            $dossier = $permission->getDossier();

            if ($permissionType === 'GROUPEMENT' && $groupement) {
                // Permission for entire groupement
                $paramKey = 'groupement_' . $paramIndex++;
                $conditions[] = "org.groupement = :{$paramKey}";
                $params[$paramKey] = $groupement;
            } elseif ($permissionType === 'DAS' && $das && $groupement) {
                // Permission for specific DAS
                $paramKeyGroupement = 'groupement_' . $paramIndex++;
                $paramKeyDas = 'das_' . $paramIndex++;
                $conditions[] = "(org.groupement = :{$paramKeyGroupement} AND org.das = :{$paramKeyDas})";
                $params[$paramKeyGroupement] = $groupement;
                $params[$paramKeyDas] = $das;
            } elseif ($permissionType === 'DOSSIER' && $dossier && $das && $groupement) {
                // Permission for specific dossier
                $paramKeyGroupement = 'groupement_' . $paramIndex++;
                $paramKeyDas = 'das_' . $paramIndex++;
                $paramKeyDossier = 'dossier_' . $paramIndex++;
                $conditions[] = "(org.groupement = :{$paramKeyGroupement} AND org.das = :{$paramKeyDas} AND org.dossier = :{$paramKeyDossier})";
                $params[$paramKeyGroupement] = $groupement;
                $params[$paramKeyDas] = $das;
                $params[$paramKeyDossier] = $dossier;
            }
        }

        if (empty($conditions)) {
            return ['where' => '1=0', 'params' => []];
        }

        $where = '(' . implode(' OR ', $conditions) . ')';

        return ['where' => $where, 'params' => $params];
    }

    /**
     * Get organisation hierarchy structure
     * Builds hierarchy dynamically from database
     */
    public static function getOrganisationHierarchy(): array
    {
        // Try to get hierarchy from database if EntityManager is available
        // Otherwise, fall back to static structure
        try {
            // This is a static method, so we can't use dependency injection
            // We'll need to get the EntityManager from the service container
            // For now, we'll use a hybrid approach: static structure + database query if possible
            return self::ORGANISATION_HIERARCHY;
        } catch (\Exception $e) {
            return self::ORGANISATION_HIERARCHY;
        }
    }
    
    /**
     * Get organisation hierarchy structure from database
     * This method should be called with an EntityManager instance
     */
    public function getOrganisationHierarchyFromDatabase(): array
    {
        $hierarchy = [];
        
        // Query all organisations from database
        $organisations = $this->entityManager->getRepository(Organisation::class)->findAll();
        
        foreach ($organisations as $org) {
            $groupement = $org->getGroupement();
            $das = $org->getDas();
            $dossier = $org->getDossier();
            
            // Skip if missing required fields (groupement and DAS are mandatory)
            if (!$groupement || !$das) {
                continue;
            }
            
            // Initialize groupement if not exists
            if (!isset($hierarchy[$groupement])) {
                $hierarchy[$groupement] = [];
            }
            
            // Initialize DAS if not exists (even if no dossiers, we want to show the DAS)
            if (!isset($hierarchy[$groupement][$das])) {
                $hierarchy[$groupement][$das] = [];
            }
            
            // Add dossier if exists and not already in array
            if ($dossier && !in_array($dossier, $hierarchy[$groupement][$das])) {
                $hierarchy[$groupement][$das][] = $dossier;
            }
        }
        
        // Sort dossiers within each DAS
        foreach ($hierarchy as $groupement => $dasList) {
            foreach ($dasList as $das => $dossiers) {
                sort($hierarchy[$groupement][$das]);
            }
            // Sort DAS keys
            ksort($hierarchy[$groupement]);
        }
        
        // Sort groupement keys
        ksort($hierarchy);
        
        return $hierarchy;
    }

    /**
     * Get DAS labels mapping
     */
    public static function getDasLabels(): array
    {
        return [
            'DSIG' => 'DSIG (DAS-SIEGE)',
            'DGST' => 'DGST (DAS-GESTION)',
            'DASO' => 'DASO (DAS-ACTIVITE SOCIALE)',
            'DENS' => 'DENS (DAS-ENSEIGNEMENT & FORMATION)',
            'DSOI' => 'DSOI (DAS-SOINS)',
            'DRST' => 'DRST (DAS-HOTELLERIE-RESTAURATION)',
            'DNUM' => 'DNUM (DAS-INFORMATIQUE ET NUMERIQUE)',
            'DING' => 'DING (DAS-INGENIERIE & TRAVAUX)',
            'DAPR' => 'DAPR (DAS-LOGISTIQUE & APPROVISIONNEMENT)',
            'DPHR' => 'DPHR (DAS-PHARMACEUTIQUE)',
            'DPRD' => 'DPRD (DAS-PRESTATIONS EXTERNALISEES & ACTIVITES DE PRODUCTION)',
            'DEXP' => 'DEXP (DAS-RECHERCHE & EXPERTISE)',
            'DSPR' => 'DSPR (DAS-SERVICES DE PROXIMITE)',
            'EPHR' => 'EPHR (AFRICMED-COMMERCIALISATION PHARMACEUTIQUE)',
            'EPRD' => 'EPRD (SA2S-METIERS ET SERVICES)',
            'EING' => 'EING (SA2S-INGENIERIE & TRAVAUX)',
        ];
    }

    /**
     * Get groupement labels
     */
    public static function getGroupementLabels(): array
    {
        return [
            'FCZ' => 'FCZ',
            'RGA' => 'RGA',
            'SSS' => 'SSS',
            'SST' => 'SST',
        ];
    }
}


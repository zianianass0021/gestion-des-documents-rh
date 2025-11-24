<?php

namespace App\Repository;

use App\Entity\ResponsableRhOrganisationPermission;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ResponsableRhOrganisationPermission>
 */
class ResponsableRhOrganisationPermissionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ResponsableRhOrganisationPermission::class);
    }

    /**
     * Get all permissions for a responsable RH
     */
    public function findByResponsable($responsable): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.responsable = :responsable')
            ->setParameter('responsable', $responsable)
            ->getQuery()
            ->getResult();
    }

    /**
     * Delete all permissions for a responsable RH
     */
    public function deleteByResponsable($responsable): void
    {
        $this->createQueryBuilder('p')
            ->delete()
            ->where('p.responsable = :responsable')
            ->setParameter('responsable', $responsable)
            ->getQuery()
            ->execute();
    }

    /**
     * Get allowed groupements for a responsable RH
     */
    public function getAllowedGroupements($responsable): array
    {
        $permissions = $this->findByResponsable($responsable);
        $groupements = [];
        
        foreach ($permissions as $permission) {
            if ($permission->getGroupement() && !in_array($permission->getGroupement(), $groupements)) {
                $groupements[] = $permission->getGroupement();
            }
        }
        
        return $groupements;
    }

    /**
     * Get allowed DAS for a responsable RH
     */
    public function getAllowedDas($responsable): array
    {
        $permissions = $this->findByResponsable($responsable);
        $das = [];
        
        foreach ($permissions as $permission) {
            if ($permission->getDas() && !in_array($permission->getDas(), $das)) {
                $das[] = $permission->getDas();
            }
        }
        
        return $das;
    }

    /**
     * Get allowed dossiers for a responsable RH
     */
    public function getAllowedDossiers($responsable): array
    {
        $permissions = $this->findByResponsable($responsable);
        $dossiers = [];
        
        foreach ($permissions as $permission) {
            if ($permission->getDossier() && !in_array($permission->getDossier(), $dossiers)) {
                $dossiers[] = $permission->getDossier();
            }
        }
        
        return $dossiers;
    }
}


<?php

namespace App\Repository;

use App\Entity\Placard;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Placard>
 */
class PlacardRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Placard::class);
    }

    public function save(Placard $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Placard $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findByDossier(int $dossierId): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.dossier = :dossierId')
            ->setParameter('dossierId', $dossierId)
            ->orderBy('p.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findByLocation(string $location): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.location LIKE :location')
            ->setParameter('location', '%' . $location . '%')
            ->orderBy('p.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Crée une QueryBuilder pour tous les placards
     */
    public function findAllQuery()
    {
        return $this->createQueryBuilder('p')
            ->orderBy('p.name', 'ASC');
    }

    /**
     * Crée une QueryBuilder pour les placards avec filtre actif/inactif
     */
    public function findAllQueryWithFilter(?string $status = 'all')
    {
        $qb = $this->createQueryBuilder('p');
        
        if ($status === 'active') {
            $qb->where('p.isActive = :isActive')
               ->setParameter('isActive', true);
        } elseif ($status === 'inactive') {
            $qb->where('p.isActive = :isActive')
               ->setParameter('isActive', false);
        }
        // If status is 'all' or null, no filter is applied
        
        return $qb->orderBy('p.name', 'ASC');
    }

    /**
     * Compte les placards actifs
     */
    public function countActive(): int
    {
        return $this->count(['isActive' => true]);
    }

    /**
     * Compte les placards inactifs
     */
    public function countInactive(): int
    {
        return $this->count(['isActive' => false]);
    }
}

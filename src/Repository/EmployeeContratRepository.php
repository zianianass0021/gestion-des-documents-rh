<?php

namespace App\Repository;

use App\Entity\EmployeeContrat;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<EmployeeContrat>
 */
class EmployeeContratRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, EmployeeContrat::class);
    }

    public function save(EmployeeContrat $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(EmployeeContrat $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findActiveContrats(): array
    {
        return $this->createQueryBuilder('ec')
            ->andWhere('ec.statut = :statut')
            ->setParameter('statut', 'actif')
            ->orderBy('ec.dateDebut', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findByEmployee(int $employeeId): array
    {
        return $this->createQueryBuilder('ec')
            ->andWhere('ec.employe = :employeeId')
            ->setParameter('employeeId', $employeeId)
            ->orderBy('ec.dateDebut', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findExpiringContrats(int $days = 30): array
    {
        $date = new \DateTime();
        $date->add(new \DateInterval('P' . $days . 'D'));

        return $this->createQueryBuilder('ec')
            ->andWhere('ec.statut = :statut')
            ->andWhere('ec.dateFin <= :date')
            ->andWhere('ec.dateFin IS NOT NULL')
            ->setParameter('statut', 'actif')
            ->setParameter('date', $date)
            ->orderBy('ec.dateFin', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Crée une QueryBuilder pour tous les contrats
     */
    public function findAllQuery()
    {
        return $this->createQueryBuilder('ec')
            ->leftJoin('ec.employe', 'e')
            ->leftJoin('ec.natureContrat', 'nc')
            ->orderBy('ec.dateDebut', 'DESC');
    }

    /**
     * Trouve les contrats actifs par recherche sur nom/prénom d'employé
     */
    public function findActiveContratsBySearch(string $search, int $limit = 100): array
    {
        return $this->createQueryBuilder('ec')
            ->join('ec.employe', 'e')
            ->join('ec.natureContrat', 'nc')
            ->where('ec.statut = :statut')
            ->andWhere('(LOWER(e.nom) LIKE LOWER(:search) OR LOWER(e.prenom) LIKE LOWER(:search))')
            ->setParameter('statut', 'actif')
            ->setParameter('search', $search . '%')
            ->orderBy('e.nom', 'ASC')
            ->addOrderBy('e.prenom', 'ASC')
            ->addOrderBy('ec.dateDebut', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}

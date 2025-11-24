<?php

namespace App\Repository;

use App\Entity\Demande;
use App\Entity\Employe;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Demande>
 */
class DemandeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Demande::class);
    }

    /**
     * Trouve les demandes d'un employé
     */
    public function findByEmploye(Employe $employe): array
    {
        return $this->createQueryBuilder('d')
            ->where('d.employe = :employe')
            ->setParameter('employe', $employe)
            ->orderBy('d.dateCreation', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les demandes en attente pour un responsable RH
     */
    public function findEnAttente(): array
    {
        return $this->createQueryBuilder('d')
            ->where('d.statut = :statut')
            ->setParameter('statut', 'en_attente')
            ->orderBy('d.dateCreation', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les demandes traitées par un responsable RH
     */
    public function findTraiteesParResponsable(Employe $responsableRh): array
    {
        return $this->createQueryBuilder('d')
            ->where('d.responsableRh = :responsableRh')
            ->andWhere('d.statut != :statutAttente')
            ->setParameter('responsableRh', $responsableRh)
            ->setParameter('statutAttente', 'en_attente')
            ->orderBy('d.dateReponse', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les demandes récentes (toutes)
     */
    public function findRecentDemandes(int $limit = 10): array
    {
        return $this->createQueryBuilder('d')
            ->orderBy('d.dateCreation', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Compte les demandes en attente
     */
    public function countEnAttente(): int
    {
        return $this->createQueryBuilder('d')
            ->select('COUNT(d.id)')
            ->where('d.statut = :statut')
            ->setParameter('statut', 'en_attente')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Trouve les demandes par statut
     */
    public function findByStatut(string $statut): array
    {
        return $this->createQueryBuilder('d')
            ->where('d.statut = :statut')
            ->setParameter('statut', $statut)
            ->orderBy('d.dateCreation', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Crée une QueryBuilder pour les demandes en attente
     */
    public function findEnAttenteQuery()
    {
        return $this->createQueryBuilder('d')
            ->andWhere('d.statut = :statut')
            ->setParameter('statut', 'en_attente')
            ->orderBy('d.dateCreation', 'DESC');
    }

    /**
     * Crée une QueryBuilder pour les demandes traitées par un responsable
     */
    public function findTraiteesParResponsableQuery($responsable)
    {
        return $this->createQueryBuilder('d')
            ->andWhere('d.responsableRh = :responsable')
            ->setParameter('responsable', $responsable)
            ->orderBy('d.dateCreation', 'DESC');
    }

    /**
     * Crée une QueryBuilder pour toutes les demandes
     * Optimisé avec eager loading des relations pour éviter N+1 queries
     */
    public function findAllQuery()
    {
        return $this->createQueryBuilder('d')
            ->leftJoin('d.employe', 'e')
            ->addSelect('e')
            ->leftJoin('d.responsableRh', 'r')
            ->addSelect('r')
            ->orderBy('d.dateCreation', 'DESC');
    }

    /**
     * Crée une QueryBuilder pour les demandes par statut
     * Pour 'traitees', retourne toutes les demandes non en attente
     * Optimisé avec eager loading des relations pour éviter N+1 queries
     */
    public function findByStatutQuery(string $filter)
    {
        $qb = $this->createQueryBuilder('d')
            ->leftJoin('d.employe', 'e')
            ->addSelect('e')
            ->leftJoin('d.responsableRh', 'r')
            ->addSelect('r');
        
        if ($filter === 'en_attente') {
            $qb->where('d.statut = :statut')
               ->setParameter('statut', 'en_attente')
               ->orderBy('d.dateCreation', 'DESC');
        } elseif ($filter === 'traitees') {
            $qb->where('d.statut != :statut')
               ->setParameter('statut', 'en_attente')
               ->orderBy('d.dateReponse', 'DESC')
               ->addOrderBy('d.dateCreation', 'DESC');
        } else {
            // Toutes les demandes
            $qb->orderBy('d.dateCreation', 'DESC');
        }
        
        return $qb;
    }
}

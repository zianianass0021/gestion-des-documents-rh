<?php

namespace App\Repository;

use App\Entity\Dossier;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Dossier>
 */
class DossierRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Dossier::class);
    }

    public function save(Dossier $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Dossier $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findByEmployee(int $employeeId): array
    {
        return $this->createQueryBuilder('d')
            ->andWhere('d.employe = :employeeId')
            ->setParameter('employeeId', $employeeId)
            ->orderBy('d.id', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findByStatus(string $status): array
    {
        return $this->createQueryBuilder('d')
            ->andWhere('d.status = :status')
            ->setParameter('status', $status)
            ->orderBy('d.id', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findRecentDossiers(int $limit = 10): array
    {
        return $this->createQueryBuilder('d')
            ->orderBy('d.id', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function findRecentDossiersWithDocuments(int $limit = 10): array
    {
        // Méthode modifiée car la relation d.documents n'existe plus
        return $this->createQueryBuilder('d')
            ->orderBy('d.id', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function searchByNom(string $searchTerm): array
    {
        return $this->createQueryBuilder('d')
            ->andWhere('d.nom LIKE :searchTerm')
            ->setParameter('searchTerm', '%' . $searchTerm . '%')
            ->orderBy('d.nom', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Recherche flexible des dossiers par tous les champs pertinents
     */
    public function findBySearch(string $search): array
    {
        return $this->createQueryBuilder('d')
            ->leftJoin('d.employe', 'e')
            ->leftJoin('d.documents', 'doc')
            ->leftJoin('d.placard', 'p')
            ->where('d.nom LIKE :search')
            ->orWhere('d.description LIKE :search')
            ->orWhere('d.status LIKE :search')
            ->orWhere('e.nom LIKE :search')
            ->orWhere('e.prenom LIKE :search')
            ->orWhere('e.email LIKE :search')
            ->orWhere('doc.abbreviation LIKE :search')
            ->orWhere('doc.libelleComplet LIKE :search')
            ->orWhere('doc.typeDocument LIKE :search')
            ->orWhere('p.name LIKE :search')
            ->setParameter('search', '%' . $search . '%')
            ->orderBy('d.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Crée une QueryBuilder pour tous les dossiers
     */
    public function findAllQuery()
    {
        return $this->createQueryBuilder('d')
            ->leftJoin('d.employe', 'e')
            ->leftJoin('d.placard', 'p')
            ->orderBy('d.createdAt', 'DESC');
    }

    /**
     * Crée une QueryBuilder pour la recherche de dossiers
     */
    public function findBySearchQuery(string $search)
    {
        return $this->createQueryBuilder('d')
            ->leftJoin('d.employe', 'e')
            ->leftJoin('d.documents', 'doc')
            ->leftJoin('d.placard', 'p')
            ->where('d.nom LIKE :search')
            ->orWhere('d.description LIKE :search')
            ->orWhere('d.status LIKE :search')
            ->orWhere('e.nom LIKE :search')
            ->orWhere('e.prenom LIKE :search')
            ->orWhere('e.email LIKE :search')
            ->orWhere('doc.abbreviation LIKE :search')
            ->orWhere('doc.libelleComplet LIKE :search')
            ->orWhere('doc.typeDocument LIKE :search')
            ->orWhere('p.name LIKE :search')
            ->setParameter('search', '%' . $search . '%')
            ->orderBy('d.createdAt', 'DESC');
    }
}

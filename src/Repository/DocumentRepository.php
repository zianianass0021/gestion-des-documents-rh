<?php

namespace App\Repository;

use App\Entity\Document;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Document>
 */
class DocumentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Document::class);
    }

    public function save(Document $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Document $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findByDossier(int $dossierId): array
    {
        // Note: Documents are no longer directly linked to dossiers
        // This method returns an empty array since the relationship was removed
        return [];
    }

    public function findByFileType(string $fileType): array
    {
        return $this->createQueryBuilder('d')
            ->andWhere('d.typeDocument = :fileType')
            ->setParameter('fileType', $fileType)
            ->orderBy('d.id', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findByReference(string $reference): ?Document
    {
        return $this->createQueryBuilder('d')
            ->andWhere('d.abbreviation = :reference')
            ->setParameter('reference', $reference)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findRecentDocuments(int $limit = 10): array
    {
        return $this->createQueryBuilder('d')
            ->orderBy('d.id', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function searchByFilename(string $searchTerm): array
    {
        return $this->createQueryBuilder('d')
            ->andWhere('d.libelleComplet LIKE :searchTerm')
            ->setParameter('searchTerm', '%' . $searchTerm . '%')
            ->orderBy('d.libelleComplet', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Recherche flexible des documents par tous les champs pertinents
     */
    public function findBySearch(string $search): array
    {
        return $this->createQueryBuilder('d')
            ->leftJoin('d.dossier', 'dos')
            ->leftJoin('dos.employe', 'e')
            ->where('d.abbreviation LIKE :search')
            ->orWhere('d.libelleComplet LIKE :search')
            ->orWhere('d.typeDocument LIKE :search')
            ->orWhere('d.usage LIKE :search')
            ->orWhere('d.uploadedBy LIKE :search')
            ->orWhere('dos.nom LIKE :search')
            ->orWhere('e.nom LIKE :search')
            ->orWhere('e.prenom LIKE :search')
            ->orWhere('e.email LIKE :search')
            ->setParameter('search', '%' . $search . '%')
            ->orderBy('d.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}

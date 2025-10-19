<?php

namespace App\Repository;

use App\Entity\NatureContratTypeDocument;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<NatureContratTypeDocument>
 *
 * @method NatureContratTypeDocument|null find($id, $lockMode = null, $lockVersion = null)
 * @method NatureContratTypeDocument|null findOneBy(array $criteria, array $orderBy = null)
 * @method NatureContratTypeDocument[]    findAll()
 * @method NatureContratTypeDocument[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class NatureContratTypeDocumentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, NatureContratTypeDocument::class);
    }

    public function save(NatureContratTypeDocument $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(NatureContratTypeDocument $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}

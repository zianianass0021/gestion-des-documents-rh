<?php

namespace App\Repository;

use App\Entity\NatureContrat;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<NatureContrat>
 */
class NatureContratRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, NatureContrat::class);
    }

    public function save(NatureContrat $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(NatureContrat $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findAllOrderedByLibelle(): array
    {
        return $this->createQueryBuilder('nc')
            ->orderBy('nc.libelle', 'ASC')
            ->getQuery()
            ->getResult();
    }
}

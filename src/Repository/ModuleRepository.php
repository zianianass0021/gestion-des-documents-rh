<?php

namespace App\Repository;

use App\Entity\Module;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Module>
 */
class ModuleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Module::class);
    }

    /**
     * Find all active modules ordered by order field
     * @return Module[]
     */
    public function findAllActiveOrdered(): array
    {
        return $this->createQueryBuilder('m')
            ->where('m.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('m.sortOrder', 'ASC')
            ->addOrderBy('m.label', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find module by code
     */
    public function findOneByCode(string $code): ?Module
    {
        return $this->createQueryBuilder('m')
            ->where('m.code = :code')
            ->setParameter('code', $code)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find all modules (including inactive) ordered by order field
     * @return Module[]
     */
    public function findAllOrdered(): array
    {
        return $this->createQueryBuilder('m')
            ->orderBy('m.sortOrder', 'ASC')
            ->addOrderBy('m.label', 'ASC')
            ->getQuery()
            ->getResult();
    }
}


<?php

namespace App\Repository;

use App\Entity\UserModule;
use App\Entity\Employe;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<UserModule>
 */
class UserModuleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserModule::class);
    }

    /**
     * Find all modules for a specific user
     * @return UserModule[]
     */
    public function findByUser(Employe $user): array
    {
        return $this->createQueryBuilder('um')
            ->where('um.user = :user')
            ->setParameter('user', $user)
            ->join('um.module', 'm')
            ->andWhere('m.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('m.sortOrder', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Remove all modules for a user
     */
    public function removeAllForUser(Employe $user): void
    {
        $this->createQueryBuilder('um')
            ->delete()
            ->where('um.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->execute();
    }
}


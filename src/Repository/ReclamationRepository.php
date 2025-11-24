<?php

namespace App\Repository;

use App\Entity\Reclamation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Reclamation>
 */
class ReclamationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Reclamation::class);
    }

//    /**
//     * @return Reclamation[] Returns an array of Reclamation objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('r')
//            ->andWhere('r.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('r.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?Reclamation
//    {
//        return $this->createQueryBuilder('r')
//            ->andWhere('r.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }

    /**
     * Crée une QueryBuilder pour les réclamations par statut
     * Optimisé avec eager loading des relations pour éviter N+1 queries
     */
    public function findByStatutQuery(string $filter)
    {
        $qb = $this->createQueryBuilder('r')
            ->leftJoin('r.employe', 'e')
            ->addSelect('e')
            ->leftJoin('r.manager', 'm')
            ->addSelect('m')
            ->leftJoin('r.traitePar', 't')
            ->addSelect('t');
        
        if ($filter === 'en_attente') {
            $qb->where('r.statut = :statut')
               ->setParameter('statut', 'en_attente')
               ->orderBy('r.dateCreation', 'DESC');
        } elseif ($filter === 'traitees') {
            // Traitées = tous les statuts sauf 'en_attente'
            $qb->where('r.statut != :statut')
               ->setParameter('statut', 'en_attente')
               ->orderBy('r.dateTraitement', 'DESC')
               ->addOrderBy('r.dateCreation', 'DESC');
        } else {
            // Par défaut, toutes les réclamations
            $qb->orderBy('r.dateCreation', 'DESC');
        }
        
        return $qb;
    }

    /**
     * Crée une QueryBuilder pour toutes les réclamations
     * Optimisé avec eager loading des relations pour éviter N+1 queries
     */
    public function findAllQuery()
    {
        return $this->createQueryBuilder('r')
            ->leftJoin('r.employe', 'e')
            ->addSelect('e')
            ->leftJoin('r.manager', 'm')
            ->addSelect('m')
            ->leftJoin('r.traitePar', 't')
            ->addSelect('t')
            ->orderBy('r.dateCreation', 'DESC');
    }

    /**
     * Crée une QueryBuilder pour les réclamations d'un manager spécifique
     * Optimisé avec eager loading des relations pour éviter N+1 queries
     * Supporte la recherche et le filtrage
     */
    public function findByManagerQuery($manager, ?string $search = null, ?string $typeFilter = null, ?string $statutFilter = null)
    {
        $qb = $this->createQueryBuilder('r')
            ->leftJoin('r.employe', 'e')
            ->addSelect('e')
            ->leftJoin('r.manager', 'm')
            ->addSelect('m')
            ->leftJoin('r.traitePar', 't')
            ->addSelect('t')
            ->where('r.manager = :manager')
            ->setParameter('manager', $manager);
        
        // Recherche par employé (nom, prénom, email) ou date
        if ($search) {
            $searchLower = '%' . strtolower($search) . '%';
            $searchDate = '%' . $search . '%';
            
            // Try to parse date in different formats
            $dateConditions = [];
            try {
                // Try dd/mm/yyyy format
                if (preg_match('/\d{2}\/\d{2}\/\d{4}/', $search)) {
                    $dateParts = explode('/', $search);
                    if (count($dateParts) === 3) {
                        $dateObj = new \DateTime($dateParts[2] . '-' . $dateParts[1] . '-' . $dateParts[0]);
                        $dateStart = clone $dateObj;
                        $dateStart->setTime(0, 0, 0);
                        $dateEnd = clone $dateObj;
                        $dateEnd->setTime(23, 59, 59);
                        $dateConditions[] = $qb->expr()->between('r.dateCreation', ':searchDateStart', ':searchDateEnd');
                        $qb->setParameter('searchDateStart', $dateStart);
                        $qb->setParameter('searchDateEnd', $dateEnd);
                    }
                }
                // Try yyyy-mm-dd format
                elseif (preg_match('/\d{4}-\d{2}-\d{2}/', $search)) {
                    $dateObj = new \DateTime($search);
                    $dateStart = clone $dateObj;
                    $dateStart->setTime(0, 0, 0);
                    $dateEnd = clone $dateObj;
                    $dateEnd->setTime(23, 59, 59);
                    $dateConditions[] = $qb->expr()->between('r.dateCreation', ':searchDateStart', ':searchDateEnd');
                    $qb->setParameter('searchDateStart', $dateStart);
                    $qb->setParameter('searchDateEnd', $dateEnd);
                }
            } catch (\Exception $e) {
                // Invalid date format, ignore
            }
            
            $orConditions = [
                $qb->expr()->like('LOWER(e.prenom)', ':search'),
                $qb->expr()->like('LOWER(e.nom)', ':search'),
                $qb->expr()->like('LOWER(e.email)', ':search'),
                $qb->expr()->like('LOWER(CONCAT(e.prenom, \' \', e.nom))', ':search'),
            ];
            
            if (!empty($dateConditions)) {
                $orConditions = array_merge($orConditions, $dateConditions);
            } else {
                // Fallback: search in date string representation (for partial date matches)
                $orConditions[] = $qb->expr()->like('CAST(r.dateCreation AS TEXT)', ':searchDate');
                $qb->setParameter('searchDate', $searchDate);
            }
            
            $qb->andWhere($qb->expr()->orX(...$orConditions))
               ->setParameter('search', $searchLower);
        }
        
        // Filtre par type
        if ($typeFilter && $typeFilter !== 'all') {
            $qb->andWhere('r.typeReclamation = :typeFilter')
               ->setParameter('typeFilter', $typeFilter);
        }
        
        // Filtre par statut
        if ($statutFilter && $statutFilter !== 'all') {
            $qb->andWhere('r.statut = :statutFilter')
               ->setParameter('statutFilter', $statutFilter);
        }
        
        $qb->orderBy('r.dateCreation', 'DESC');
        
        return $qb;
    }
}

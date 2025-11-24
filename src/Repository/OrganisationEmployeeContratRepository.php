<?php

namespace App\Repository;

use App\Entity\OrganisationEmployeeContrat;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<OrganisationEmployeeContrat>
 *
 * @method OrganisationEmployeeContrat|null find($id, $lockMode = null, $lockVersion = null)
 * @method OrganisationEmployeeContrat|null findOneBy(array $criteria, array $orderBy = null)
 * @method OrganisationEmployeeContrat[]    findAll()
 * @method OrganisationEmployeeContrat[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class OrganisationEmployeeContratRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, OrganisationEmployeeContrat::class);
    }

    public function save(OrganisationEmployeeContrat $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(OrganisationEmployeeContrat $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Trouve les organisations par contrat d'employé
     */
    public function findByEmployeeContrat(int $employeeContratId): array
    {
        return $this->createQueryBuilder('oec')
            ->andWhere('oec.employeeContrat = :employeeContratId')
            ->setParameter('employeeContratId', $employeeContratId)
            ->orderBy('oec.dateDebut', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Crée une QueryBuilder pour les contrats d'employés d'une organisation spécifique
     * Avec eager loading des relations pour éviter N+1 queries
     */
    public function findByOrganisationQuery($organisation)
    {
        return $this->createQueryBuilder('oec')
            ->leftJoin('oec.employeeContrat', 'ec')
            ->addSelect('ec')
            ->leftJoin('ec.employe', 'e')
            ->addSelect('e')
            ->leftJoin('ec.natureContrat', 'nc')
            ->addSelect('nc')
            ->where('oec.organisation = :organisation')
            ->setParameter('organisation', $organisation)
            ->orderBy('e.nom', 'ASC')
            ->addOrderBy('e.prenom', 'ASC');
    }
}

<?php

namespace App\Repository;

use App\Entity\Employe;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;

/**
 * @extends ServiceEntityRepository<Employe>
 *
 * @implements PasswordUpgraderInterface<Employe>
 *
 * @method Employe|null find($id, $lockMode = null, $lockVersion = null)
 * @method Employe|null findOneBy(array $criteria, array $orderBy = null)
 * @method Employe[]    findAll()
 * @method Employe[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class EmployeRepository extends ServiceEntityRepository implements PasswordUpgraderInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Employe::class);
    }

    /**
     * Used to upgrade (rehash) the user's password automatically over time.
     */
    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof Employe) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', $user::class));
        }

        $user->setPassword($newHashedPassword);
        $this->getEntityManager()->persist($user);
        $this->getEntityManager()->flush();
    }

    /**
     * Trouve un employé par son email
     */
    public function findByEmail(string $email): ?Employe
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.email = :email')
            ->setParameter('email', $email)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Trouve les employés actifs
     */
    public function findActiveEmployees(): array
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('e.nom', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les employés par rôle
     */
    public function findEmployeesByRole(string $role): array
    {
        // Utiliser une requête SQL native pour PostgreSQL
        $sql = 'SELECT e.* FROM t_employe e WHERE e.roles::text LIKE :role ORDER BY e.nom ASC';
        
        $stmt = $this->getEntityManager()->getConnection()->prepare($sql);
        $result = $stmt->executeQuery(['role' => '%' . $role . '%']);
        
        $data = $result->fetchAllAssociative();
        $employees = [];
        
        foreach ($data as $row) {
            $employees[] = $this->find($row['id']);
        }
        
        return $employees;
    }

    /**
     * Récupère les IDs des employés par rôle (pour les formulaires)
     */
    public function getEmployeeIdsByRole(string $role): array
    {
        $sql = 'SELECT e.id FROM t_employe e WHERE e.roles::text LIKE :role ORDER BY e.nom ASC';
        
        $stmt = $this->getEntityManager()->getConnection()->prepare($sql);
        $result = $stmt->executeQuery(['role' => '%' . $role . '%']);
        
        $data = $result->fetchAllAssociative();
        
        return array_column($data, 'id');
    }

    /**
     * Trouve les employés par rôle (alias pour findEmployeesByRole)
     */
    public function findByRole(string $role): array
    {
        return $this->findEmployeesByRole($role);
    }

    /**
     * Trouve les employés actifs par rôle
     */
    public function findActiveByRole(string $role): array
    {
        // Utiliser une requête SQL native pour PostgreSQL
        $sql = 'SELECT e.* FROM t_employe e WHERE e.is_active = :active AND e.roles::text LIKE :role ORDER BY e.nom ASC';
        
        $stmt = $this->getEntityManager()->getConnection()->prepare($sql);
        $result = $stmt->executeQuery([
            'active' => true,
            'role' => '%' . $role . '%'
        ]);
        
        $data = $result->fetchAllAssociative();
        $employees = [];
        
        foreach ($data as $row) {
            $employees[] = $this->find($row['id']);
        }
        
        return $employees;
    }

    /**
     * Trouve les employés par rôle avec recherche flexible
     */
    public function findByRoleAndSearch(string $role, string $search): array
    {
        // Recherche flexible qui inclut aussi les informations d'organisation et de contrat
        $sql = 'SELECT DISTINCT e.* FROM t_employe e 
                LEFT JOIN t_employee_contrat ec ON e.id = ec.employe_id
                LEFT JOIN t_organisation_employee_contrat oec ON ec.id = oec.employee_contrat_id
                LEFT JOIN p_organisation o ON oec.organisation_id = o.id
                LEFT JOIN p_nature_contrat nc ON ec.nature_contrat_id = nc.id
                WHERE e.roles::text LIKE :role 
                AND (LOWER(e.nom) LIKE LOWER(:search) 
                     OR LOWER(e.prenom) LIKE LOWER(:search) 
                     OR LOWER(e.email) LIKE LOWER(:search)
                     OR LOWER(o.dossier_designation) LIKE LOWER(:search)
                     OR LOWER(o.code) LIKE LOWER(:search)
                     OR LOWER(nc.designation) LIKE LOWER(:search))
                ORDER BY e.nom ASC';
        
        $stmt = $this->getEntityManager()->getConnection()->prepare($sql);
        $result = $stmt->executeQuery([
            'role' => '%' . $role . '%',
            'search' => '%' . $search . '%'
        ]);
        
        $data = $result->fetchAllAssociative();
        $employees = [];
        
        foreach ($data as $row) {
            $employees[] = $this->find($row['id']);
        }
        
        return $employees;
    }

    /**
     * Trouve les employés actifs par rôle avec recherche flexible
     */
    public function findActiveByRoleAndSearch(string $role, string $search): array
    {
        // Recherche flexible pour employés actifs qui inclut organisation et contrat
        $sql = 'SELECT DISTINCT e.* FROM t_employe e 
                LEFT JOIN t_employee_contrat ec ON e.id = ec.employe_id
                LEFT JOIN t_organisation_employee_contrat oec ON ec.id = oec.employee_contrat_id
                LEFT JOIN p_organisation o ON oec.organisation_id = o.id
                LEFT JOIN p_nature_contrat nc ON ec.nature_contrat_id = nc.id
                WHERE e.is_active = :active AND e.roles::text LIKE :role 
                AND (LOWER(e.nom) LIKE LOWER(:search) 
                     OR LOWER(e.prenom) LIKE LOWER(:search) 
                     OR LOWER(e.email) LIKE LOWER(:search)
                     OR LOWER(o.dossier_designation) LIKE LOWER(:search)
                     OR LOWER(o.code) LIKE LOWER(:search)
                     OR LOWER(nc.designation) LIKE LOWER(:search))
                ORDER BY e.nom ASC';
        
        $stmt = $this->getEntityManager()->getConnection()->prepare($sql);
        $result = $stmt->executeQuery([
            'active' => true,
            'role' => '%' . $role . '%',
            'search' => '%' . $search . '%'
        ]);
        
        $data = $result->fetchAllAssociative();
        $employees = [];
        
        foreach ($data as $row) {
            $employees[] = $this->find($row['id']);
        }
        
        return $employees;
    }

    /**
     * Crée une QueryBuilder pour les employés par rôle
     */
    public function findByRoleQuery(string $role)
    {
        // Utiliser une requête SQL native pour PostgreSQL
        $sql = 'SELECT e.* FROM t_employe e WHERE e.roles::text LIKE :role ORDER BY e.nom ASC';
        
        $stmt = $this->getEntityManager()->getConnection()->prepare($sql);
        $result = $stmt->executeQuery(['role' => '%' . $role . '%']);
        
        $data = $result->fetchAllAssociative();
        $employees = [];
        
        foreach ($data as $row) {
            $employees[] = $this->find($row['id']);
        }
        
        return $employees;
    }

    /**
     * Crée une QueryBuilder pour les employés actifs par rôle
     */
    public function findActiveByRoleQuery(string $role)
    {
        // Utiliser une requête SQL native pour PostgreSQL
        $sql = 'SELECT e.* FROM t_employe e WHERE e.is_active = :active AND e.roles::text LIKE :role ORDER BY e.nom ASC';
        
        $stmt = $this->getEntityManager()->getConnection()->prepare($sql);
        $result = $stmt->executeQuery([
            'active' => true,
            'role' => '%' . $role . '%'
        ]);
        
        $data = $result->fetchAllAssociative();
        $employees = [];
        
        foreach ($data as $row) {
            $employees[] = $this->find($row['id']);
        }
        
        return $employees;
    }

    /**
     * Crée une QueryBuilder pour les employés par rôle avec recherche
     */
    public function findByRoleAndSearchQuery(string $role, string $search)
    {
        // Utiliser une requête SQL native pour PostgreSQL
        $sql = 'SELECT DISTINCT e.* FROM t_employe e 
                WHERE e.roles::text LIKE :role 
                AND (LOWER(e.nom) LIKE LOWER(:search) 
                     OR LOWER(e.prenom) LIKE LOWER(:search) 
                     OR LOWER(e.email) LIKE LOWER(:search))
                ORDER BY e.nom ASC';
        
        $stmt = $this->getEntityManager()->getConnection()->prepare($sql);
        $result = $stmt->executeQuery([
            'role' => '%' . $role . '%',
            'search' => '%' . $search . '%'
        ]);
        
        $data = $result->fetchAllAssociative();
        $employees = [];
        
        foreach ($data as $row) {
            $employees[] = $this->find($row['id']);
        }
        
        return $employees;
    }

    /**
     * Crée une QueryBuilder pour les employés actifs par rôle avec recherche
     */
    public function findActiveByRoleAndSearchQuery(string $role, string $search)
    {
        // Utiliser une requête SQL native pour PostgreSQL
        $sql = 'SELECT DISTINCT e.* FROM t_employe e 
                WHERE e.is_active = :active AND e.roles::text LIKE :role 
                AND (LOWER(e.nom) LIKE LOWER(:search) 
                     OR LOWER(e.prenom) LIKE LOWER(:search) 
                     OR LOWER(e.email) LIKE LOWER(:search))
                ORDER BY e.nom ASC';
        
        $stmt = $this->getEntityManager()->getConnection()->prepare($sql);
        $result = $stmt->executeQuery([
            'active' => true,
            'role' => '%' . $role . '%',
            'search' => '%' . $search . '%'
        ]);
        
        $data = $result->fetchAllAssociative();
        $employees = [];
        
        foreach ($data as $row) {
            $employees[] = $this->find($row['id']);
        }
        
        return $employees;
    }
}

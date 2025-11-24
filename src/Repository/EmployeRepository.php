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
     * Compte les employés par rôle
     */
    public function countByRole(string $role): int
    {
        $sql = 'SELECT COUNT(e.id) FROM t_user e WHERE e.roles::text LIKE :role';
        
        $stmt = $this->getEntityManager()->getConnection()->prepare($sql);
        $result = $stmt->executeQuery(['role' => '%' . $role . '%']);
        
        return (int) $result->fetchOne();
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
        $sql = 'SELECT e.* FROM t_user e WHERE e.roles::text LIKE :role ORDER BY e.nom ASC';
        
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
        $sql = 'SELECT e.id FROM t_user e WHERE e.roles::text LIKE :role ORDER BY e.nom ASC';
        
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
        $sql = 'SELECT e.* FROM t_user e WHERE e.is_active = :active AND e.roles::text LIKE :role ORDER BY e.nom ASC';
        
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
        $sql = 'SELECT DISTINCT e.* FROM t_user e 
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
     * Recherche optimisée d'employés actifs par rôle SANS DOSSIER (pour AJAX/search)
     * Retourne un tableau associatif avec id, nom, prénom, email uniquement
     * Utilisé pour la création de dossier (un employé = un dossier)
     */
    public function searchActiveEmployeesWithoutDossierByRole(string $role, string $search, int $limit = 50): array
    {
        $conn = $this->getEntityManager()->getConnection();
        $searchLower = '%' . strtolower(trim($search)) . '%';
        
        // Recherche les employés actifs avec le rôle spécifié qui n'ont PAS de dossier
        $sql = 'SELECT e.id, e.nom, e.prenom, e.email 
                FROM t_user e 
                LEFT JOIN t_dossier d ON e.id = d.employe_id
                WHERE e.is_active = :active 
                AND CAST(e.roles AS TEXT) LIKE :role 
                AND d.id IS NULL
                AND (
                    LOWER(e.nom) LIKE :search_lower 
                    OR LOWER(e.prenom) LIKE :search_lower 
                    OR LOWER(e.email) LIKE :search_lower
                    OR LOWER(e.prenom || \' \' || e.nom) LIKE :search_lower
                    OR LOWER(e.nom || \' \' || e.prenom) LIKE :search_lower
                )
                ORDER BY 
                    CASE 
                        WHEN LOWER(e.nom) LIKE :search_lower THEN 1
                        WHEN LOWER(e.prenom) LIKE :search_lower THEN 2
                        WHEN LOWER(e.prenom || \' \' || e.nom) LIKE :search_lower THEN 3
                        WHEN LOWER(e.nom || \' \' || e.prenom) LIKE :search_lower THEN 4
                        ELSE 5
                    END,
                    e.nom ASC, 
                    e.prenom ASC 
                LIMIT ' . (int)$limit;
        
        try {
            $stmt = $conn->prepare($sql);
            $result = $stmt->executeQuery([
                'active' => true,
                'role' => '%' . $role . '%',
                'search_lower' => $searchLower
            ]);
            
            return $result->fetchAllAssociative();
        } catch (\Exception $e) {
            error_log('Erreur SQL dans searchActiveEmployeesWithoutDossierByRole: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Recherche optimisée d'employés actifs par rôle (pour AJAX/search)
     * Retourne un tableau associatif avec id, nom, prénom, email uniquement
     * 
     * @param string $role Le rôle à rechercher
     * @param string $search Le terme de recherche
     * @param int $limit Limite de résultats
     * @param string|null $dossierCode Code du dossier pour filtrer (optionnel, pour les managers)
     */
    public function searchActiveEmployeesByRole(string $role, string $search, int $limit = 50, ?string $dossierCode = null, ?array $dossierCodes = null): array
    {
        // Requête SQL optimisée qui retourne uniquement les données nécessaires
        // Recherche dans nom, prénom, email et nom complet (nom + prénom)
        $conn = $this->getEntityManager()->getConnection();
        $searchLower = '%' . strtolower(trim($search)) . '%';
        
        // Utiliser || pour la concaténation PostgreSQL au lieu de CONCAT pour plus de compatibilité
        // Si dossierCodes (array) est fourni, filtrer par plusieurs codes de dossier
        // Sinon, si dossierCode (string) est fourni, filtrer par un seul code de dossier (rétrocompatibilité)
        $dossiersToFilter = null;
        if ($dossierCodes !== null && !empty($dossierCodes)) {
            $dossiersToFilter = $dossierCodes;
        } elseif ($dossierCode !== null) {
            $dossiersToFilter = [$dossierCode];
        }
        
        if ($dossiersToFilter !== null && !empty($dossiersToFilter)) {
            // Recherche flexible: cherche dans nom OU prénom OU email OU combinaisons
            // Permet de trouver un employé même si on ne connait que son prénom ou son nom
            // Note: On utilise DISTINCT mais ORDER BY simple car avec DISTINCT, ORDER BY doit être dans SELECT
            $sql = 'SELECT DISTINCT e.id, e.nom, e.prenom, e.email 
                    FROM t_user e 
                    INNER JOIN t_dossier d ON e.id = d.employe_id
                    WHERE e.is_active = :active 
                    AND CAST(e.roles AS TEXT) LIKE :role 
                    AND d.dossier_code IS NOT NULL
                    AND d.dossier_code = ANY(:dossier_codes)
                    AND (
                        LOWER(e.nom) LIKE :search_lower 
                        OR LOWER(e.prenom) LIKE :search_lower 
                        OR LOWER(e.email) LIKE :search_lower
                        OR LOWER(e.prenom || \' \' || e.nom) LIKE :search_lower
                        OR LOWER(e.nom || \' \' || e.prenom) LIKE :search_lower
                    )
                    ORDER BY e.nom ASC, e.prenom ASC 
                    LIMIT ' . (int)$limit;
            
            $params = [
                'active' => true,
                'role' => '%' . $role . '%',
                'dossier_codes' => '{' . implode(',', array_map(function($code) {
                    return '"' . addslashes($code) . '"';
                }, $dossiersToFilter)) . '}',
                'search_lower' => $searchLower
            ];
        } else {
            $sql = 'SELECT e.id, e.nom, e.prenom, e.email 
                    FROM t_user e 
                    WHERE e.is_active = :active 
                    AND CAST(e.roles AS TEXT) LIKE :role 
                    AND (
                        LOWER(e.nom) LIKE :search_lower 
                        OR LOWER(e.prenom) LIKE :search_lower 
                        OR LOWER(e.email) LIKE :search_lower
                        OR LOWER(e.prenom || \' \' || e.nom) LIKE :search_lower
                        OR LOWER(e.nom || \' \' || e.prenom) LIKE :search_lower
                    )
                    ORDER BY 
                        CASE 
                            WHEN LOWER(e.nom) LIKE :search_lower THEN 1
                            WHEN LOWER(e.prenom) LIKE :search_lower THEN 2
                            WHEN LOWER(e.prenom || \' \' || e.nom) LIKE :search_lower THEN 3
                            WHEN LOWER(e.nom || \' \' || e.prenom) LIKE :search_lower THEN 4
                            ELSE 5
                        END,
                        e.nom ASC, 
                        e.prenom ASC 
                    LIMIT ' . (int)$limit;
            
            $params = [
                'active' => true,
                'role' => '%' . $role . '%',
                'search_lower' => $searchLower
            ];
        }
        
        try {
            $stmt = $conn->prepare($sql);
            $result = $stmt->executeQuery($params);
            
            return $result->fetchAllAssociative();
        } catch (\Exception $e) {
            // En cas d'erreur SQL, retourner un tableau vide plutôt que de planter
            error_log('Erreur SQL dans searchActiveEmployeesByRole: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Trouve les employés actifs par rôle avec recherche flexible
     */
    public function findActiveByRoleAndSearch(string $role, string $search): array
    {
        // Recherche flexible pour employés actifs qui inclut organisation et contrat
        $sql = 'SELECT DISTINCT e.* FROM t_user e 
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
     * Crée une QueryBuilder optimisé pour les employés actifs par rôle (pour formulaires)
     * Optimisé pour éviter l'épuisement mémoire en limitant les résultats et filtrant sur is_active
     */
    public function findActiveEmployeesByRoleQueryBuilder(string $role, int $limit = 500)
    {
        // Utiliser SQL natif pour obtenir rapidement les IDs sans charger les relations
        $conn = $this->getEntityManager()->getConnection();
        // PostgreSQL ne supporte pas les paramètres nommés dans LIMIT, donc on utilise directement la valeur
        $sql = 'SELECT e.id FROM t_user e 
                WHERE e.is_active = :active 
                AND CAST(e.roles AS TEXT) LIKE :role 
                ORDER BY e.nom ASC, e.prenom ASC 
                LIMIT ' . (int)$limit;
        $stmt = $conn->prepare($sql);
        $result = $stmt->executeQuery([
            'active' => true,
            'role' => '%' . $role . '%'
        ]);
        $employeeIds = $result->fetchFirstColumn();
        
        if (empty($employeeIds)) {
            return $this->createQueryBuilder('e')->where('1 = 0');
        }
        
        // Charger uniquement les entités nécessaires sans relations lazy
        return $this->createQueryBuilder('e')
            ->where('e.id IN (:ids)')
            ->setParameter('ids', $employeeIds)
            ->orderBy('e.nom', 'ASC')
            ->addOrderBy('e.prenom', 'ASC');
    }

    /**
     * Crée une QueryBuilder pour les employés par rôle
     */
    public function findByRoleQuery(string $role)
    {
        $conn = $this->getEntityManager()->getConnection();
        $sql = 'SELECT e.id FROM t_user e WHERE CAST(e.roles AS TEXT) LIKE :role ORDER BY e.nom ASC';
        
        $stmt = $conn->prepare($sql);
        $result = $stmt->executeQuery(['role' => '%' . $role . '%']);
        
        $ids = $result->fetchFirstColumn();
        
        if (empty($ids)) {
            return $this->createQueryBuilder('e')->where('1 = 0');
        }
        
        return $this->createQueryBuilder('e')
            ->where('e.id IN (:ids)')
            ->setParameter('ids', $ids)
            ->orderBy('e.nom', 'ASC');
    }

    /**
     * Crée une QueryBuilder pour les employés actifs par rôle
     */
    public function findActiveByRoleQuery(string $role)
    {
        // Get IDs efficiently
        $conn = $this->getEntityManager()->getConnection();
        $sql = 'SELECT e.id FROM t_user e WHERE e.is_active = :active AND CAST(e.roles AS TEXT) LIKE :role ORDER BY e.nom ASC';
        
        $stmt = $conn->prepare($sql);
        $result = $stmt->executeQuery([
            'active' => true,
            'role' => '%' . $role . '%'
        ]);
        
        $ids = $result->fetchFirstColumn();
        
        if (empty($ids)) {
            return $this->createQueryBuilder('e')->where('1 = 0');
        }
        
        return $this->createQueryBuilder('e')
            ->where('e.id IN (:ids)')
            ->setParameter('ids', $ids)
            ->orderBy('e.nom', 'ASC');
    }

    /**
     * Crée une QueryBuilder pour les employés par rôle avec recherche
     */
    public function findByRoleAndSearchQuery(string $role, string $search)
    {
        $conn = $this->getEntityManager()->getConnection();
        $sql = 'SELECT e.id FROM t_user e 
                WHERE CAST(e.roles AS TEXT) LIKE :role 
                AND (LOWER(e.nom) LIKE LOWER(:search) 
                     OR LOWER(e.prenom) LIKE LOWER(:search) 
                     OR LOWER(e.email) LIKE LOWER(:search))
                ORDER BY e.nom ASC';
        
        $stmt = $conn->prepare($sql);
        $result = $stmt->executeQuery([
            'role' => '%' . $role . '%',
            'search' => '%' . $search . '%'
        ]);
        
        $ids = $result->fetchFirstColumn();
        
        if (empty($ids)) {
            return $this->createQueryBuilder('e')->where('1 = 0');
        }
        
        return $this->createQueryBuilder('e')
            ->where('e.id IN (:ids)')
            ->setParameter('ids', $ids)
            ->orderBy('e.nom', 'ASC');
    }

    /**
     * Crée une QueryBuilder pour les employés actifs par rôle avec recherche
     * Returns a QueryBuilder for proper pagination support
     */
    public function findActiveByRoleAndSearchQuery(string $role, string $search)
    {
        $conn = $this->getEntityManager()->getConnection();
        $sql = 'SELECT e.id FROM t_user e 
                WHERE e.is_active = :active AND CAST(e.roles AS TEXT) LIKE :role 
                AND (LOWER(e.nom) LIKE LOWER(:search) 
                     OR LOWER(e.prenom) LIKE LOWER(:search) 
                     OR LOWER(e.email) LIKE LOWER(:search))
                ORDER BY e.nom ASC';
        
        $stmt = $conn->prepare($sql);
        $result = $stmt->executeQuery([
            'active' => true,
            'role' => '%' . $role . '%',
            'search' => '%' . $search . '%'
        ]);
        
        $ids = $result->fetchFirstColumn();
        
        if (empty($ids)) {
            return $this->createQueryBuilder('e')->where('1 = 0');
        }
        
        return $this->createQueryBuilder('e')
            ->where('e.id IN (:ids)')
            ->setParameter('ids', $ids)
            ->orderBy('e.nom', 'ASC');
    }
}

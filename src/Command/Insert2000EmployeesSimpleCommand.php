<?php

namespace App\Command;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:insert-2000-employees-simple',
    description: 'Insert 2000 new employees using simple SQL approach',
)]
class Insert2000EmployeesSimpleCommand extends Command
{
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Insertion Simple de 2000 Nouveaux Employés');

        $connection = $this->entityManager->getConnection();

        // 1. Créer de nouveaux placards
        $io->section('Création de nouveaux placards...');
        $placardNames = [
            'Archives Centrales', 'Dossiers Financiers', 'Ressources Humaines',
            'Services Techniques', 'Direction Générale', 'Comptabilité',
            'Informatique', 'Marketing', 'Ventes', 'Production',
            'Archives Secrétariat', 'Dossiers Juridiques', 'RH Temporaires',
            'Archives Anciennes', 'Dossiers Spéciaux', 'Archives Digitales'
        ];

        foreach ($placardNames as $name) {
            $location = 'Bâtiment ' . chr(65 + rand(0, 25)) . ' - Étage ' . rand(1, 5);
            $sql = "INSERT INTO p_placards (id, name, location, created_at) VALUES (nextval('p_placards_id_seq'), ?, ?, NOW())";
            $connection->executeStatement($sql, [$name, $location]);
        }
        $io->text("Créés " . count($placardNames) . " nouveaux placards");

        // 2. Insérer 2000 employés avec des noms uniques
        $io->section('Insertion des 2000 employés...');
        
        $prenoms = [
            'Ahmed', 'Mohamed', 'Hassan', 'Omar', 'Youssef', 'Karim', 'Rachid', 'Said', 'Ali', 'Mustapha',
            'Fatima', 'Aicha', 'Khadija', 'Zineb', 'Naima', 'Samira', 'Latifa', 'Malika', 'Hakima', 'Souad',
            'Abdel', 'Abdellah', 'Abderrahman', 'Abdelkader', 'Abdelaziz', 'Abdelhak', 'Abdelmajid', 'Abdelouahed',
            'Amina', 'Houda', 'Nadia', 'Rachida', 'Saida', 'Touria', 'Widad', 'Yasmina', 'Zakia', 'Halima',
            'Brahim', 'Chakib', 'Driss', 'Fouad', 'Ghassan', 'Hicham', 'Ibrahim', 'Jamal', 'Khalid', 'Lahcen',
            'Mounir', 'Nabil', 'Othman', 'Reda', 'Salah', 'Tarik', 'Walid', 'Yassine', 'Zakaria', 'Adil',
            'Badr', 'Chadi', 'Dounia', 'Elyas', 'Fadi', 'Ghita', 'Hajar', 'Imane', 'Jihane', 'Kenza',
            'Lina', 'Meryem', 'Nour', 'Oumaima', 'Rania', 'Salma', 'Tasnim', 'Wiam', 'Yara', 'Zineb',
            'Achraf', 'Anouar', 'Anass', 'Younes', 'Mehdi', 'Soufiane', 'Hamza', 'Ayoub', 'Imad', 'Ziad'
        ];

        $noms = [
            'Alaoui', 'Benali', 'Chraibi', 'Dakir', 'El Fassi', 'Gharbi', 'Hassani', 'Idrissi', 'Jabri', 'Kabbaj',
            'Lahlou', 'Mansouri', 'Naciri', 'Ouali', 'Pacha', 'Qadiri', 'Rahmani', 'Saadi', 'Tazi', 'Uali',
            'Verdi', 'Wahbi', 'Xalil', 'Yahya', 'Zaki', 'Achour', 'Bennani', 'Cherki', 'Daoudi', 'El Mansouri',
            'Fassi', 'Guerraoui', 'Hajji', 'Ibrahimi', 'Jouhari', 'Kettani', 'Lahlou', 'Mansouri', 'Naciri', 'Ouali',
            'Pacha', 'Qadiri', 'Rahmani', 'Saadi', 'Tazi', 'Uali', 'Verdi', 'Wahbi', 'Xalil', 'Yahya',
            'Zaki', 'Achour', 'Bennani', 'Cherki', 'Daoudi', 'El Mansouri', 'Fassi', 'Guerraoui', 'Hajji', 'Ibrahimi',
            'Jouhari', 'Kettani', 'Lahlou', 'Mansouri', 'Naciri', 'Ouali', 'Pacha', 'Qadiri', 'Rahmani', 'Saadi',
            'Tazi', 'Uali', 'Verdi', 'Wahbi', 'Xalil', 'Yahya', 'Zaki', 'Achour', 'Bennani', 'Cherki',
            'Daoudi', 'El Mansouri', 'Fassi', 'Guerraoui', 'Hajji', 'Ibrahimi', 'Jouhari', 'Kettani', 'Lahlou', 'Mansouri'
        ];

        // Générer des combinaisons uniques
        $usedNames = [];
        $employees = [];
        
        for ($i = 0; $i < 2000; $i++) {
            do {
                $prenom = $prenoms[array_rand($prenoms)];
                $nom = $noms[array_rand($noms)];
                $fullName = $prenom . ' ' . $nom;
            } while (in_array($fullName, $usedNames));
            
            $usedNames[] = $fullName;
            $employees[] = [
                'prenom' => $prenom,
                'nom' => $nom,
                'email' => strtolower($prenom . '.' . $nom . '.' . rand(100, 999) . '@uiass.ma'),
                'username' => strtolower($prenom . $nom . rand(100, 999)),
                'telephone' => '06' . rand(10000000, 99999999),
                'password' => '$2y$12$5AsyzCDJUpvtVlX2e5CvD.xGGIK2vq92zOEHykeLOzdIM1SKN4cCu',
                'roles' => '["ROLE_EMPLOYEE"]',
                'is_active' => true
            ];
        }

        // Insérer par batch de 500 pour éviter les timeouts
        $batchSize = 500;
        $totalBatches = ceil(2000 / $batchSize);
        
        for ($batch = 0; $batch < $totalBatches; $batch++) {
            $start = $batch * $batchSize;
            $end = min($start + $batchSize, 2000);
            $batchEmployees = array_slice($employees, $start, $batchSize);
            
            $values = [];
            $params = [];
            foreach ($batchEmployees as $employee) {
                $values[] = "(nextval('t_employe_id_seq'), ?, ?, ?, ?, ?, ?, ?, ?)";
                $params[] = $employee['prenom'];
                $params[] = $employee['nom'];
                $params[] = $employee['email'];
                $params[] = $employee['username'];
                $params[] = $employee['telephone'];
                $params[] = $employee['password'];
                $params[] = $employee['roles'];
                $params[] = $employee['is_active'];
            }

            $sql = "INSERT INTO t_employe (id, prenom, nom, email, username, telephone, password, roles, is_active) VALUES " . implode(', ', $values);
            $connection->executeStatement($sql, $params);
            
            $io->text("Batch " . ($batch + 1) . "/{$totalBatches} inséré (" . ($end - $start) . " employés)");
        }

        $io->text("2000 employés insérés avec succès !");

        // 3. Récupérer les IDs des nouveaux employés
        $newEmployeeIds = $connection->executeQuery("SELECT id FROM t_employe ORDER BY id DESC LIMIT 2000")->fetchFirstColumn();
        $io->text("Récupérés " . count($newEmployeeIds) . " IDs d'employés");

        // 4. Créer les contrats avec la distribution spécifiée
        $io->section('Création des contrats...');
        
        // Récupérer les types de contrats et organisations
        $contractTypes = $connection->executeQuery("SELECT id FROM t_nature_contrat")->fetchFirstColumn();
        $organizations = $connection->executeQuery("SELECT id FROM t_organisation")->fetchFirstColumn();
        
        // Distribution: 81% 1 contrat, 14% 2 contrats, 4% 3 contrats, 1% 4-5 contrats
        $contractDistribution = [
            1 => 1620, // 81% avec 1 contrat
            2 => 280,  // 14% avec 2 contrats  
            3 => 80,   // 4% avec 3 contrats
            4 => 15,   // 0.75% avec 4 contrats
            5 => 5     // 0.25% avec 5 contrats
        ];

        $employeeIndex = 0;
        foreach ($contractDistribution as $contractCount => $employeeCount) {
            for ($i = 0; $i < $employeeCount && $employeeIndex < 2000; $i++) {
                $employeeId = $newEmployeeIds[$employeeIndex];
                
                // Créer les contrats pour cet employé
                for ($j = 0; $j < $contractCount; $j++) {
                    $contractTypeId = $contractTypes[array_rand($contractTypes)];
                    $organizationId = $organizations[array_rand($organizations)];
                    $salaire = rand(5000, 25000);
                    
                    // Insérer le contrat
                    $contractSql = "INSERT INTO t_employee_contrat (id, employe_id, nature_contrat_id, date_debut, date_fin, salaire, created_at) VALUES (nextval('t_employee_contrat_id_seq'), ?, ?, NOW(), NOW() + INTERVAL '1 year', ?, NOW())";
                    $connection->executeStatement($contractSql, [$employeeId, $contractTypeId, $salaire]);
                    
                    // Associer à l'organisation
                    $orgContractSql = "INSERT INTO t_organisation_employee_contrat (organisation_id, employee_contrat_id) VALUES (?, (SELECT id FROM t_employee_contrat WHERE employe_id = ? ORDER BY id DESC LIMIT 1))";
                    $connection->executeStatement($orgContractSql, [$organizationId, $employeeId]);
                }
                
                $employeeIndex++;
            }
            
            $io->text("Créés {$employeeCount} employés avec {$contractCount} contrat(s)");
        }

        // 5. Créer les dossiers pour tous les nouveaux employés
        $io->section('Création des dossiers...');
        $dossierSql = "
            INSERT INTO t_dossier (id, employe_id, nom, description, status, created_at, placard_id, emplacement)
            SELECT 
                nextval('t_dossier_id_seq'),
                e.id,
                'Dossier ' || e.prenom || ' ' || e.nom,
                'Dossier personnel de ' || e.prenom || ' ' || e.nom,
                CASE (random() * 4)::int
                    WHEN 0 THEN 'actif'
                    WHEN 1 THEN 'completed'
                    WHEN 2 THEN 'in_progress'
                    ELSE 'pending'
                END,
                NOW(),
                (SELECT id FROM p_placards ORDER BY random() LIMIT 1),
                NULL
            FROM t_employe e
            WHERE e.id IN (" . implode(',', $newEmployeeIds) . ")
        ";

        $dossierResult = $connection->executeStatement($dossierSql);
        $io->text("Créés {$dossierResult} dossiers");

        // 6. Statistiques finales
        $io->section('Statistiques finales');
        
        $totalEmployees = $connection->executeQuery("SELECT COUNT(*) FROM t_employe")->fetchOne();
        $totalDossiers = $connection->executeQuery("SELECT COUNT(*) FROM t_dossier")->fetchOne();
        $totalContrats = $connection->executeQuery("SELECT COUNT(*) FROM t_employee_contrat")->fetchOne();
        $totalPlacards = $connection->executeQuery("SELECT COUNT(*) FROM p_placards")->fetchOne();

        $io->text("Total employés: {$totalEmployees}");
        $io->text("Total dossiers: {$totalDossiers}");
        $io->text("Total contrats: {$totalContrats}");
        $io->text("Total placards: {$totalPlacards}");

        // Vérification de la distribution des contrats
        $io->section('Vérification distribution contrats');
        $distributionSql = "
            SELECT 
                COUNT(ec.id) as contract_count,
                COUNT(*) as employee_count
            FROM t_employe e
            LEFT JOIN t_employee_contrat ec ON e.id = ec.employe_id
            WHERE e.id IN (" . implode(',', $newEmployeeIds) . ")
            GROUP BY e.id
            ORDER BY contract_count
        ";

        $distribution = $connection->executeQuery($distributionSql)->fetchAllAssociative();
        $contractStats = [];
        foreach ($distribution as $row) {
            $count = $row['contract_count'];
            $contractStats[$count] = ($contractStats[$count] ?? 0) + 1;
        }

        foreach ($contractStats as $contractCount => $employeeCount) {
            $percentage = round(($employeeCount / 2000) * 100, 2);
            $io->text("Employés avec {$contractCount} contrat(s): {$employeeCount} ({$percentage}%)");
        }

        $io->success("Insertion terminée avec succès !");
        return Command::SUCCESS;
    }
}

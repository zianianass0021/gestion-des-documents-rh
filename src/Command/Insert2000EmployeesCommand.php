<?php

namespace App\Command;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:insert-2000-employees',
    description: 'Insert 2000 new employees with unique names and specific contract distribution',
)]
class Insert2000EmployeesCommand extends Command
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

        $io->title('Insertion de 2000 Nouveaux Employés');

        $connection = $this->entityManager->getConnection();

        // Créer d'abord de nouveaux placards
        $io->section('Création de nouveaux placards...');
        $placardNames = [
            'Archives Centrales',
            'Dossiers Financiers',
            'Ressources Humaines',
            'Services Techniques',
            'Direction Générale',
            'Comptabilité',
            'Informatique',
            'Marketing',
            'Ventes',
            'Production'
        ];

        $placardIds = [];
        foreach ($placardNames as $name) {
            $location = 'Bâtiment ' . chr(65 + array_rand(range(0, 25))) . ' - Étage ' . rand(1, 5);
            $sql = "INSERT INTO p_placards (id, name, location, created_at) VALUES (nextval('p_placards_id_seq'), ?, ?, NOW())";
            $connection->executeStatement($sql, [$name, $location]);
            $placardIds[] = $connection->lastInsertId();
        }

        $io->text("Créés " . count($placardIds) . " nouveaux placards");

        // Générer des noms marocains uniques
        $io->section('Génération des noms uniques...');
        $prenoms = [
            'Ahmed', 'Mohamed', 'Hassan', 'Omar', 'Youssef', 'Karim', 'Rachid', 'Said', 'Ali', 'Mustapha',
            'Fatima', 'Aicha', 'Khadija', 'Zineb', 'Naima', 'Samira', 'Latifa', 'Malika', 'Hakima', 'Souad',
            'Abdel', 'Abdellah', 'Abderrahman', 'Abdelkader', 'Abdelaziz', 'Abdelhak', 'Abdelmajid', 'Abdelouahed',
            'Amina', 'Houda', 'Nadia', 'Rachida', 'Saida', 'Touria', 'Widad', 'Yasmina', 'Zakia', 'Halima',
            'Brahim', 'Chakib', 'Driss', 'Fouad', 'Ghassan', 'Hicham', 'Ibrahim', 'Jamal', 'Khalid', 'Lahcen',
            'Mounir', 'Nabil', 'Othman', 'Reda', 'Salah', 'Tarik', 'Walid', 'Yassine', 'Zakaria', 'Adil',
            'Badr', 'Chadi', 'Dounia', 'Elyas', 'Fadi', 'Ghita', 'Hajar', 'Imane', 'Jihane', 'Kenza',
            'Lina', 'Meryem', 'Nour', 'Oumaima', 'Rania', 'Salma', 'Tasnim', 'Wiam', 'Yara', 'Zineb'
        ];

        $noms = [
            'Alaoui', 'Benali', 'Chraibi', 'Dakir', 'El Fassi', 'Gharbi', 'Hassani', 'Idrissi', 'Jabri', 'Kabbaj',
            'Lahlou', 'Mansouri', 'Naciri', 'Ouali', 'Pacha', 'Qadiri', 'Rahmani', 'Saadi', 'Tazi', 'Uali',
            'Verdi', 'Wahbi', 'Xalil', 'Yahya', 'Zaki', 'Achour', 'Bennani', 'Cherki', 'Daoudi', 'El Mansouri',
            'Fassi', 'Guerraoui', 'Hajji', 'Ibrahimi', 'Jouhari', 'Kettani', 'Lahlou', 'Mansouri', 'Naciri', 'Ouali',
            'Pacha', 'Qadiri', 'Rahmani', 'Saadi', 'Tazi', 'Uali', 'Verdi', 'Wahbi', 'Xalil', 'Yahya',
            'Zaki', 'Achour', 'Bennani', 'Cherki', 'Daoudi', 'El Mansouri', 'Fassi', 'Guerraoui', 'Hajji', 'Ibrahimi',
            'Jouhari', 'Kettani', 'Lahlou', 'Mansouri', 'Naciri', 'Ouali', 'Pacha', 'Qadiri', 'Rahmani', 'Saadi',
            'Tazi', 'Uali', 'Verdi', 'Wahbi', 'Xalil', 'Yahya', 'Zaki', 'Achour', 'Bennani', 'Cherki'
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
                'email' => strtolower($prenom . '.' . $nom . '@uiass.ma'),
                'username' => strtolower($prenom . $nom . rand(100, 999)),
                'telephone' => '06' . rand(10000000, 99999999),
                'password' => password_hash('password123', PASSWORD_DEFAULT),
                'roles' => '["ROLE_EMPLOYEE"]',
                'is_active' => true
            ];
        }

        $io->text("Générés 2000 noms uniques");

        // Insérer les employés
        $io->section('Insertion des employés...');
        $progressBar = $io->createProgressBar(2000);
        $progressBar->start();

        $batchSize = 100;
        for ($i = 0; $i < 2000; $i += $batchSize) {
            $batch = array_slice($employees, $i, $batchSize);
            
            $values = [];
            $params = [];
            foreach ($batch as $index => $employee) {
                $baseIndex = $i + $index;
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
            
            $progressBar->advance($batchSize);
        }
        $progressBar->finish();

        $io->newLine(2);
        $io->success("2000 employés insérés avec succès !");

        // Maintenant créer les contrats avec la distribution spécifiée
        $io->section('Création des contrats...');
        
        // Récupérer les types de contrats et organisations existants
        $contractTypes = $connection->executeQuery("SELECT id FROM t_nature_contrat")->fetchFirstColumn();
        $organizations = $connection->executeQuery("SELECT id FROM t_organisation")->fetchFirstColumn();
        
        if (empty($contractTypes) || empty($organizations)) {
            $io->error('Types de contrats ou organisations manquants !');
            return Command::FAILURE;
        }

        // Récupérer les IDs des nouveaux employés
        $newEmployeeIds = $connection->executeQuery("SELECT id FROM t_employe ORDER BY id DESC LIMIT 2000")->fetchFirstColumn();
        
        // Distribution des contrats
        $contractDistribution = [
            1 => 1620, // 81% avec 1 contrat
            2 => 280,  // 14% avec 2 contrats  
            3 => 80,   // 4% avec 3 contrats
            4 => 15,   // 0.75% avec 4 contrats
            5 => 5     // 0.25% avec 5 contrats
        ];

        $employeeIndex = 0;
        $progressBar = $io->createProgressBar(2000);
        $progressBar->start();

        foreach ($contractDistribution as $contractCount => $employeeCount) {
            for ($i = 0; $i < $employeeCount && $employeeIndex < 2000; $i++) {
                $employeeId = $newEmployeeIds[$employeeIndex];
                
                // Créer les contrats pour cet employé
                for ($j = 0; $j < $contractCount; $j++) {
                    $contractTypeId = $contractTypes[array_rand($contractTypes)];
                    $organizationId = $organizations[array_rand($organizations)];
                    
                    // Insérer le contrat
                    $contractSql = "INSERT INTO t_employee_contrat (id, employe_id, nature_contrat_id, date_debut, date_fin, salaire, created_at) VALUES (nextval('t_employee_contrat_id_seq'), ?, ?, NOW(), NOW() + INTERVAL '1 year', ?, NOW())";
                    $salaire = rand(5000, 25000);
                    $connection->executeStatement($contractSql, [$employeeId, $contractTypeId, $salaire]);
                    
                    // Associer à l'organisation
                    $orgContractSql = "INSERT INTO t_organisation_employee_contrat (organisation_id, employee_contrat_id) VALUES (?, (SELECT id FROM t_employee_contrat WHERE employe_id = ? ORDER BY id DESC LIMIT 1))";
                    $connection->executeStatement($orgContractSql, [$organizationId, $employeeId]);
                }
                
                $employeeIndex++;
                $progressBar->advance();
            }
        }
        $progressBar->finish();

        $io->newLine(2);
        $io->success("Contrats créés avec la distribution spécifiée !");

        // Créer les dossiers pour tous les nouveaux employés
        $io->section('Création des dossiers...');
        
        $dossierSql = "
            INSERT INTO t_dossier (id, employe_id, nom, description, status, created_at, placard_id, emplacement)
            SELECT 
                nextval('t_dossier_id_seq') as id,
                e.id as employe_id,
                'Dossier ' || e.prenom || ' ' || e.nom as nom,
                'Dossier personnel de ' || e.prenom || ' ' || e.nom as description,
                CASE (random() * 4)::int
                    WHEN 0 THEN 'actif'
                    WHEN 1 THEN 'completed'
                    WHEN 2 THEN 'in_progress'
                    ELSE 'pending'
                END as status,
                NOW() as created_at,
                p_placards.id as placard_id,
                NULL as emplacement
            FROM t_employe e
            CROSS JOIN LATERAL (
                SELECT id FROM p_placards 
                ORDER BY random() 
                LIMIT 1
            ) p_placards
            WHERE e.id IN (" . implode(',', $newEmployeeIds) . ")
        ";

        $result = $connection->executeStatement($dossierSql);
        $io->success("{$result} dossiers créés !");

        // Statistiques finales
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
        $contractStats = $connection->executeQuery("
            SELECT 
                COUNT(*) as employee_count,
                COUNT(ec.id) as contract_count
            FROM t_employe e
            LEFT JOIN t_employee_contrat ec ON e.id = ec.employe_id
            WHERE e.id IN (" . implode(',', $newEmployeeIds) . ")
            GROUP BY e.id
            ORDER BY contract_count
        ")->fetchAllAssociative();

        $distribution = [];
        foreach ($contractStats as $stat) {
            $count = $stat['contract_count'];
            $distribution[$count] = ($distribution[$count] ?? 0) + 1;
        }

        foreach ($distribution as $contractCount => $employeeCount) {
            $percentage = round(($employeeCount / 2000) * 100, 2);
            $io->text("Employés avec {$contractCount} contrat(s): {$employeeCount} ({$percentage}%)");
        }

        return Command::SUCCESS;
    }
}

<?php

namespace App\Command;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:insert-2000-employees-fast',
    description: 'Insert 2000 new employees using fast SQL approach',
)]
class Insert2000EmployeesFastCommand extends Command
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

        $io->title('Insertion Rapide de 2000 Nouveaux Employés');

        $connection = $this->entityManager->getConnection();

        // 1. Créer de nouveaux placards rapidement
        $io->section('Création de nouveaux placards...');
        $placardSql = "
            INSERT INTO p_placards (id, name, location, created_at)
            SELECT 
                nextval('p_placards_id_seq'),
                placard_names.name,
                'Bâtiment ' || chr(65 + (random() * 26)::int) || ' - Étage ' || (random() * 5 + 1)::int,
                NOW()
            FROM (
                VALUES 
                ('Archives Centrales'), ('Dossiers Financiers'), ('Ressources Humaines'),
                ('Services Techniques'), ('Direction Générale'), ('Comptabilité'),
                ('Informatique'), ('Marketing'), ('Ventes'), ('Production'),
                ('Archives Secrétariat'), ('Dossiers Juridiques'), ('RH Temporaires'),
                ('Archives Anciennes'), ('Dossiers Spéciaux'), ('Archives Digitales')
            ) AS placard_names(name)
        ";
        
        $placardResult = $connection->executeStatement($placardSql);
        $io->text("Créés {$placardResult} nouveaux placards");

        // 2. Insérer 2000 employés avec des noms uniques en une seule requête
        $io->section('Insertion des 2000 employés...');
        $employeeSql = "
            WITH RECURSIVE employee_data AS (
                SELECT 
                    nextval('t_employe_id_seq') as id,
                    prenoms.prenom,
                    noms.nom,
                    LOWER(prenoms.prenom || '.' || noms.nom || '.' || (random() * 999 + 100)::int) as email,
                    LOWER(prenoms.prenom || noms.nom || (random() * 999 + 100)::int) as username,
                    '06' || (random() * 99999999 + 10000000)::int as telephone,
                    '$2y$12$5AsyzCDJUpvtVlX2e5CvD.xGGIK2vq92zOEHykeLOzdIM1SKN4cCu' as password,
                    '[\"ROLE_EMPLOYEE\"]' as roles,
                    true as is_active,
                    ROW_NUMBER() OVER() as row_num
                FROM (
                    VALUES 
                    ('Ahmed'), ('Mohamed'), ('Hassan'), ('Omar'), ('Youssef'), ('Karim'), ('Rachid'), ('Said'), ('Ali'), ('Mustapha'),
                    ('Fatima'), ('Aicha'), ('Khadija'), ('Zineb'), ('Naima'), ('Samira'), ('Latifa'), ('Malika'), ('Hakima'), ('Souad'),
                    ('Abdel'), ('Abdellah'), ('Abderrahman'), ('Abdelkader'), ('Abdelaziz'), ('Abdelhak'), ('Abdelmajid'), ('Abdelouahed'),
                    ('Amina'), ('Houda'), ('Nadia'), ('Rachida'), ('Saida'), ('Touria'), ('Widad'), ('Yasmina'), ('Zakia'), ('Halima'),
                    ('Brahim'), ('Chakib'), ('Driss'), ('Fouad'), ('Ghassan'), ('Hicham'), ('Ibrahim'), ('Jamal'), ('Khalid'), ('Lahcen'),
                    ('Mounir'), ('Nabil'), ('Othman'), ('Reda'), ('Salah'), ('Tarik'), ('Walid'), ('Yassine'), ('Zakaria'), ('Adil'),
                    ('Badr'), ('Chadi'), ('Dounia'), ('Elyas'), ('Fadi'), ('Ghita'), ('Hajar'), ('Imane'), ('Jihane'), ('Kenza'),
                    ('Lina'), ('Meryem'), ('Nour'), ('Oumaima'), ('Rania'), ('Salma'), ('Tasnim'), ('Wiam'), ('Yara'), ('Zineb'),
                    ('Achraf'), ('Anouar'), ('Anass'), ('Younes'), ('Mehdi'), ('Soufiane'), ('Hamza'), ('Ayoub'), ('Imad'), ('Ziad')
                ) AS prenoms(prenom)
                CROSS JOIN (
                    VALUES 
                    ('Alaoui'), ('Benali'), ('Chraibi'), ('Dakir'), ('El Fassi'), ('Gharbi'), ('Hassani'), ('Idrissi'), ('Jabri'), ('Kabbaj'),
                    ('Lahlou'), ('Mansouri'), ('Naciri'), ('Ouali'), ('Pacha'), ('Qadiri'), ('Rahmani'), ('Saadi'), ('Tazi'), ('Uali'),
                    ('Verdi'), ('Wahbi'), ('Xalil'), ('Yahya'), ('Zaki'), ('Achour'), ('Bennani'), ('Cherki'), ('Daoudi'), ('El Mansouri'),
                    ('Fassi'), ('Guerraoui'), ('Hajji'), ('Ibrahimi'), ('Jouhari'), ('Kettani'), ('Lahlou'), ('Mansouri'), ('Naciri'), ('Ouali'),
                    ('Pacha'), ('Qadiri'), ('Rahmani'), ('Saadi'), ('Tazi'), ('Uali'), ('Verdi'), ('Wahbi'), ('Xalil'), ('Yahya'),
                    ('Zaki'), ('Achour'), ('Bennani'), ('Cherki'), ('Daoudi'), ('El Mansouri'), ('Fassi'), ('Guerraoui'), ('Hajji'), ('Ibrahimi'),
                    ('Jouhari'), ('Kettani'), ('Lahlou'), ('Mansouri'), ('Naciri'), ('Ouali'), ('Pacha'), ('Qadiri'), ('Rahmani'), ('Saadi'),
                    ('Tazi'), ('Uali'), ('Verdi'), ('Wahbi'), ('Xalil'), ('Yahya'), ('Zaki'), ('Achour'), ('Bennani'), ('Cherki'),
                    ('Daoudi'), ('El Mansouri'), ('Fassi'), ('Guerraoui'), ('Hajji'), ('Ibrahimi'), ('Jouhari'), ('Kettani'), ('Lahlou'), ('Mansouri')
                ) AS noms(nom)
                WHERE ROW_NUMBER() OVER() <= 2000
            )
            INSERT INTO t_employe (id, prenom, nom, email, username, telephone, password, roles, is_active)
            SELECT id, prenom, nom, email, username, telephone, password, roles, is_active
            FROM employee_data
            ORDER BY random()
            LIMIT 2000
        ";

        $employeeResult = $connection->executeStatement($employeeSql);
        $io->text("Insérés {$employeeResult} employés");

        // 3. Récupérer les IDs des nouveaux employés
        $newEmployeeIds = $connection->executeQuery("SELECT id FROM t_employe ORDER BY id DESC LIMIT 2000")->fetchFirstColumn();
        $io->text("Récupérés " . count($newEmployeeIds) . " IDs d'employés");

        // 4. Créer les contrats avec la distribution spécifiée
        $io->section('Création des contrats...');
        
        // Récupérer les types de contrats et organisations
        $contractTypes = $connection->executeQuery("SELECT id FROM t_nature_contrat")->fetchFirstColumn();
        $organizations = $connection->executeQuery("SELECT id FROM t_organisation")->fetchFirstColumn();
        
        // Distribution: 81% 1 contrat, 14% 2 contrats, 4% 3 contrats, 1% 4-5 contrats
        $contractSql = "
            WITH employee_contracts AS (
                SELECT 
                    e.id as employee_id,
                    CASE 
                        WHEN e.id % 100 < 81 THEN 1  -- 81% avec 1 contrat
                        WHEN e.id % 100 < 95 THEN 2  -- 14% avec 2 contrats
                        WHEN e.id % 100 < 99 THEN 3  -- 4% avec 3 contrats
                        ELSE 4 + (e.id % 2)         -- 1% avec 4-5 contrats
                    END as contract_count
                FROM t_employe e
                WHERE e.id IN (" . implode(',', $newEmployeeIds) . ")
            ),
            contract_data AS (
                SELECT 
                    ec.employee_id,
                    ec.contract_count,
                    generate_series(1, ec.contract_count) as contract_num,
                    (SELECT id FROM t_nature_contrat ORDER BY random() LIMIT 1) as contract_type_id,
                    (SELECT id FROM t_organisation ORDER BY random() LIMIT 1) as org_id,
                    5000 + (random() * 20000)::int as salaire
                FROM employee_contracts ec
            )
            INSERT INTO t_employee_contrat (id, employe_id, nature_contrat_id, date_debut, date_fin, salaire, created_at)
            SELECT 
                nextval('t_employee_contrat_id_seq'),
                cd.employee_id,
                cd.contract_type_id,
                NOW(),
                NOW() + INTERVAL '1 year',
                cd.salaire,
                NOW()
            FROM contract_data cd
        ";

        $contractResult = $connection->executeStatement($contractSql);
        $io->text("Créés {$contractResult} contrats");

        // 5. Associer les contrats aux organisations
        $io->section('Association contrats-organisations...');
        $orgContractSql = "
            INSERT INTO t_organisation_employee_contrat (organisation_id, employee_contrat_id)
            SELECT 
                (SELECT id FROM t_organisation ORDER BY random() LIMIT 1),
                ec.id
            FROM t_employee_contrat ec
            WHERE ec.employe_id IN (" . implode(',', $newEmployeeIds) . ")
        ";

        $orgContractResult = $connection->executeStatement($orgContractSql);
        $io->text("Associés {$orgContractResult} contrats aux organisations");

        // 6. Créer les dossiers pour tous les nouveaux employés
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

        // 7. Statistiques finales
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
                COUNT(*) as employee_count,
                ROUND(COUNT(*) * 100.0 / 2000, 2) as percentage
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

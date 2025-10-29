<?php

namespace App\Command;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:insert-2000-final-employees',
    description: 'Insert 2000 final employees using fast SQL approach',
)]
class Insert2000FinalEmployeesCommand extends Command
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
        $io->title('Insertion de 2000 Employés Finaux');

        $connection = $this->entityManager->getConnection();

        // Insérer 2000 employés en une seule requête
        $io->section('Insertion des 2000 employés...');
        $sql = "INSERT INTO t_employe (id, prenom, nom, email, username, telephone, password, roles, is_active) 
                SELECT 
                    nextval('t_employe_id_seq'),
                    'Hassan' || i,
                    'Chraibi' || i,
                    'hassan' || i || '@uiass.ma',
                    'hassan' || i,
                    '06' || (random() * 99999999 + 10000000)::int,
                    '$2y$12$5AsyzCDJUpvtVlX2e5CvD.xGGIK2vq92zOEHykeLOzdIM1SKN4cCu',
                    '[\"ROLE_EMPLOYEE\"]',
                    true
                FROM generate_series(1, 2000) AS i";

        $result = $connection->executeStatement($sql);
        $io->text("Insérés {$result} employés");

        // Récupérer les IDs des nouveaux employés
        $newEmployeeIds = $connection->executeQuery("SELECT id FROM t_employe ORDER BY id DESC LIMIT 2000")->fetchFirstColumn();
        $io->text("Récupérés " . count($newEmployeeIds) . " IDs d'employés");

        // Créer les contrats avec distribution
        $io->section('Création des contrats...');
        $contractSql = "
            WITH employee_contracts AS (
                SELECT 
                    e.id as employee_id,
                    CASE 
                        WHEN e.id % 100 < 81 THEN 1
                        WHEN e.id % 100 < 95 THEN 2
                        WHEN e.id % 100 < 99 THEN 3
                        ELSE 4 + (e.id % 2)
                    END as contract_count
                FROM t_employe e
                WHERE e.id IN (" . implode(',', $newEmployeeIds) . ")
            ),
            contract_data AS (
                SELECT 
                    ec.employee_id,
                    ec.contract_count,
                    generate_series(1, ec.contract_count) as contract_num,
                    (SELECT id FROM p_nature_contrat ORDER BY random() LIMIT 1) as contract_type_id,
                    (SELECT id FROM p_organisation ORDER BY random() LIMIT 1) as org_id,
                    5000 + (random() * 20000)::int as salaire
                FROM employee_contracts ec
            )
            INSERT INTO t_employee_contrat (id, employe_id, nature_contrat_id, date_debut, date_fin, statut, salaire)
            SELECT 
                nextval('t_employee_contrat_id_seq'),
                cd.employee_id,
                cd.contract_type_id,
                CURRENT_DATE,
                CURRENT_DATE + INTERVAL '1 year',
                'actif',
                cd.salaire
            FROM contract_data cd
        ";

        $contractResult = $connection->executeStatement($contractSql);
        $io->text("Créés {$contractResult} contrats");

        // Associer aux organisations (différentes organisations)
        $io->section('Association aux organisations...');
        $orgContractSql = "
            INSERT INTO t_organisation_employee_contrat (id, organisation_id, employee_contrat_id, date_debut, date_fin)
            SELECT 
                nextval('t_organisation_employee_contrat_id_seq'),
                (SELECT id FROM p_organisation ORDER BY random() LIMIT 1),
                ec.id,
                CURRENT_DATE,
                CURRENT_DATE + INTERVAL '1 year'
            FROM t_employee_contrat ec
            WHERE ec.employe_id IN (" . implode(',', $newEmployeeIds) . ")
        ";

        $orgContractResult = $connection->executeStatement($orgContractSql);
        $io->text("Associés {$orgContractResult} contrats aux organisations");

        // Créer les dossiers (utiliser les placards existants)
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

        // Statistiques finales
        $io->section('Statistiques finales');
        $totalEmployees = $connection->executeQuery("SELECT COUNT(*) FROM t_employe")->fetchOne();
        $totalDossiers = $connection->executeQuery("SELECT COUNT(*) FROM t_dossier")->fetchOne();
        $totalContrats = $connection->executeQuery("SELECT COUNT(*) FROM t_employee_contrat")->fetchOne();
        $totalPlacards = $connection->executeQuery("SELECT COUNT(*) FROM p_placards")->fetchOne();
        $totalOrganisations = $connection->executeQuery("SELECT COUNT(*) FROM p_organisation")->fetchOne();

        $io->text("Total employés: {$totalEmployees}");
        $io->text("Total dossiers: {$totalDossiers}");
        $io->text("Total contrats: {$totalContrats}");
        $io->text("Total placards: {$totalPlacards}");
        $io->text("Total organisations: {$totalOrganisations}");

        // Vérification de la distribution des organisations
        $io->section('Distribution des organisations');
        $orgDistribution = $connection->executeQuery("
            SELECT 
                o.designation as organisation,
                COUNT(oec.id) as nombre_contrats
            FROM p_organisation o
            LEFT JOIN t_organisation_employee_contrat oec ON o.id = oec.organisation_id
            GROUP BY o.id, o.designation
            ORDER BY nombre_contrats DESC
            LIMIT 10
        ")->fetchAllAssociative();

        foreach ($orgDistribution as $org) {
            $io->text("{$org['organisation']}: {$org['nombre_contrats']} contrats");
        }

        $io->success("Insertion terminée avec succès !");
        return Command::SUCCESS;
    }
}

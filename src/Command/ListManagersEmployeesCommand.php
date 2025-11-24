<?php

namespace App\Command;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:list-managers-employees',
    description: 'Liste les employés assignés à chaque manager avec leur type de dossier',
)]
class ListManagersEmployeesCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Liste des managers et leurs employés assignés');

        $conn = $this->entityManager->getConnection();

        // Récupérer tous les managers avec leur dossier géré
        $sql = 'SELECT e.id, e.nom, e.prenom, e.email, e.dossier_gere
                FROM t_user e
                WHERE CAST(e.roles AS TEXT) LIKE :role
                AND e.dossier_gere IS NOT NULL
                AND e.is_active = :active
                ORDER BY e.dossier_gere, e.nom, e.prenom';
        
        $stmt = $conn->prepare($sql);
        $result = $stmt->executeQuery([
            'role' => '%ROLE_MANAGER%',
            'active' => true
        ]);
        
        $managers = $result->fetchAllAssociative();

        if (empty($managers)) {
            $io->warning('Aucun manager trouvé avec un dossier assigné.');
            return Command::SUCCESS;
        }

        $io->info(sprintf('Trouvé %d manager(s).', count($managers)));

        $totalEmployees = 0;

        foreach ($managers as $manager) {
            $managerId = $manager['id'];
            $managerName = sprintf('%s %s (%s)', $manager['prenom'], $manager['nom'], $manager['email']);
            $dossierGere = $manager['dossier_gere'];

            $io->section(sprintf('Manager: %s - Dossier géré: %s', $managerName, $dossierGere));

            // Récupérer les employés dont le dossier a le même type que celui géré par le manager
            $employeesSql = 'SELECT e.id, e.nom, e.prenom, e.email, d.dossier_code, d.nom as dossier_nom
                            FROM t_user e
                            INNER JOIN t_dossier d ON e.id = d.employe_id
                            WHERE d.dossier_code = :dossier_code
                            AND CAST(e.roles AS TEXT) LIKE :role
                            AND e.is_active = :active
                            AND e.id != :manager_id
                            ORDER BY e.nom, e.prenom';
            
            $employeesStmt = $conn->prepare($employeesSql);
            $employeesResult = $employeesStmt->executeQuery([
                'dossier_code' => $dossierGere,
                'role' => '%ROLE_EMPLOYEE%',
                'active' => true,
                'manager_id' => $managerId
            ]);

            $employees = $employeesResult->fetchAllAssociative();

            if (empty($employees)) {
                $io->writeln('  <comment>Aucun employé assigné</comment>');
            } else {
                $tableData = [];
                foreach ($employees as $employee) {
                    $tableData[] = [
                        $employee['id'],
                        sprintf('%s %s', $employee['prenom'], $employee['nom']),
                        $employee['email'],
                        $employee['dossier_code'] ?? '<comment>N/A</comment>',
                        $employee['dossier_nom'] ?? '<comment>N/A</comment>',
                    ];
                }
                
                $io->table(
                    ['ID', 'Nom complet', 'Email', 'Type dossier', 'Nom dossier'],
                    $tableData
                );
                
                $io->writeln(sprintf('  <info>Total: %d employé(s)</info>', count($employees)));
                $totalEmployees += count($employees);
            }
            
            $io->newLine();
        }

        $io->section('Résumé global');
        $io->table(['Statut', 'Nombre'], [
            ['Managers actifs', count($managers)],
            ['Employés assignés (total)', $totalEmployees],
            ['Moyenne par manager', count($managers) > 0 ? round($totalEmployees / count($managers), 2) : 0],
        ]);

        // Statistiques par type de dossier
        $statsSql = 'SELECT e.dossier_gere, COUNT(DISTINCT e2.id) as employee_count
                     FROM t_user e
                     LEFT JOIN t_dossier d ON d.dossier_code = e.dossier_gere
                     LEFT JOIN t_user e2 ON e2.id = d.employe_id
                     WHERE CAST(e.roles AS TEXT) LIKE :role
                     AND e.dossier_gere IS NOT NULL
                     AND e.is_active = :active
                     AND (e2.id IS NULL OR (CAST(e2.roles AS TEXT) LIKE :role2 AND e2.is_active = :active2))
                     GROUP BY e.dossier_gere
                     ORDER BY employee_count DESC, e.dossier_gere';
        
        $statsStmt = $conn->prepare($statsSql);
        $statsResult = $statsStmt->executeQuery([
            'role' => '%ROLE_MANAGER%',
            'active' => true,
            'role2' => '%ROLE_EMPLOYEE%',
            'active2' => true
        ]);

        $stats = $statsResult->fetchAllAssociative();

        if (!empty($stats)) {
            $io->section('Répartition par type de dossier');
            $statsTable = [];
            foreach ($stats as $stat) {
                $statsTable[] = [
                    $stat['dossier_gere'],
                    $stat['employee_count'],
                ];
            }
            $io->table(['Type dossier', 'Nombre d\'employés'], $statsTable);
        }

        return Command::SUCCESS;
    }
}


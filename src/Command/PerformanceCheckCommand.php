<?php

namespace App\Command;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:perf:check',
    description: 'Performance check: ensure pagination and dashboard load times are acceptable',
)]
class PerformanceCheckCommand extends Command
{
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        parent::__construct();
        $this->entityManager = $entityManager;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Performance Check');

        $thresholds = [
            'employee_pagination' => 100, // ms
            'dossier_pagination' => 100,
            'dashboard_load' => 100,
        ];

        $io->section('Performance Thresholds');
        $io->table(['Operation', 'Threshold'], [
            ['Employee Pagination', $thresholds['employee_pagination'] . 'ms'],
            ['Dossier Pagination', $thresholds['dossier_pagination'] . 'ms'],
            ['Dashboard Load', $thresholds['dashboard_load'] . 'ms'],
        ]);

        $results = [];

        // Test 1: Employee pagination
        $io->section('1. Testing Employee Pagination');
        $results['employee_pagination'] = $this->testEmployeePagination($io, $thresholds['employee_pagination']);

        // Test 2: Dossier pagination
        $io->section('2. Testing Dossier Pagination');
        $results['dossier_pagination'] = $this->testDossierPagination($io, $thresholds['dossier_pagination']);

        // Test 3: Dashboard data loading
        $io->section('3. Testing Dashboard Data Loading');
        $results['dashboard_load'] = $this->testDashboardLoad($io, $thresholds['dashboard_load']);

        // Summary
        $io->newLine();
        $io->section('Performance Summary');

        $tableData = [];
        foreach ($results as $test => $result) {
            $status = $result['pass'] ? '✓ PASS' : '✗ FAIL';
            $color = $result['pass'] ? 'green' : 'red';
            
            $tableData[] = [
                ucfirst(str_replace('_', ' ', $test)),
                $result['duration'] . 'ms',
                $thresholds[$test] . 'ms',
                $status,
                $result['details'] ?? '-',
            ];
        }

        $io->table(['Test', 'Duration', 'Threshold', 'Status', 'Details'], $tableData);

        $allPassed = !in_array(false, array_column($results, 'pass'));

        if ($allPassed) {
            $io->success('All performance checks passed!');
            return Command::SUCCESS;
        } else {
            $io->warning('Some performance checks failed. Consider optimization.');
            return Command::FAILURE;
        }
    }

    private function testEmployeePagination(SymfonyStyle $io, int $threshold): array
    {
        $startTime = microtime(true);

        try {
            $conn = $this->entityManager->getConnection();
            
            // Simulate the pagination query used in ResponsableRhController
            $limit = 10;
            $offset = 0;
            
            $countSql = 'SELECT COUNT(e.id) FROM t_user e 
                        WHERE e.is_active = true AND CAST(e.roles AS TEXT) LIKE :role';
            
            $idsSql = 'SELECT e.id FROM t_user e 
                      WHERE e.is_active = true AND CAST(e.roles AS TEXT) LIKE :role 
                      ORDER BY e.nom ASC 
                      LIMIT :limit OFFSET :offset';

            // Count query
            $stmt = $conn->prepare($countSql);
            $result = $stmt->executeQuery(['role' => '%ROLE_EMPLOYEE%']);
            $totalCount = $result->fetchOne();

            // IDs query
            $stmt = $conn->prepare($idsSql);
            $stmt->bindValue('role', '%ROLE_EMPLOYEE%', \PDO::PARAM_STR);
            $stmt->bindValue('limit', $limit, \PDO::PARAM_INT);
            $stmt->bindValue('offset', $offset, \PDO::PARAM_INT);
            $result = $stmt->execute();
            $rows = $result->fetchAllAssociative();
            $ids = array_column($rows, 'id');

            // Fetch entities (if IDs found)
            if (!empty($ids)) {
                $qb = $this->entityManager->createQueryBuilder();
                $employees = $qb->select('e')
                    ->from('App\Entity\Employe', 'e')
                    ->where('e.id IN (:ids)')
                    ->setParameter('ids', $ids)
                    ->setMaxResults($limit)
                    ->getQuery()
                    ->getResult();
            }

            $duration = round((microtime(true) - $startTime) * 1000, 2);
            $pass = $duration <= $threshold;

            $io->text(sprintf(
                "  %s Employee pagination: %sms (threshold: %sms) - Found %d employees",
                $pass ? '✓' : '✗',
                $duration,
                $threshold,
                $totalCount
            ));

            return [
                'pass' => $pass,
                'duration' => $duration,
                'details' => "Total: {$totalCount}, Page: " . min(count($ids ?? []), $limit),
            ];

        } catch (\Exception $e) {
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            $io->error('  ✗ Employee pagination test failed: ' . $e->getMessage());
            
            return [
                'pass' => false,
                'duration' => $duration,
                'details' => 'ERROR: ' . substr($e->getMessage(), 0, 50),
            ];
        }
    }

    private function testDossierPagination(SymfonyStyle $io, int $threshold): array
    {
        $startTime = microtime(true);

        try {
            // Simulate dossier pagination query
            $limit = 10;
            
            $qb = $this->entityManager->createQueryBuilder();
            $totalCount = $qb->select('COUNT(d.id)')
                ->from('App\Entity\Dossier', 'd')
                ->getQuery()
                ->getSingleScalarResult();

            $qb = $this->entityManager->createQueryBuilder();
            $dossiers = $qb->select('d')
                ->from('App\Entity\Dossier', 'd')
                ->setMaxResults($limit)
                ->getQuery()
                ->getResult();

            $duration = round((microtime(true) - $startTime) * 1000, 2);
            $pass = $duration <= $threshold;

            $io->text(sprintf(
                "  %s Dossier pagination: %sms (threshold: %sms) - Found %d dossiers",
                $pass ? '✓' : '✗',
                $duration,
                $threshold,
                $totalCount
            ));

            return [
                'pass' => $pass,
                'duration' => $duration,
                'details' => "Total: {$totalCount}, Loaded: " . count($dossiers),
            ];

        } catch (\Exception $e) {
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            $io->error('  ✗ Dossier pagination test failed: ' . $e->getMessage());
            
            return [
                'pass' => false,
                'duration' => $duration,
                'details' => 'ERROR: ' . substr($e->getMessage(), 0, 50),
            ];
        }
    }

    private function testDashboardLoad(SymfonyStyle $io, int $threshold): array
    {
        $startTime = microtime(true);

        try {
            // Simulate dashboard data loading (minimal - just counts)
            $conn = $this->entityManager->getConnection();
            
            // Count employees
            $employeeCount = $conn->executeQuery('SELECT COUNT(*) FROM t_user')->fetchOne();
            
            // Count dossiers
            $dossierCount = $conn->executeQuery('SELECT COUNT(*) FROM t_dossier')->fetchOne();
            
            // Count documents
            $documentCount = $conn->executeQuery('SELECT COUNT(*) FROM p_document')->fetchOne();
            
            // Count contrats
            $contratCount = $conn->executeQuery('SELECT COUNT(*) FROM t_employee_contrat')->fetchOne();

            $duration = round((microtime(true) - $startTime) * 1000, 2);
            $pass = $duration <= $threshold;

            $io->text(sprintf(
                "  %s Dashboard load: %sms (threshold: %sms)",
                $pass ? '✓' : '✗',
                $duration,
                $threshold
            ));
            $io->text(sprintf(
                "    Employees: %s, Dossiers: %s, Documents: %s, Contrats: %s",
                $employeeCount,
                $dossierCount,
                $documentCount,
                $contratCount
            ));

            return [
                'pass' => $pass,
                'duration' => $duration,
                'details' => "E:{$employeeCount} D:{$dossierCount} Doc:{$documentCount} C:{$contratCount}",
            ];

        } catch (\Exception $e) {
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            $io->error('  ✗ Dashboard load test failed: ' . $e->getMessage());
            
            return [
                'pass' => false,
                'duration' => $duration,
                'details' => 'ERROR: ' . substr($e->getMessage(), 0, 50),
            ];
        }
    }
}


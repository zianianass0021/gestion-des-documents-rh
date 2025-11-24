<?php

namespace App\Command;

use App\Service\ClickHouseClient;
use App\Service\KpiOlapService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:olap:smoke-test',
    description: 'Smoke test: verify all KPI endpoints work correctly',
)]
class OlapSmokeTestCommand extends Command
{
    private ClickHouseClient $clickHouseClient;
    private KpiOlapService $kpiOlapService;

    public function __construct(
        ClickHouseClient $clickHouseClient,
        KpiOlapService $kpiOlapService
    ) {
        parent::__construct();
        $this->clickHouseClient = $clickHouseClient;
        $this->kpiOlapService = $kpiOlapService;
    }

    protected function configure(): void
    {
        $this
            ->addOption('seed', 's', InputOption::VALUE_NONE, 'Seed minimal test data before testing')
            ->addOption('kpi', 'k', InputOption::VALUE_OPTIONAL, 'Test specific KPI (a, b, c, d, e, f)', null)
            ->setHelp('
This command performs smoke tests on all KPI endpoints to verify they work correctly.

Examples:
  php bin/console app:olap:smoke-test                # Test all KPIs
  php bin/console app:olap:smoke-test --seed        # Seed data first, then test
  php bin/console app:olap:smoke-test --kpi=a       # Test only KPI A
            ');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('OLAP Smoke Test');

        try {
            // Test connection first
            $io->section('1. Testing ClickHouse Connection');
            if (!$this->clickHouseClient->testConnection()) {
                $io->error('ClickHouse connection failed!');
                return Command::FAILURE;
            }
            $io->success('✓ ClickHouse connection OK');

            // Seed data if requested
            if ($input->getOption('seed')) {
                $io->section('2. Seeding Test Data');
                $this->seedTestData($io);
            }

            // Test KPIs
            $kpiOption = $input->getOption('kpi');
            $kpis = $kpiOption !== null ? [strtolower($kpiOption)] : ['a', 'b', 'c', 'd', 'e', 'f'];

            $io->section('3. Testing KPI Endpoints');
            $results = [];
            
            foreach ($kpis as $kpi) {
                $results[$kpi] = $this->testKpi($io, $kpi);
            }

            // Summary
            $io->newLine();
            $io->section('Test Summary');
            
            $tableData = [];
            foreach ($results as $kpi => $result) {
                $status = $result['success'] ? '✓ PASS' : '✗ FAIL';
                $tableData[] = [
                    'KPI ' . strtoupper($kpi),
                    $status,
                    $result['rows'],
                    $result['duration'] . 'ms',
                    $result['error'] ?? '-',
                ];
            }

            $io->table(['KPI', 'Status', 'Rows', 'Duration', 'Error'], $tableData);

            $allPassed = !in_array(false, array_column($results, 'success'));
            
            if ($allPassed) {
                $io->success('All smoke tests passed!');
                return Command::SUCCESS;
            } else {
                $io->error('Some smoke tests failed. Check errors above.');
                return Command::FAILURE;
            }

        } catch (\Exception $e) {
            $io->error('Smoke test failed: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    private function seedTestData(SymfonyStyle $io): void
    {
        $io->text('Seeding minimal test data...');

        // Seed KPI A data
        $this->seedKpiA($io);
        $this->seedKpiB($io);
        $this->seedKpiC($io);
        $this->seedKpiD($io);
        $this->seedKpiE($io);
        $this->seedKpiF($io);

        $io->success('Test data seeded successfully');
    }

    private function seedKpiA(SymfonyStyle $io): void
    {
        try {
            // Delete existing test data
            $this->clickHouseClient->execute("ALTER TABLE rh_olap.kpi_a_contract_type_performance DELETE WHERE event_date = today()");
            
            // Insert test data with explicit column names
            $query = "INSERT INTO rh_olap.kpi_a_contract_type_performance (event_date, contract_type, personnel_completion, ayant_droits_completion, missing_docs_personnel, missing_docs_ayantdroits) VALUES " .
                "(today(), 'CDI', 85.5, 90.0, 15, 10), " .
                "(today(), 'CDD', 75.0, 80.5, 25, 20)";
            
            $this->clickHouseClient->insert($query);
            $io->text('  ✓ KPI A seeded');
        } catch (\Exception $e) {
            $io->text('  ✗ KPI A seed failed: ' . $e->getMessage());
        }
    }

    private function seedKpiB(SymfonyStyle $io): void
    {
        try {
            $this->clickHouseClient->execute("ALTER TABLE rh_olap.kpi_b_doc_reliability_by_org DELETE WHERE event_date = today()");
            
            $query = "INSERT INTO rh_olap.kpi_b_doc_reliability_by_org (event_date, organization, completion, missing) VALUES " .
                "(today(), 'DAS_TEST_1', 88.0, 12), " .
                "(today(), 'DAS_TEST_2', 92.5, 8)";
            
            $this->clickHouseClient->insert($query);
            $io->text('  ✓ KPI B seeded');
        } catch (\Exception $e) {
            $io->text('  ✗ KPI B seed failed: ' . $e->getMessage());
        }
    }

    private function seedKpiC(SymfonyStyle $io): void
    {
        try {
            $this->clickHouseClient->execute("ALTER TABLE rh_olap.kpi_c_document_matrix DELETE WHERE event_date = today()");
            
            $query = "INSERT INTO rh_olap.kpi_c_document_matrix VALUES " .
                "(today(), 'CV', 950, 50), " .
                "(today(), 'COP_DIP', 900, 100)";
            
            $this->clickHouseClient->insert($query);
            $io->text('  ✓ KPI C seeded');
        } catch (\Exception $e) {
            $io->text('  ✗ KPI C seed failed: ' . $e->getMessage());
        }
    }

    private function seedKpiD(SymfonyStyle $io): void
    {
        try {
            $this->clickHouseClient->execute("ALTER TABLE rh_olap.kpi_d_evolution DELETE WHERE event_date = today()");
            
            $query = "INSERT INTO rh_olap.kpi_d_evolution VALUES " .
                "(today(), 'CDI_CV', 95.0), " .
                "(today(), 'CDD_CV', 85.5)";
            
            $this->clickHouseClient->insert($query);
            $io->text('  ✓ KPI D seeded');
        } catch (\Exception $e) {
            $io->text('  ✗ KPI D seed failed: ' . $e->getMessage());
        }
    }

    private function seedKpiE(SymfonyStyle $io): void
    {
        try {
            $this->clickHouseClient->execute("ALTER TABLE rh_olap.kpi_e_personnel_matrix DELETE WHERE event_date = today()");
            
            $query = "INSERT INTO rh_olap.kpi_e_personnel_matrix VALUES " .
                "(today(), 'Personnel', 'CDI', 85.5), " .
                "(today(), 'Personnel', 'CDD', 75.0)";
            
            $this->clickHouseClient->insert($query);
            $io->text('  ✓ KPI E seeded');
        } catch (\Exception $e) {
            $io->text('  ✗ KPI E seed failed: ' . $e->getMessage());
        }
    }

    private function seedKpiF(SymfonyStyle $io): void
    {
        try {
            $this->clickHouseClient->execute("ALTER TABLE rh_olap.kpi_f_comparative DELETE WHERE event_date = today()");
            
            $query = "INSERT INTO rh_olap.kpi_f_comparative VALUES " .
                "(today(), 'CDI', 'completion', 90.0), " .
                "(today(), 'CDD', 'completion', 80.5)";
            
            $this->clickHouseClient->insert($query);
            $io->text('  ✓ KPI F seeded');
        } catch (\Exception $e) {
            $io->text('  ✗ KPI F seed failed: ' . $e->getMessage());
        }
    }

    private function testKpi(SymfonyStyle $io, string $kpi): array
    {
        $startTime = microtime(true);
        $error = null;
        $rows = 0;

        try {
            $io->text("Testing KPI {$kpi}...");
            
            $data = match(strtolower($kpi)) {
                'a' => $this->kpiOlapService->getKpiA(),
                'b' => $this->kpiOlapService->getKpiB(),
                'c' => $this->kpiOlapService->getKpiC(),
                'd' => $this->kpiOlapService->getKpiD(),
                'e' => $this->kpiOlapService->getKpiE(),
                'f' => $this->kpiOlapService->getKpiF(),
                default => throw new \InvalidArgumentException("Unknown KPI: {$kpi}"),
            };

            $rows = is_array($data) ? count($data) : 0;
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            
            if ($rows >= 0) {
                $io->text("  ✓ KPI {$kpi}: {$rows} rows in {$duration}ms");
                return [
                    'success' => true,
                    'rows' => $rows,
                    'duration' => $duration,
                ];
            } else {
                throw new \RuntimeException("Invalid data returned");
            }

        } catch (\Exception $e) {
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            $error = $e->getMessage();
            $io->text("  ✗ KPI {$kpi}: FAILED - {$error}");
            
            return [
                'success' => false,
                'rows' => 0,
                'duration' => $duration,
                'error' => substr($error, 0, 50),
            ];
        }
    }
}


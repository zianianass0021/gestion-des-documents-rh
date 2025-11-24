<?php

namespace App\Command;

use App\Service\OlapEtlService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:olap:sync',
    description: 'ETL: Sync PostgreSQL data to ClickHouse OLAP cube',
)]
class OlapSyncCommand extends Command
{
    private OlapEtlService $etlService;

    public function __construct(OlapEtlService $etlService)
    {
        parent::__construct();
        $this->etlService = $etlService;
    }

    protected function configure(): void
    {
        $this
            ->addOption('kpi', 'k', InputOption::VALUE_OPTIONAL, 'Sync specific KPI (a, b, c, d, e, f)', null)
            ->setHelp('
This command syncs data from PostgreSQL to ClickHouse OLAP cube.

Examples:
  php bin/console app:olap:sync              # Sync all KPIs
  php bin/console app:olap:sync --kpi=a     # Sync only KPI A
  php bin/console app:olap:sync -k b        # Sync only KPI B
            ');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // Increase memory limit for ETL operations
        ini_set('memory_limit', '4096M');
        set_time_limit(600); // 10 minutes
        $io = new SymfonyStyle($input, $output);
        $io->title('OLAP ETL Sync: PostgreSQL → ClickHouse');

        $kpiOption = $input->getOption('kpi');
        $startTime = microtime(true);

        try {
            if ($kpiOption !== null) {
                // Sync specific KPI
                $kpi = strtolower($kpiOption);
                $io->section("Syncing KPI " . strtoupper($kpi));
                
                $result = match($kpi) {
                    'a' => $this->etlService->syncKpiA(),
                    'b' => $this->etlService->syncKpiB(),
                    'c' => $this->etlService->syncKpiC(),
                    'd' => $this->etlService->syncKpiD(),
                    'e' => $this->etlService->syncKpiE(),
                    'f' => $this->etlService->syncKpiF(),
                    default => throw new \InvalidArgumentException("Invalid KPI: {$kpi}. Must be a, b, c, d, e, or f.")
                };
                
                if ($result['status'] === 'success') {
                    $io->success("KPI {$kpi} synced successfully! Rows: {$result['rows']}");
                } else {
                    $io->error("KPI {$kpi} sync failed!");
                    return Command::FAILURE;
                }
            } else {
                // Sync all KPIs
                $io->section('Syncing all KPIs...');
                
                $results = $this->etlService->syncAll();
                
                $io->newLine();
                $io->table(
                    ['KPI', 'Status', 'Rows'],
                    [
                        ['A', $results['kpi_a']['status'], $results['kpi_a']['rows']],
                        ['B', $results['kpi_b']['status'], $results['kpi_b']['rows']],
                        ['C', $results['kpi_c']['status'], $results['kpi_c']['rows']],
                        ['D', $results['kpi_d']['status'], $results['kpi_d']['rows']],
                        ['E', $results['kpi_e']['status'], $results['kpi_e']['rows']],
                        ['F', $results['kpi_f']['status'], $results['kpi_f']['rows']],
                    ]
                );
                
                $totalRows = array_sum(array_column($results, 'rows'));
                $allSuccess = !in_array('error', array_column($results, 'status'));
                
                if ($allSuccess) {
                    $io->success("All KPIs synced successfully! Total rows: {$totalRows}");
                } else {
                    $io->warning("Some KPIs failed to sync. Check logs for details.");
                    return Command::FAILURE;
                }
            }
            
            $duration = round(microtime(true) - $startTime, 2);
            $io->note("Sync completed in {$duration} seconds");
            
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error('ETL sync failed: ' . $e->getMessage());
            $io->error($e->getTraceAsString());
            return Command::FAILURE;
        }
    }
}


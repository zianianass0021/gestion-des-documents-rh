<?php

namespace App\Command;

use App\Service\ClickHouseClient;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:olap:monitor',
    description: 'Monitor ClickHouse OLAP cube health and statistics',
)]
class OlapMonitorCommand extends Command
{
    private ClickHouseClient $clickHouseClient;

    public function __construct(ClickHouseClient $clickHouseClient)
    {
        parent::__construct();
        $this->clickHouseClient = $clickHouseClient;
    }

    protected function configure(): void
    {
        $this
            ->addOption('health', null, InputOption::VALUE_NONE, 'Check ClickHouse health status')
            ->addOption('stats', null, InputOption::VALUE_NONE, 'Show KPI table statistics')
            ->addOption('tables', null, InputOption::VALUE_NONE, 'List all OLAP tables')
            ->addOption('detailed', 'd', InputOption::VALUE_NONE, 'Show detailed information')
            ->setHelp('
This command monitors ClickHouse OLAP cube health and statistics.

Examples:
  php bin/console app:olap:monitor --health
  php bin/console app:olap:monitor --stats
  php bin/console app:olap:monitor --tables --detailed
            ');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('ClickHouse OLAP Monitoring');

        try {
            $health = $input->getOption('health');
            $stats = $input->getOption('stats');
            $tables = $input->getOption('tables');
            $detailed = $input->getOption('detailed');

            // If no option specified, show all
            if (!$health && !$stats && !$tables) {
                $health = $stats = $tables = true;
            }

            if ($health) {
                $this->showHealth($io);
            }

            if ($tables) {
                $this->showTables($io, $detailed);
            }

            if ($stats) {
                $this->showStats($io, $detailed);
            }

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error('Monitoring failed: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    private function showHealth(SymfonyStyle $io): void
    {
        $io->section('Health Status');

        try {
            // Test connection
            $result = $this->clickHouseClient->select('SELECT 1 as test');
            if (!empty($result)) {
                $io->success('✓ ClickHouse connection: OK');
            } else {
                $io->error('✗ ClickHouse connection: FAILED');
                return;
            }

            // Check version
            $version = $this->clickHouseClient->select('SELECT version() as version');
            if (!empty($version) && isset($version[0])) {
                $ver = is_array($version[0]) ? ($version[0]['version'] ?? $version[0]) : $version[0];
                $io->text('  Version: ' . $ver);
            }

            // Check uptime
            $uptime = $this->clickHouseClient->select('SELECT uptime() as uptime_seconds');
            if (!empty($uptime) && isset($uptime[0])) {
                $up = is_array($uptime[0]) ? ($uptime[0]['uptime_seconds'] ?? $uptime[0]) : $uptime[0];
                $days = floor($up / 86400);
                $hours = floor(($up % 86400) / 3600);
                $io->text('  Uptime: ' . $days . ' days, ' . $hours . ' hours');
            }

            // Check database exists
            $dbInfo = $this->clickHouseClient->getDatabaseInfo();
            $olapExists = !empty($dbInfo);

            if ($olapExists) {
                $io->success('✓ OLAP database (rh_olap): EXISTS');
            } else {
                $io->error('✗ OLAP database (rh_olap): NOT FOUND');
            }

        } catch (\Exception $e) {
            $io->error('✗ Health check failed: ' . $e->getMessage());
        }
    }

    private function showTables(SymfonyStyle $io, bool $detailed): void
    {
        $io->section('OLAP Tables');

        try {
            $tables = $this->clickHouseClient->getTables();
            
            if (empty($tables)) {
                $io->warning('No tables found in OLAP database');
                return;
            }

            $rows = [];
            foreach ($tables as $table) {
                $name = is_array($table) ? ($table['name'] ?? 'N/A') : $table;
                $rows[] = [
                    $name,
                    is_array($table) ? ($table['total_rows'] ?? 'N/A') : 'N/A',
                    $this->formatBytes((int)($table['total_bytes'] ?? 0)),
                ];
            }

            $io->table(['Table Name', 'Total Rows', 'Size'], $rows);

            if ($detailed) {
                $io->newLine();
                foreach ($tables as $table) {
                    $name = is_array($table) ? ($table['name'] ?? 'N/A') : $table;
                    $io->section("Details: {$name}");
                    
                    try {
                        $query = "SELECT * FROM system.columns WHERE database = 'rh_olap' AND table = '{$name}' ORDER BY position";
                        $columns = $this->clickHouseClient->select($query);
                        
                        if (!empty($columns)) {
                            $columnRows = [];
                            foreach ($columns as $col) {
                                $columnRows[] = [
                                    is_array($col) ? ($col['name'] ?? 'N/A') : 'N/A',
                                    is_array($col) ? ($col['type'] ?? 'N/A') : 'N/A',
                                ];
                            }
                            $io->table(['Column', 'Type'], $columnRows);
                        }
                    } catch (\Exception $e) {
                        $io->text('  Could not fetch column details: ' . $e->getMessage());
                    }
                }
            }

        } catch (\Exception $e) {
            $io->error('Failed to list tables: ' . $e->getMessage());
        }
    }

    private function showStats(SymfonyStyle $io, bool $detailed): void
    {
        $io->section('KPI Statistics');

        $kpiTables = [
            'kpi_a_contract_type_performance' => 'KPI A: Performance by Contract Type',
            'kpi_b_doc_reliability_by_org' => 'KPI B: Reliability by Organization',
            'kpi_c_document_matrix' => 'KPI C: Document Matrix',
            'kpi_d_evolution' => 'KPI D: Evolution Metrics',
            'kpi_e_personnel_matrix' => 'KPI E: Personnel Matrix',
            'kpi_f_comparative' => 'KPI F: Comparative Metrics',
        ];

        $rows = [];
        foreach ($kpiTables as $table => $description) {
            try {
                // Get row count for today
                $todayQuery = "SELECT count() as count FROM rh_olap.{$table} WHERE event_date = today()";
                $todayResult = $this->clickHouseClient->select($todayQuery);
                $todayCount = is_array($todayResult[0] ?? []) ? ($todayResult[0]['count'] ?? 0) : ($todayResult[0] ?? 0);

                // Get total row count
                $totalQuery = "SELECT count() as count FROM rh_olap.{$table}";
                $totalResult = $this->clickHouseClient->select($totalQuery);
                $totalCount = is_array($totalResult[0] ?? []) ? ($totalResult[0]['count'] ?? 0) : ($totalResult[0] ?? 0);

                // Get latest event date
                $latestQuery = "SELECT max(event_date) as latest FROM rh_olap.{$table}";
                $latestResult = $this->clickHouseClient->select($latestQuery);
                $latestDate = is_array($latestResult[0] ?? []) ? ($latestResult[0]['latest'] ?? 'N/A') : ($latestResult[0] ?? 'N/A');

                $rows[] = [
                    $description,
                    number_format((int)$todayCount),
                    number_format((int)$totalCount),
                    $latestDate !== 'N/A' ? date('Y-m-d', strtotime($latestDate)) : 'N/A',
                ];

            } catch (\Exception $e) {
                $rows[] = [
                    $description,
                    'ERROR',
                    'ERROR',
                    $e->getMessage(),
                ];
            }
        }

        $io->table(['KPI', 'Today\'s Rows', 'Total Rows', 'Latest Date'], $rows);

        if ($detailed) {
            $io->newLine();
            $io->note('For detailed query analysis, check system.query_log table in ClickHouse');
        }
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes == 0) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $exp = floor(log($bytes) / log(1024));
        $exp = min($exp, count($units) - 1);
        
        return round($bytes / pow(1024, $exp), 2) . ' ' . $units[$exp];
    }
}


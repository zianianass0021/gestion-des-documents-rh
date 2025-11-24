<?php

namespace App\Command;

use App\Service\ClickHouseClient;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

#[AsCommand(
    name: 'app:olap:backup',
    description: 'Backup ClickHouse OLAP data (exports to SQL/CSV format)',
)]
class OlapBackupCommand extends Command
{
    private ClickHouseClient $clickHouseClient;
    private string $projectDir;

    public function __construct(ClickHouseClient $clickHouseClient, ParameterBagInterface $parameterBag)
    {
        parent::__construct();
        $this->clickHouseClient = $clickHouseClient;
        $this->projectDir = $parameterBag->get('kernel.project_dir');
    }

    protected function configure(): void
    {
        $this
            ->addOption('format', 'f', InputOption::VALUE_OPTIONAL, 'Backup format (sql, csv, json)', 'sql')
            ->addOption('output', 'o', InputOption::VALUE_OPTIONAL, 'Output directory', null)
            ->addOption('table', 't', InputOption::VALUE_OPTIONAL, 'Backup specific table only', null)
            ->addOption('date', 'd', InputOption::VALUE_OPTIONAL, 'Backup data for specific date (YYYY-MM-DD), default: today', null)
            ->setHelp('
This command backs up ClickHouse OLAP data.

Examples:
  php bin/console app:olap:backup                           # Backup all tables (SQL format)
  php bin/console app:olap:backup --format=csv            # Backup as CSV
  php bin/console app:olap:backup --table=kpi_a_contract_type_performance
  php bin/console app:olap:backup --date=2024-01-15      # Backup data for specific date
            ');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('ClickHouse OLAP Backup');

        try {
            $format = strtolower($input->getOption('format') ?? 'sql');
            $tableName = $input->getOption('table');
            $date = $input->getOption('date') ?? date('Y-m-d');
            $outputDir = $input->getOption('output') ?? $this->projectDir . '/var/backups/clickhouse';

            // Validate format
            if (!in_array($format, ['sql', 'csv', 'json'])) {
                $io->error('Invalid format. Must be: sql, csv, or json');
                return Command::FAILURE;
            }

            // Create output directory
            if (!is_dir($outputDir)) {
                mkdir($outputDir, 0755, true);
            }

            $io->section('Backup Configuration');
            $io->table(
                ['Setting', 'Value'],
                [
                    ['Format', $format],
                    ['Output Directory', $outputDir],
                    ['Date', $date],
                    ['Table', $tableName ?? 'All tables'],
                ]
            );

            // Get tables to backup
            $tables = [];
            if ($tableName) {
                $tables = [$tableName];
            } else {
                $allTables = $this->clickHouseClient->getTables();
                foreach ($allTables as $table) {
                    $name = is_array($table) ? ($table['name'] ?? null) : $table;
                    if ($name && strpos($name, 'kpi_') === 0) {
                        $tables[] = $name;
                    }
                }
            }

            if (empty($tables)) {
                $io->warning('No tables to backup');
                return Command::SUCCESS;
            }

            $io->section('Backing up ' . count($tables) . ' table(s)...');

            $backupFiles = [];
            foreach ($tables as $table) {
                $io->text("Backing up: {$table}...");
                
                try {
                    $backupFile = $this->backupTable($table, $date, $format, $outputDir);
                    $backupFiles[] = $backupFile;
                    $io->text("  ✓ Saved to: {$backupFile}");
                } catch (\Exception $e) {
                    $io->error("  ✗ Failed: " . $e->getMessage());
                }
            }

            $io->newLine();
            if (!empty($backupFiles)) {
                $io->success('Backup completed! Files saved:');
                foreach ($backupFiles as $file) {
                    $io->text('  - ' . basename($file));
                }
            } else {
                $io->warning('No backup files were created');
            }

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error('Backup failed: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    private function backupTable(string $tableName, string $date, string $format, string $outputDir): string
    {
        $timestamp = date('Ymd_His');
        $filename = "{$tableName}_{$date}_{$timestamp}.{$format}";
        $filepath = $outputDir . '/' . $filename;

        // Query data for the specified date
        $query = "SELECT * FROM rh_olap.{$tableName} WHERE event_date = '{$date}' FORMAT {$this->getFormatForClickHouse($format)}";
        
        try {
            $response = $this->clickHouseClient->execute($query);
            $content = $response->getContent();

            file_put_contents($filepath, $content);

            return $filepath;
        } catch (\Exception $e) {
            throw new \RuntimeException("Failed to backup {$tableName}: " . $e->getMessage());
        }
    }

    private function getFormatForClickHouse(string $format): string
    {
        return match($format) {
            'sql' => 'SQLInsert',
            'csv' => 'CSV',
            'json' => 'JSONEachRow',
            default => 'SQLInsert',
        };
    }
}


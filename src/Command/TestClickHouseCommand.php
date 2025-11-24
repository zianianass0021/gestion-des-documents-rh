<?php

namespace App\Command;

use App\Service\ClickHouseClient;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:test-clickhouse',
    description: 'Test ClickHouse connection and basic operations',
)]
class TestClickHouseCommand extends Command
{
    public function __construct(
        private ClickHouseClient $clickHouseClient
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('ClickHouse Connection Test');

        // Test connection
        $io->section('Testing Connection');
        if ($this->clickHouseClient->testConnection()) {
            $io->success('ClickHouse connection successful!');
        } else {
            $io->error('ClickHouse connection failed!');
            return Command::FAILURE;
        }

        // Show connection info
        $io->section('Connection Information');
        $connectionInfo = $this->clickHouseClient->getConnectionInfo();
        $io->table(['Parameter', 'Value'], [
            ['Host', $connectionInfo['host']],
            ['Port', $connectionInfo['port']],
            ['Database', $connectionInfo['database']],
            ['Username', $connectionInfo['username']],
            ['HTTP URL', $connectionInfo['http_url']],
        ]);

        // Test database info
        $io->section('Database Information');
        $dbInfo = $this->clickHouseClient->getDatabaseInfo();
        if (!empty($dbInfo)) {
            $io->table(['Name', 'Engine'], array_map(fn($db) => [$db['name'], $db['engine']], $dbInfo));
        } else {
            $io->warning('No database information found');
        }

        // Test tables
        $io->section('Tables in Database');
        $tables = $this->clickHouseClient->getTables();
        if (!empty($tables)) {
            $io->table(['Name', 'Engine', 'Total Rows', 'Total Bytes'], 
                array_map(fn($table) => [
                    $table['name'], 
                    $table['engine'], 
                    $table['total_rows'] ?? 'N/A',
                    $table['total_bytes'] ?? 'N/A'
                ], $tables)
            );
        } else {
            $io->warning('No tables found in database');
        }

        // Test a simple query
        $io->section('Testing Simple Query');
        try {
            $result = $this->clickHouseClient->select('SELECT now() as current_time, version() as version');
            if (!empty($result)) {
                $io->success('Query executed successfully!');
                
                // Handle array format (ClickHouse returns array of arrays)
                if (is_array($result[0]) && count($result[0]) >= 2) {
                    $io->table(['Current Time', 'Version'], [
                        [$result[0][0], $result[0][1]]
                    ]);
                } else {
                    $io->writeln('Unexpected result format: ' . print_r($result, true));
                }
            }
        } catch (\Exception $e) {
            $io->error('Query failed: ' . $e->getMessage());
            return Command::FAILURE;
        }

        $io->success('All ClickHouse tests passed!');
        return Command::SUCCESS;
    }
}

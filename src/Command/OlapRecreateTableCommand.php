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
    name: 'app:olap:recreate-table',
    description: 'Recreate ClickHouse OLAP table with correct schema',
)]
class OlapRecreateTableCommand extends Command
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
            ->addOption('table', 't', InputOption::VALUE_OPTIONAL, 'Specific table to recreate (kpi_a, kpi_b, etc.)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $tableName = $input->getOption('table');

        $io->title('Recreate ClickHouse OLAP Tables');

        $tables = [];
        if ($tableName) {
            $tables[] = $tableName;
        } else {
            $tables = ['kpi_a', 'kpi_b', 'kpi_c', 'kpi_d', 'kpi_e', 'kpi_f'];
        }

        foreach ($tables as $table) {
            try {
                $fullTableName = $this->getTableName($table);
                $io->section("Recreating table: {$fullTableName}");

                // Drop table if exists
                $this->clickHouseClient->execute("DROP TABLE IF EXISTS {$fullTableName}");

                // Create table with correct schema
                $schema = $this->getTableSchema($table);
                $this->clickHouseClient->execute($schema);

                $io->success("✓ {$fullTableName} recreated successfully");
            } catch (\Exception $e) {
                $io->error("✗ Failed to recreate {$table}: " . $e->getMessage());
                return Command::FAILURE;
            }
        }

        $io->success('All tables recreated successfully!');
        return Command::SUCCESS;
    }

    private function getTableName(string $table): string
    {
        $map = [
            'kpi_a' => 'rh_olap.kpi_a_contract_type_performance',
            'kpi_b' => 'rh_olap.kpi_b_doc_reliability_by_org',
            'kpi_c' => 'rh_olap.kpi_c_document_matrix',
            'kpi_d' => 'rh_olap.kpi_d_evolution',
            'kpi_e' => 'rh_olap.kpi_e_personnel_matrix',
            'kpi_f' => 'rh_olap.kpi_f_comparative',
        ];

        return $map[$table] ?? throw new \InvalidArgumentException("Unknown table: {$table}");
    }

    private function getTableSchema(string $table): string
    {
        $schemas = [
            'kpi_a' => "CREATE TABLE rh_olap.kpi_a_contract_type_performance (
  event_date Date,
  contract_type String,
  personnel_completion Float64,
  ayant_droits_completion Float64,
  missing_docs_personnel UInt32,
  missing_docs_ayantdroits UInt32
) ENGINE = MergeTree() ORDER BY (event_date, contract_type)",
            'kpi_b' => "CREATE TABLE rh_olap.kpi_b_doc_reliability_by_org (
  event_date Date,
  organization String,
  personnel_completion Float64,
  ayant_droits_completion Float64
) ENGINE = MergeTree() ORDER BY (event_date, organization)",
            'kpi_c' => "CREATE TABLE rh_olap.kpi_c_document_matrix (
  event_date Date,
  das_code String,
  document_abbreviation String,
  document_type String,
  completion_percentage Float64
) ENGINE = MergeTree() ORDER BY (event_date, das_code, document_abbreviation)",
            'kpi_d' => "CREATE TABLE rh_olap.kpi_d_evolution (
  event_date Date,
  contract_type String,
  document_abbreviation String,
  document_type String,
  completion_percentage Float64
) ENGINE = MergeTree() ORDER BY (event_date, contract_type, document_abbreviation)",
            'kpi_e' => "CREATE TABLE rh_olap.kpi_e_personnel_matrix (
  event_date Date,
  contract_type String,
  das_code String,
  completion_percentage Float64
) ENGINE = MergeTree() ORDER BY (event_date, contract_type, das_code)",
            'kpi_f' => "CREATE TABLE rh_olap.kpi_f_comparative (
  event_date Date,
  contract_type String,
  das_code String,
  completion_percentage Float64
) ENGINE = MergeTree() ORDER BY (event_date, contract_type, das_code)",
        ];

        return $schemas[$table] ?? throw new \InvalidArgumentException("Unknown table: {$table}");
    }
}


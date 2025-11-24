<?php

namespace App\Command;

use App\Service\ClickHouseClient;
use App\Service\KpiOlapService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:olap:debug',
    description: 'Debug OLAP data retrieval'
)]
class OlapDebugCommand extends Command
{
    public function __construct(
        private ClickHouseClient $clickHouseClient,
        private KpiOlapService $kpiOlapService
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('OLAP Data Debug');

        // Test KPI A
        $io->section('KPI A: Contract Type Performance');
        
        // Direct ClickHouse query
        $query = "
            SELECT *
            FROM rh_olap.kpi_a_contract_type_performance
            WHERE event_date = (SELECT max(event_date) FROM rh_olap.kpi_a_contract_type_performance)
            ORDER BY contract_type
            LIMIT 5
        ";
        
        try {
            $rawResults = $this->clickHouseClient->select($query);
            $io->writeln('Raw ClickHouse results: ' . count($rawResults) . ' rows');
            if (!empty($rawResults)) {
                $io->writeln('First row (raw):');
                $io->writeln(json_encode($rawResults[0], JSON_PRETTY_PRINT));
                $io->writeln('Keys: ' . implode(', ', array_keys($rawResults[0] ?? [])));
            }
            
            // Through service
            $serviceResults = $this->kpiOlapService->getKpiA();
            $io->writeln('Service results: ' . count($serviceResults) . ' rows');
            if (!empty($serviceResults)) {
                $io->writeln('First row (service):');
                $io->writeln(json_encode($serviceResults[0], JSON_PRETTY_PRINT));
            }
        } catch (\Exception $e) {
            $io->error('Error: ' . $e->getMessage());
        }

        // Test KPI B
        $io->section('KPI B: Organization Performance');
        
        $query = "
            SELECT *
            FROM rh_olap.kpi_b_doc_reliability_by_org
            WHERE event_date = (SELECT max(event_date) FROM rh_olap.kpi_b_doc_reliability_by_org)
            ORDER BY organization
            LIMIT 5
        ";
        
        try {
            $rawResults = $this->clickHouseClient->select($query);
            $io->writeln('Raw ClickHouse results: ' . count($rawResults) . ' rows');
            if (!empty($rawResults)) {
                $io->writeln('First row (raw):');
                $io->writeln(json_encode($rawResults[0], JSON_PRETTY_PRINT));
            }
            
            $serviceResults = $this->kpiOlapService->getKpiB();
            $io->writeln('Service results: ' . count($serviceResults) . ' rows');
            if (!empty($serviceResults)) {
                $io->writeln('First row (service):');
                $io->writeln(json_encode($serviceResults[0], JSON_PRETTY_PRINT));
            }
        } catch (\Exception $e) {
            $io->error('Error: ' . $e->getMessage());
        }

        // Test formatting
        $io->section('Testing Format Functions');
        
        $controller = new \App\Controller\DashboardController();
        $reflection = new \ReflectionClass($controller);
        
        // Test formatReportAData
        $method = $reflection->getMethod('formatReportAData');
        $method->setAccessible(true);
        $serviceDataA = $this->kpiOlapService->getKpiA();
        $io->writeln('KPI A service data: ' . count($serviceDataA) . ' rows');
        if (!empty($serviceDataA)) {
            $io->writeln('First row keys: ' . implode(', ', array_keys($serviceDataA[0])));
            $formattedA = $method->invoke($controller, $serviceDataA);
            $io->writeln('Formatted header length: ' . count($formattedA[0]));
            $io->writeln('Formatted header: ' . json_encode($formattedA[0]));
            $io->writeln('Formatted row 1 length: ' . count($formattedA[1]));
            $io->writeln('Formatted row 1: ' . json_encode(array_slice($formattedA[1], 0, 5)));
        }

        return Command::SUCCESS;
    }
}


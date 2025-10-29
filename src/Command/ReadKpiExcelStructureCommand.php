<?php

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Box\Spout\Reader\XLSX\Reader as XLSXReader;

#[AsCommand(
    name: 'kpi:read-excel-structure',
    description: 'Read Excel files to understand KPI structure',
)]
class ReadKpiExcelStructureCommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Reading KPI Excel File Structures');

        $files = [
            'B' => 'help/B.xlsx',
            'C' => 'help/C.xlsx',
            'D' => 'help/D.xlsx',
            'E' => 'help/E.xlsx',
            'F' => 'help/F.xlsx',
        ];

        foreach ($files as $kpi => $file) {
            if (!file_exists($file)) {
                $io->warning("File not found: $file");
                continue;
            }

            $io->section("KPI $kpi: " . $file);
            
            try {
                $reader = new XLSXReader();
                $reader->open($file);
                
                $rowCount = 0;
                $maxColumns = 0;
                $rows = [];
                
                foreach ($reader->getSheetIterator() as $sheet) {
                    foreach ($sheet->getRowIterator() as $row) {
                        $rowData = $row->toArray();
                        $rows[] = $rowData;
                        $maxColumns = max($maxColumns, count($rowData));
                        $rowCount++;
                        if ($rowCount >= 20) break;
                    }
                    break; // Only read first sheet
                }
                $reader->close();
                
                $io->text("Dimensions: ~{$maxColumns} columns x {$rowCount} rows (first 20 shown)");
                $io->newLine();
                
                // Display first 20 rows
                $io->text("First 20 rows:");
                foreach ($rows as $idx => $rowData) {
                    $displayData = array_map(function($val) {
                        return is_null($val) ? '' : substr((string)$val, 0, 30);
                    }, array_slice($rowData, 0, 10)); // Show first 10 columns
                    $io->text(sprintf("Row %2d: %s", $idx + 1, implode(' | ', array_filter($displayData))));
                }
                
                $io->newLine();
                
            } catch (\Exception $e) {
                $io->error("Error reading $file: " . $e->getMessage());
            }
            
            $io->newLine();
        }

        $io->success('Finished reading all KPI Excel files');
        return Command::SUCCESS;
    }
}

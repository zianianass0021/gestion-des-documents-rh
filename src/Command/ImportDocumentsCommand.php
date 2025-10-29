<?php

namespace App\Command;

use App\Entity\NatureContratTypeDocument;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:import-documents',
    description: 'Import document requirements from SQL file',
)]
class ImportDocumentsCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $em
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('file', InputArgument::OPTIONAL, 'Path to the SQL file', 'import_documents.sql')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $filePath = $input->getArgument('file');

        if (!file_exists($filePath)) {
            $io->error("File not found: $filePath");
            return Command::FAILURE;
        }

        try {
            $sqlContent = file_get_contents($filePath);
            
            // Split by INSERT statements
            preg_match_all('/VALUES\s+\(\'([^\']+)\',\s+\'([^\']+)\',\s+(true|false)\)/i', $sqlContent, $matches);
            
            $io->title('Importing Document Requirements');
            $io->info(sprintf('Found %d document requirements', count($matches[0])));
            
            $imported = 0;
            
            for ($i = 0; $i < count($matches[0]); $i++) {
                try {
                    $docAbb = $matches[1][$i];
                    $contractType = $matches[2][$i];
                    $required = $matches[3][$i] === 'true';
                    
                    $docReq = new NatureContratTypeDocument();
                    $docReq->setDocumentAbbreviation($docAbb);
                    $docReq->setContractType($contractType);
                    $docReq->setRequired($required);
                    
                    $this->em->persist($docReq);
                    $imported++;
                } catch (\Exception $e) {
                    // Skip duplicates
                }
            }
            
            $this->em->flush();
            
            $io->success(sprintf('Imported %d document requirements successfully!', $imported));
            
            return Command::SUCCESS;
            
        } catch (\Exception $e) {
            $io->error('Error: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}


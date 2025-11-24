<?php

namespace App\Command;

use App\Entity\Document;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:fix-document-types',
    description: 'Fix document types: replace "à définir" with "Personnel" or "Ayant droit"',
)]
class FixDocumentTypesCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        ini_set('memory_limit', '512M');
        set_time_limit(0);
        
        $io = new SymfonyStyle($input, $output);
        $io->title('Correction des types de documents');

        $updated = 0;
        $batchSize = 100;

        // Fix Documents
        $io->section('Correction des Documents...');
        $qb = $this->entityManager->getRepository(Document::class)
            ->createQueryBuilder('d')
            ->where("d.typeDocument IS NULL OR d.typeDocument = '' OR LOWER(d.typeDocument) = 'à définir' OR LOWER(d.typeDocument) = 'a definir'");
        
        $count = (int) $this->entityManager->getRepository(Document::class)
            ->createQueryBuilder('d')
            ->select('COUNT(d.id)')
            ->where("d.typeDocument IS NULL OR d.typeDocument = '' OR LOWER(d.typeDocument) = 'à définir' OR LOWER(d.typeDocument) = 'a definir'")
            ->getQuery()
            ->getSingleScalarResult();
        $io->text(sprintf('Trouvé %d documents avec typeDocument invalide...', $count));
        
        $offset = 0;
        $docCount = 0;
        while ($offset < $count) {
            $documents = $qb->setFirstResult($offset)
                ->setMaxResults($batchSize)
                ->getQuery()
                ->getResult();
            
            foreach ($documents as $document) {
                // Determine based on usage if available, otherwise default to "Personnel"
                $usage = $document->getUsage() ?? '';
                if (strtolower($usage) === 'ayant droit' || strtolower($usage) === 'ayant_droit') {
                    $document->setTypeDocument('Ayant droit');
                } else {
                    $document->setTypeDocument('Personnel');
                }
                $docCount++;
            }
            
            $this->entityManager->flush();
            $this->entityManager->clear();
            $offset += $batchSize;
            
            if ($docCount % 1000 == 0) {
                $io->text(sprintf('  ... %d documents traités', $docCount));
            }
        }
        $io->text(sprintf('✓ %d documents mis à jour', $docCount));
        $updated += $docCount;

        $io->success(sprintf('Types de documents corrigés pour %d documents !', $updated));

        return Command::SUCCESS;
    }
}


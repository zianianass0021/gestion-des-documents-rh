<?php

namespace App\Command;

use App\Entity\Employe;
use App\Entity\EmployeeContrat;
use App\Entity\Document;
use App\Entity\Dossier;
use App\Entity\Organisation;
use App\Repository\EmployeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:fix-traceability-updated',
    description: 'Set updatedAt and updatedBy for 40% of entities randomly',
)]
class FixTraceabilityUpdatedFieldsCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private EmployeRepository $employeRepository
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        ini_set('memory_limit', '512M');
        set_time_limit(0);
        
        $io = new SymfonyStyle($input, $output);
        $io->title('Ajout de valeurs updatedAt et updatedBy pour 40% des entités');

        // Get all Responsable RH users
        $responsablesRh = $this->employeRepository->findByRole('ROLE_RESPONSABLE_RH');
        
        if (empty($responsablesRh)) {
            $io->error('Aucun Responsable RH trouvé. Impossible de continuer.');
            return Command::FAILURE;
        }
        
        $io->info(sprintf('Trouvé %d Responsable(s) RH', count($responsablesRh)));
        $responsableIds = array_map(fn($r) => $r->getId(), $responsablesRh);
        
        $updated = 0;
        $batchSize = 100;
        $percentage = 0.4; // 40%

        // Fix Employes
        $io->section('Mise à jour des Employés...');
        $qb = $this->entityManager->getRepository(Employe::class)
            ->createQueryBuilder('e')
            ->where('e.createdAt IS NOT NULL')
            ->andWhere('e.updatedAt IS NULL')
            ->orderBy('e.id', 'ASC');
        
        $count = (int) $this->entityManager->getRepository(Employe::class)
            ->createQueryBuilder('e')
            ->select('COUNT(e.id)')
            ->where('e.createdAt IS NOT NULL')
            ->andWhere('e.updatedAt IS NULL')
            ->getQuery()
            ->getSingleScalarResult();
        $io->text(sprintf('Trouvé %d employés à vérifier...', $count));
        
        $targetCount = (int)($count * $percentage);
        $selectedIds = $this->getRandomIds($count, $targetCount);
        
        $offset = 0;
        $employeCount = 0;
        $currentIndex = 0;
        while ($offset < $count) {
            $employes = $qb->setFirstResult($offset)
                ->setMaxResults($batchSize)
                ->getQuery()
                ->getResult();
            
            foreach ($employes as $employe) {
                if (in_array($currentIndex, $selectedIds)) {
                    $this->setUpdatedFields($employe, $responsableIds);
                    $employeCount++;
                }
                $currentIndex++;
            }
            
            $this->entityManager->flush();
            $this->entityManager->clear();
            $offset += $batchSize;
        }
        $io->text(sprintf('✓ %d employés mis à jour', $employeCount));
        $updated += $employeCount;

        // Fix EmployeeContrats
        $io->section('Mise à jour des Contrats...');
        $qb = $this->entityManager->getRepository(EmployeeContrat::class)
            ->createQueryBuilder('ec')
            ->where('ec.createdAt IS NOT NULL')
            ->andWhere('ec.updatedAt IS NULL')
            ->orderBy('ec.id', 'ASC');
        
        $count = (int) $this->entityManager->getRepository(EmployeeContrat::class)
            ->createQueryBuilder('ec')
            ->select('COUNT(ec.id)')
            ->where('ec.createdAt IS NOT NULL')
            ->andWhere('ec.updatedAt IS NULL')
            ->getQuery()
            ->getSingleScalarResult();
        $io->text(sprintf('Trouvé %d contrats à vérifier...', $count));
        
        $targetCount = (int)($count * $percentage);
        $selectedIds = $this->getRandomIds($count, $targetCount);
        
        $offset = 0;
        $contratCount = 0;
        $currentIndex = 0;
        while ($offset < $count) {
            $contrats = $qb->setFirstResult($offset)
                ->setMaxResults($batchSize)
                ->getQuery()
                ->getResult();
            
            foreach ($contrats as $contrat) {
                if (in_array($currentIndex, $selectedIds)) {
                    $this->setUpdatedFields($contrat, $responsableIds);
                    $contratCount++;
                }
                $currentIndex++;
            }
            
            $this->entityManager->flush();
            $this->entityManager->clear();
            $offset += $batchSize;
        }
        $io->text(sprintf('✓ %d contrats mis à jour', $contratCount));
        $updated += $contratCount;

        // Fix Documents
        $io->section('Mise à jour des Documents...');
        $qb = $this->entityManager->getRepository(Document::class)
            ->createQueryBuilder('d')
            ->where('d.createdAt IS NOT NULL')
            ->andWhere('d.updatedAt IS NULL')
            ->orderBy('d.id', 'ASC');
        
        $count = (int) $this->entityManager->getRepository(Document::class)
            ->createQueryBuilder('d')
            ->select('COUNT(d.id)')
            ->where('d.createdAt IS NOT NULL')
            ->andWhere('d.updatedAt IS NULL')
            ->getQuery()
            ->getSingleScalarResult();
        $io->text(sprintf('Trouvé %d documents à vérifier...', $count));
        
        $targetCount = (int)($count * $percentage);
        $selectedIds = $this->getRandomIds($count, $targetCount);
        
        $offset = 0;
        $docCount = 0;
        $docBatchSize = 50;
        $currentIndex = 0;
        while ($offset < $count) {
            $documents = $qb->setFirstResult($offset)
                ->setMaxResults($docBatchSize)
                ->getQuery()
                ->getResult();
            
            foreach ($documents as $document) {
                if (in_array($currentIndex, $selectedIds)) {
                    $this->setUpdatedFields($document, $responsableIds);
                    $docCount++;
                }
                $currentIndex++;
            }
            
            $this->entityManager->flush();
            $this->entityManager->clear();
            $offset += $docBatchSize;
            
            if ($docCount % 1000 == 0) {
                $io->text(sprintf('  ... %d documents traités', $docCount));
            }
        }
        $io->text(sprintf('✓ %d documents mis à jour', $docCount));
        $updated += $docCount;

        // Fix Dossiers
        $io->section('Mise à jour des Dossiers...');
        $qb = $this->entityManager->getRepository(Dossier::class)
            ->createQueryBuilder('d')
            ->where('d.createdAt IS NOT NULL')
            ->andWhere('d.updatedAt IS NULL')
            ->orderBy('d.id', 'ASC');
        
        $count = (int) $this->entityManager->getRepository(Dossier::class)
            ->createQueryBuilder('d')
            ->select('COUNT(d.id)')
            ->where('d.createdAt IS NOT NULL')
            ->andWhere('d.updatedAt IS NULL')
            ->getQuery()
            ->getSingleScalarResult();
        $io->text(sprintf('Trouvé %d dossiers à vérifier...', $count));
        
        $targetCount = (int)($count * $percentage);
        $selectedIds = $this->getRandomIds($count, $targetCount);
        
        $offset = 0;
        $dossierCount = 0;
        $currentIndex = 0;
        while ($offset < $count) {
            $dossiers = $qb->setFirstResult($offset)
                ->setMaxResults($batchSize)
                ->getQuery()
                ->getResult();
            
            foreach ($dossiers as $dossier) {
                if (in_array($currentIndex, $selectedIds)) {
                    $this->setUpdatedFields($dossier, $responsableIds);
                    $dossierCount++;
                }
                $currentIndex++;
            }
            
            $this->entityManager->flush();
            $this->entityManager->clear();
            $offset += $batchSize;
        }
        $io->text(sprintf('✓ %d dossiers mis à jour', $dossierCount));
        $updated += $dossierCount;

        // Fix Organisations
        $io->section('Mise à jour des Organisations...');
        $qb = $this->entityManager->getRepository(Organisation::class)
            ->createQueryBuilder('o')
            ->where('o.createdAt IS NOT NULL')
            ->andWhere('o.updatedAt IS NULL')
            ->orderBy('o.id', 'ASC');
        
        $count = (int) $this->entityManager->getRepository(Organisation::class)
            ->createQueryBuilder('o')
            ->select('COUNT(o.id)')
            ->where('o.createdAt IS NOT NULL')
            ->andWhere('o.updatedAt IS NULL')
            ->getQuery()
            ->getSingleScalarResult();
        $io->text(sprintf('Trouvé %d organisations à vérifier...', $count));
        
        $targetCount = (int)($count * $percentage);
        $selectedIds = $this->getRandomIds($count, $targetCount);
        
        $offset = 0;
        $orgCount = 0;
        $currentIndex = 0;
        while ($offset < $count) {
            $organisations = $qb->setFirstResult($offset)
                ->setMaxResults($batchSize)
                ->getQuery()
                ->getResult();
            
            foreach ($organisations as $organisation) {
                if (in_array($currentIndex, $selectedIds)) {
                    $this->setUpdatedFields($organisation, $responsableIds);
                    $orgCount++;
                }
                $currentIndex++;
            }
            
            $this->entityManager->flush();
            $this->entityManager->clear();
            $offset += $batchSize;
        }
        $io->text(sprintf('✓ %d organisations mises à jour', $orgCount));
        $updated += $orgCount;

        $message = 'Champs updatedAt et updatedBy ajoutés pour ' . $updated . ' entités (40% aléatoire) !';
        $io->success($message);

        return Command::SUCCESS;
    }

    /**
     * Get random indices from 0 to count-1
     */
    private function getRandomIds(int $total, int $targetCount): array
    {
        $allIds = range(0, $total - 1);
        shuffle($allIds);
        return array_slice($allIds, 0, $targetCount);
    }

    /**
     * Set updatedAt and updatedBy fields for an entity
     */
    private function setUpdatedFields($entity, array $responsableIds): void
    {
        $createdAt = $entity->getCreatedAt();
        if (!$createdAt) {
            return;
        }

        // Get random Responsable RH
        $randomResponsableId = $responsableIds[array_rand($responsableIds)];
        $responsable = $this->entityManager->find(Employe::class, $randomResponsableId);
        if (!$responsable) {
            return;
        }

        // Set updatedBy (only if entity has BlameableTrait)
        try {
            if (method_exists($entity, 'setUpdatedBy')) {
                $entity->setUpdatedBy($responsable);
            }
        } catch (\Error $e) {
            // Skip if method doesn't exist or has wrong signature
            return;
        }

        // Set updatedAt to a random date between createdAt and now
        $createdTimestamp = $createdAt->getTimestamp();
        $nowTimestamp = time();
        
        // Only set updatedAt if createdAt is in the past
        if ($createdTimestamp < $nowTimestamp) {
            $randomTimestamp = mt_rand($createdTimestamp, $nowTimestamp);
            $updatedAt = new \DateTime();
            $updatedAt->setTimestamp($randomTimestamp);
            try {
                if (method_exists($entity, 'setUpdatedAt')) {
                    $entity->setUpdatedAt($updatedAt);
                }
            } catch (\Error $e) {
                // Skip if method doesn't exist or has wrong signature
                return;
            }
        } else {
            // If createdAt is in the future (shouldn't happen), set updatedAt to now
            try {
                if (method_exists($entity, 'setUpdatedAt')) {
                    $entity->setUpdatedAt(new \DateTime());
                }
            } catch (\Error $e) {
                // Skip if method doesn't exist or has wrong signature
                return;
            }
        }
    }
}


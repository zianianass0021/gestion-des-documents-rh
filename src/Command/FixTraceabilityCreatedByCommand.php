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
    name: 'app:fix-traceability-created-by',
    description: 'Fix createdBy fields to use Responsable RH instead of Admin RH',
)]
class FixTraceabilityCreatedByCommand extends Command
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
        $io->title('Correction des champs createdBy pour utiliser des Responsables RH');

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

        // Fix Employes - assign random Responsable RH
        $io->section('Correction des Employés...');
        $qb = $this->entityManager->getRepository(Employe::class)
            ->createQueryBuilder('e')
            ->where('e.createdBy IS NOT NULL');
        
        $count = (int) $this->entityManager->getRepository(Employe::class)
            ->createQueryBuilder('e')
            ->select('COUNT(e.id)')
            ->where('e.createdBy IS NOT NULL')
            ->getQuery()
            ->getSingleScalarResult();
        $io->text(sprintf('Trouvé %d employés à vérifier...', $count));
        
        $offset = 0;
        $employeCount = 0;
        while ($offset < $count) {
            $employes = $qb->setFirstResult($offset)
                ->setMaxResults($batchSize)
                ->getQuery()
                ->getResult();
            
            foreach ($employes as $employe) {
                // Skip if employee is a Responsable RH or Admin (they might have been created by admin)
                if (in_array('ROLE_RESPONSABLE_RH', $employe->getRoles()) || 
                    in_array('ROLE_ADMINISTRATEUR_RH', $employe->getRoles())) {
                    continue;
                }
                
                // Assign random Responsable RH
                $randomResponsableId = $responsableIds[array_rand($responsableIds)];
                $responsable = $this->entityManager->find(Employe::class, $randomResponsableId);
                if ($responsable) {
                    $employe->setCreatedBy($responsable);
                    $employeCount++;
                }
            }
            
            $this->entityManager->flush();
            $this->entityManager->clear();
            $offset += $batchSize;
        }
        $io->text(sprintf('✓ %d employés mis à jour', $employeCount));
        $updated += $employeCount;

        // Fix EmployeeContrats - assign random Responsable RH
        $io->section('Correction des Contrats...');
        $qb = $this->entityManager->getRepository(EmployeeContrat::class)
            ->createQueryBuilder('ec')
            ->where('ec.createdBy IS NOT NULL');
        
        $count = (int) $this->entityManager->getRepository(EmployeeContrat::class)
            ->createQueryBuilder('ec')
            ->select('COUNT(ec.id)')
            ->where('ec.createdBy IS NOT NULL')
            ->getQuery()
            ->getSingleScalarResult();
        $io->text(sprintf('Trouvé %d contrats à vérifier...', $count));
        
        $offset = 0;
        $contratCount = 0;
        while ($offset < $count) {
            $contrats = $qb->setFirstResult($offset)
                ->setMaxResults($batchSize)
                ->getQuery()
                ->getResult();
            
            foreach ($contrats as $contrat) {
                // Assign random Responsable RH
                $randomResponsableId = $responsableIds[array_rand($responsableIds)];
                $responsable = $this->entityManager->find(Employe::class, $randomResponsableId);
                if ($responsable) {
                    $contrat->setCreatedBy($responsable);
                    $contratCount++;
                }
            }
            
            $this->entityManager->flush();
            $this->entityManager->clear();
            $offset += $batchSize;
        }
        $io->text(sprintf('✓ %d contrats mis à jour', $contratCount));
        $updated += $contratCount;

        // Fix Documents - assign random Responsable RH
        $io->section('Correction des Documents...');
        $qb = $this->entityManager->getRepository(Document::class)
            ->createQueryBuilder('d')
            ->where('d.createdBy IS NOT NULL');
        
        $count = (int) $this->entityManager->getRepository(Document::class)
            ->createQueryBuilder('d')
            ->select('COUNT(d.id)')
            ->where('d.createdBy IS NOT NULL')
            ->getQuery()
            ->getSingleScalarResult();
        $io->text(sprintf('Trouvé %d documents à vérifier...', $count));
        
        $offset = 0;
        $docCount = 0;
        $docBatchSize = 50;
        while ($offset < $count) {
            $documents = $qb->setFirstResult($offset)
                ->setMaxResults($docBatchSize)
                ->getQuery()
                ->getResult();
            
            foreach ($documents as $document) {
                // Assign random Responsable RH
                $randomResponsableId = $responsableIds[array_rand($responsableIds)];
                $responsable = $this->entityManager->find(Employe::class, $randomResponsableId);
                if ($responsable) {
                    $document->setCreatedBy($responsable);
                    $docCount++;
                }
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

        // Fix Dossiers - assign random Responsable RH
        $io->section('Correction des Dossiers...');
        $qb = $this->entityManager->getRepository(Dossier::class)
            ->createQueryBuilder('d')
            ->where('d.createdBy IS NOT NULL');
        
        $count = (int) $this->entityManager->getRepository(Dossier::class)
            ->createQueryBuilder('d')
            ->select('COUNT(d.id)')
            ->where('d.createdBy IS NOT NULL')
            ->getQuery()
            ->getSingleScalarResult();
        $io->text(sprintf('Trouvé %d dossiers à vérifier...', $count));
        
        $offset = 0;
        $dossierCount = 0;
        while ($offset < $count) {
            $dossiers = $qb->setFirstResult($offset)
                ->setMaxResults($batchSize)
                ->getQuery()
                ->getResult();
            
            foreach ($dossiers as $dossier) {
                // Assign random Responsable RH
                $randomResponsableId = $responsableIds[array_rand($responsableIds)];
                $responsable = $this->entityManager->find(Employe::class, $randomResponsableId);
                if ($responsable) {
                    $dossier->setCreatedBy($responsable);
                    $dossierCount++;
                }
            }
            
            $this->entityManager->flush();
            $this->entityManager->clear();
            $offset += $batchSize;
        }
        $io->text(sprintf('✓ %d dossiers mis à jour', $dossierCount));
        $updated += $dossierCount;

        // Fix Organisations - assign random Responsable RH
        $io->section('Correction des Organisations...');
        $qb = $this->entityManager->getRepository(Organisation::class)
            ->createQueryBuilder('o')
            ->where('o.createdBy IS NOT NULL');
        
        $count = (int) $this->entityManager->getRepository(Organisation::class)
            ->createQueryBuilder('o')
            ->select('COUNT(o.id)')
            ->where('o.createdBy IS NOT NULL')
            ->getQuery()
            ->getSingleScalarResult();
        $io->text(sprintf('Trouvé %d organisations à vérifier...', $count));
        
        $offset = 0;
        $orgCount = 0;
        while ($offset < $count) {
            $organisations = $qb->setFirstResult($offset)
                ->setMaxResults($batchSize)
                ->getQuery()
                ->getResult();
            
            foreach ($organisations as $organisation) {
                // Assign random Responsable RH
                $randomResponsableId = $responsableIds[array_rand($responsableIds)];
                $responsable = $this->entityManager->find(Employe::class, $randomResponsableId);
                if ($responsable) {
                    $organisation->setCreatedBy($responsable);
                    $orgCount++;
                }
            }
            
            $this->entityManager->flush();
            $this->entityManager->clear();
            $offset += $batchSize;
        }
        $io->text(sprintf('✓ %d organisations mises à jour', $orgCount));
        $updated += $orgCount;

        $io->success(sprintf('Champs createdBy corrigés pour %d entités !', $updated));
        $io->note('Les entités ont maintenant un Responsable RH aléatoire comme créateur.');

        return Command::SUCCESS;
    }
}


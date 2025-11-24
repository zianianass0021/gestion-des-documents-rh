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
    name: 'app:update-traceability',
    description: 'Update traceability fields (createdBy, updatedBy) for existing entities',
)]
class UpdateTraceabilityCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private EmployeRepository $employeRepository
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // Increase memory limit for large datasets
        ini_set('memory_limit', '512M');
        set_time_limit(0);
        
        $io = new SymfonyStyle($input, $output);
        $io->title('Mise à jour de la traçabilité pour les entités existantes');

        // Get first admin user as default creator (or null if none exists)
        $admins = $this->employeRepository->findByRole('ROLE_ADMINISTRATEUR_RH');
        $adminUser = !empty($admins) ? $admins[0] : null;
        if (!$adminUser) {
            $io->warning('Aucun administrateur RH trouvé. Les entités existantes auront createdBy = null.');
        } else {
            $io->info('Utilisation de l\'administrateur: ' . $adminUser->getPrenom() . ' ' . $adminUser->getNom());
        }

        $updated = 0;
        $batchSize = 50; // Reduced batch size for documents
        $adminUserId = $adminUser ? $adminUser->getId() : null;

        // Update Employes
        $io->section('Mise à jour des Employés...');
        $qb = $this->entityManager->getRepository(Employe::class)
            ->createQueryBuilder('e')
            ->where('e.createdBy IS NULL');
        
        $count = (int) (clone $qb)->select('COUNT(e.id)')->getQuery()->getSingleScalarResult();
        $io->text(sprintf('Trouvé %d employés à mettre à jour...', $count));
        
        $offset = 0;
        while ($offset < $count) {
            // Reload admin user after clear
            $currentAdmin = $adminUserId ? $this->entityManager->find(Employe::class, $adminUserId) : null;
            
            $employes = $qb->setFirstResult($offset)
                ->setMaxResults($batchSize)
                ->getQuery()
                ->getResult();
            
            foreach ($employes as $employe) {
                if ($employe->getCreatedAt() === null) {
                    $employe->setCreatedAt(new \DateTime());
                }
                if ($employe->getCreatedBy() === null && $currentAdmin) {
                    $employe->setCreatedBy($currentAdmin);
                }
                $updated++;
            }
            
            $this->entityManager->flush();
            $this->entityManager->clear();
            $offset += $batchSize;
        }
        $io->text(sprintf('✓ %d employés mis à jour', $updated));

        // Update EmployeeContrats
        $io->section('Mise à jour des Contrats...');
        $qb = $this->entityManager->getRepository(EmployeeContrat::class)
            ->createQueryBuilder('ec')
            ->where('ec.createdBy IS NULL');
        
        $count = (int) (clone $qb)->select('COUNT(ec.id)')->getQuery()->getSingleScalarResult();
        $io->text(sprintf('Trouvé %d contrats à mettre à jour...', $count));
        
        $offset = 0;
        $contratCount = 0;
        while ($offset < $count) {
            // Reload admin user after clear
            $currentAdmin = $adminUserId ? $this->entityManager->find(Employe::class, $adminUserId) : null;
            
            $contrats = $qb->setFirstResult($offset)
                ->setMaxResults($batchSize)
                ->getQuery()
                ->getResult();
            
            foreach ($contrats as $contrat) {
                if ($contrat->getCreatedAt() === null) {
                    $contrat->setCreatedAt(new \DateTime());
                }
                if ($contrat->getCreatedBy() === null && $currentAdmin) {
                    $contrat->setCreatedBy($currentAdmin);
                }
                $contratCount++;
            }
            
            $this->entityManager->flush();
            $this->entityManager->clear();
            $offset += $batchSize;
        }
        $io->text(sprintf('✓ %d contrats mis à jour', $contratCount));
        $updated += $contratCount;

        // Update Documents (with smaller batch size)
        $io->section('Mise à jour des Documents...');
        $qb = $this->entityManager->getRepository(Document::class)
            ->createQueryBuilder('d')
            ->where('d.createdBy IS NULL');
        
        $count = (int) (clone $qb)->select('COUNT(d.id)')->getQuery()->getSingleScalarResult();
        $io->text(sprintf('Trouvé %d documents à mettre à jour...', $count));
        
        $offset = 0;
        $docCount = 0;
        $docBatchSize = 25; // Smaller batch for documents
        while ($offset < $count) {
            // Reload admin user after clear
            $currentAdmin = $adminUserId ? $this->entityManager->find(Employe::class, $adminUserId) : null;
            
            $documents = $qb->setFirstResult($offset)
                ->setMaxResults($docBatchSize)
                ->getQuery()
                ->getResult();
            
            foreach ($documents as $document) {
                if ($document->getCreatedAt() === null) {
                    $document->setCreatedAt(new \DateTime());
                }
                if ($document->getCreatedBy() === null && $currentAdmin) {
                    $document->setCreatedBy($currentAdmin);
                }
                $docCount++;
            }
            
            $this->entityManager->flush();
            $this->entityManager->clear();
            $offset += $docBatchSize;
            
            // Progress indicator
            if ($docCount % 1000 == 0) {
                $io->text(sprintf('  ... %d documents traités', $docCount));
            }
        }
        $io->text(sprintf('✓ %d documents mis à jour', $docCount));
        $updated += $docCount;

        // Update Dossiers
        $io->section('Mise à jour des Dossiers...');
        $qb = $this->entityManager->getRepository(Dossier::class)
            ->createQueryBuilder('d')
            ->where('d.createdBy IS NULL');
        
        $count = (int) (clone $qb)->select('COUNT(d.id)')->getQuery()->getSingleScalarResult();
        $io->text(sprintf('Trouvé %d dossiers à mettre à jour...', $count));
        
        $offset = 0;
        $dossierCount = 0;
        while ($offset < $count) {
            // Reload admin user after clear
            $currentAdmin = $adminUserId ? $this->entityManager->find(Employe::class, $adminUserId) : null;
            
            $dossiers = $qb->setFirstResult($offset)
                ->setMaxResults($batchSize)
                ->getQuery()
                ->getResult();
            
            foreach ($dossiers as $dossier) {
                if ($dossier->getCreatedAt() === null) {
                    $dossier->setCreatedAt(new \DateTime());
                }
                if ($dossier->getCreatedBy() === null && $currentAdmin) {
                    $dossier->setCreatedBy($currentAdmin);
                }
                $dossierCount++;
            }
            
            $this->entityManager->flush();
            $this->entityManager->clear();
            $offset += $batchSize;
        }
        $io->text(sprintf('✓ %d dossiers mis à jour', $dossierCount));
        $updated += $dossierCount;

        // Update Organisations
        $io->section('Mise à jour des Organisations...');
        $qb = $this->entityManager->getRepository(Organisation::class)
            ->createQueryBuilder('o')
            ->where('o.createdBy IS NULL');
        
        $count = (int) (clone $qb)->select('COUNT(o.id)')->getQuery()->getSingleScalarResult();
        $io->text(sprintf('Trouvé %d organisations à mettre à jour...', $count));
        
        $offset = 0;
        $orgCount = 0;
        while ($offset < $count) {
            // Reload admin user after clear
            $currentAdmin = $adminUserId ? $this->entityManager->find(Employe::class, $adminUserId) : null;
            
            $organisations = $qb->setFirstResult($offset)
                ->setMaxResults($batchSize)
                ->getQuery()
                ->getResult();
            
            foreach ($organisations as $organisation) {
                if ($organisation->getCreatedAt() === null) {
                    $organisation->setCreatedAt(new \DateTime());
                }
                if ($organisation->getCreatedBy() === null && $currentAdmin) {
                    $organisation->setCreatedBy($currentAdmin);
                }
                $orgCount++;
            }
            
            $this->entityManager->flush();
            $this->entityManager->clear();
            $offset += $batchSize;
        }
        $io->text(sprintf('✓ %d organisations mises à jour', $orgCount));
        $updated += $orgCount;

        $io->success(sprintf('Traçabilité mise à jour pour %d entités !', $updated));
        $io->note('Les nouvelles créations auront automatiquement le createdBy rempli par l\'Event Listener.');

        return Command::SUCCESS;
    }
}


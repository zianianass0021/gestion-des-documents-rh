<?php

namespace App\Command;

use App\Entity\Employe;
use App\Entity\EmployeeContrat;
use App\Entity\Document;
use App\Entity\Dossier;
use App\Entity\Organisation;
use App\Entity\Demande;
use App\Entity\Reclamation;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:fix-traceability-dates',
    description: 'Fix traceability dates for existing entities using ID-based estimation or existing date fields',
)]
class FixTraceabilityDatesCommand extends Command
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
        $io->title('Correction des dates de traçabilité pour les entités existantes');

        $updated = 0;
        $batchSize = 100;

        // Fix Employes - use ID to estimate creation date (older IDs = older dates)
        $io->section('Correction des dates pour les Employés...');
        $qb = $this->entityManager->getRepository(Employe::class)
            ->createQueryBuilder('e')
            ->orderBy('e.id', 'ASC');
        
        $count = (int) $this->entityManager->getRepository(Employe::class)
            ->createQueryBuilder('e')
            ->select('COUNT(e.id)')
            ->getQuery()
            ->getSingleScalarResult();
        $io->text(sprintf('Trouvé %d employés à vérifier...', $count));
        
        $offset = 0;
        $minId = (int) $this->entityManager->getRepository(Employe::class)
            ->createQueryBuilder('e')
            ->select('MIN(e.id)')
            ->getQuery()
            ->getSingleScalarResult();
        
        $maxId = (int) $this->entityManager->getRepository(Employe::class)
            ->createQueryBuilder('e')
            ->select('MAX(e.id)')
            ->getQuery()
            ->getSingleScalarResult();
        
        // Estimate: oldest employee created 2 years ago, newest created today
        $oldestDate = new \DateTime('-2 years');
        $newestDate = new \DateTime();
        $dateRange = $newestDate->getTimestamp() - $oldestDate->getTimestamp();
        $idRange = $maxId - $minId;
        
        while ($offset < $count) {
            $employes = $qb->setFirstResult($offset)
                ->setMaxResults($batchSize)
                ->getQuery()
                ->getResult();
            
            foreach ($employes as $employe) {
                // Only update if created_at is today (likely set by migration)
                $createdAt = $employe->getCreatedAt();
                if ($createdAt && $createdAt->format('Y-m-d') === date('Y-m-d')) {
                    // Estimate date based on ID
                    if ($idRange > 0) {
                        $progress = ($employe->getId() - $minId) / $idRange;
                        $estimatedTimestamp = $oldestDate->getTimestamp() + ($dateRange * $progress);
                        $estimatedDate = new \DateTime();
                        $estimatedDate->setTimestamp($estimatedTimestamp);
                        $employe->setCreatedAt($estimatedDate);
                        $updated++;
                    }
                }
            }
            
            $this->entityManager->flush();
            $this->entityManager->clear();
            $offset += $batchSize;
        }
        $io->text(sprintf('✓ %d employés mis à jour', $updated));

        // Fix EmployeeContrats - use date_debut if available, otherwise estimate from ID
        $io->section('Correction des dates pour les Contrats...');
        $qb = $this->entityManager->getRepository(EmployeeContrat::class)
            ->createQueryBuilder('ec')
            ->orderBy('ec.id', 'ASC');
        
        $count = (int) $this->entityManager->getRepository(EmployeeContrat::class)
            ->createQueryBuilder('ec')
            ->select('COUNT(ec.id)')
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
                $createdAt = $contrat->getCreatedAt();
                if ($createdAt && $createdAt->format('Y-m-d') === date('Y-m-d')) {
                    // Use date_debut if available, otherwise estimate
                    if ($contrat->getDateDebut()) {
                        $contrat->setCreatedAt($contrat->getDateDebut());
                    } else {
                        // Estimate based on ID
                        $minContratId = (int) $this->entityManager->getRepository(EmployeeContrat::class)
                            ->createQueryBuilder('ec')
                            ->select('MIN(ec.id)')
                            ->getQuery()
                            ->getSingleScalarResult();
                        $maxContratId = (int) $this->entityManager->getRepository(EmployeeContrat::class)
                            ->createQueryBuilder('ec')
                            ->select('MAX(ec.id)')
                            ->getQuery()
                            ->getSingleScalarResult();
                        if ($maxContratId > $minContratId) {
                            $progress = ($contrat->getId() - $minContratId) / ($maxContratId - $minContratId);
                            $estimatedTimestamp = $oldestDate->getTimestamp() + ($dateRange * $progress);
                            $estimatedDate = new \DateTime();
                            $estimatedDate->setTimestamp($estimatedTimestamp);
                            $contrat->setCreatedAt($estimatedDate);
                        }
                    }
                    $contratCount++;
                }
            }
            
            $this->entityManager->flush();
            $this->entityManager->clear();
            $offset += $batchSize;
        }
        $io->text(sprintf('✓ %d contrats mis à jour', $contratCount));
        $updated += $contratCount;

        // Fix Documents - estimate based on ID
        $io->section('Correction des dates pour les Documents...');
        $qb = $this->entityManager->getRepository(Document::class)
            ->createQueryBuilder('d')
            ->orderBy('d.id', 'ASC');
        
        $count = (int) $this->entityManager->getRepository(Document::class)
            ->createQueryBuilder('d')
            ->select('COUNT(d.id)')
            ->getQuery()
            ->getSingleScalarResult();
        $io->text(sprintf('Trouvé %d documents à vérifier...', $count));
        
        $offset = 0;
        $docCount = 0;
        $docBatchSize = 50;
        
        $minDocId = (int) $this->entityManager->getRepository(Document::class)
            ->createQueryBuilder('d')
            ->select('MIN(d.id)')
            ->getQuery()
            ->getSingleScalarResult();
        $maxDocId = (int) $this->entityManager->getRepository(Document::class)
            ->createQueryBuilder('d')
            ->select('MAX(d.id)')
            ->getQuery()
            ->getSingleScalarResult();
        $docIdRange = $maxDocId - $minDocId;
        
        while ($offset < $count) {
            $documents = $qb->setFirstResult($offset)
                ->setMaxResults($docBatchSize)
                ->getQuery()
                ->getResult();
            
            foreach ($documents as $document) {
                $createdAt = $document->getCreatedAt();
                if ($createdAt && $createdAt->format('Y-m-d') === date('Y-m-d')) {
                    if ($docIdRange > 0) {
                        $progress = ($document->getId() - $minDocId) / $docIdRange;
                        $estimatedTimestamp = $oldestDate->getTimestamp() + ($dateRange * $progress);
                        $estimatedDate = new \DateTime();
                        $estimatedDate->setTimestamp($estimatedTimestamp);
                        $document->setCreatedAt($estimatedDate);
                        $docCount++;
                    }
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

        // Fix Dossiers - use existing created_at if available, otherwise estimate
        $io->section('Correction des dates pour les Dossiers...');
        $qb = $this->entityManager->getRepository(Dossier::class)
            ->createQueryBuilder('d')
            ->orderBy('d.id', 'ASC');
        
        $count = (int) $this->entityManager->getRepository(Dossier::class)
            ->createQueryBuilder('d')
            ->select('COUNT(d.id)')
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
                $createdAt = $dossier->getCreatedAt();
                if ($createdAt && $createdAt->format('Y-m-d') === date('Y-m-d')) {
                    // Estimate based on ID
                    $minDossierId = (int) $this->entityManager->getRepository(Dossier::class)
                        ->createQueryBuilder('d')
                        ->select('MIN(d.id)')
                        ->getQuery()
                        ->getSingleScalarResult();
                    $maxDossierId = (int) $this->entityManager->getRepository(Dossier::class)
                        ->createQueryBuilder('d')
                        ->select('MAX(d.id)')
                        ->getQuery()
                        ->getSingleScalarResult();
                    if ($maxDossierId > $minDossierId) {
                        $progress = ($dossier->getId() - $minDossierId) / ($maxDossierId - $minDossierId);
                        $estimatedTimestamp = $oldestDate->getTimestamp() + ($dateRange * $progress);
                        $estimatedDate = new \DateTime();
                        $estimatedDate->setTimestamp($estimatedTimestamp);
                        $dossier->setCreatedAt($estimatedDate);
                        $dossierCount++;
                    }
                }
            }
            
            $this->entityManager->flush();
            $this->entityManager->clear();
            $offset += $batchSize;
        }
        $io->text(sprintf('✓ %d dossiers mis à jour', $dossierCount));
        $updated += $dossierCount;

        // Fix Organisations - estimate based on ID
        $io->section('Correction des dates pour les Organisations...');
        $qb = $this->entityManager->getRepository(Organisation::class)
            ->createQueryBuilder('o')
            ->orderBy('o.id', 'ASC');
        
        $count = (int) $this->entityManager->getRepository(Organisation::class)
            ->createQueryBuilder('o')
            ->select('COUNT(o.id)')
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
                $createdAt = $organisation->getCreatedAt();
                if ($createdAt && $createdAt->format('Y-m-d') === date('Y-m-d')) {
                    // Estimate based on ID
                    $minOrgId = (int) $this->entityManager->getRepository(Organisation::class)
                        ->createQueryBuilder('o')
                        ->select('MIN(o.id)')
                        ->getQuery()
                        ->getSingleScalarResult();
                    $maxOrgId = (int) $this->entityManager->getRepository(Organisation::class)
                        ->createQueryBuilder('o')
                        ->select('MAX(o.id)')
                        ->getQuery()
                        ->getSingleScalarResult();
                    if ($maxOrgId > $minOrgId) {
                        $progress = ($organisation->getId() - $minOrgId) / ($maxOrgId - $minOrgId);
                        $estimatedTimestamp = $oldestDate->getTimestamp() + ($dateRange * $progress);
                        $estimatedDate = new \DateTime();
                        $estimatedDate->setTimestamp($estimatedTimestamp);
                        $organisation->setCreatedAt($estimatedDate);
                        $orgCount++;
                    }
                }
            }
            
            $this->entityManager->flush();
            $this->entityManager->clear();
            $offset += $batchSize;
        }
        $io->text(sprintf('✓ %d organisations mises à jour', $orgCount));
        $updated += $orgCount;

        // Fix Demandes - Demandes don't use TimestampableTrait, they use dateCreation field directly
        // So we skip them as they don't have created_at field
        $io->section('Correction des dates pour les Demandes...');
        $io->text('Les demandes utilisent le champ dateCreation directement, pas de correction nécessaire.');

        // Fix Reclamations - Reclamations don't use TimestampableTrait, they use dateCreation field directly
        // So we skip them as they don't have created_at field
        $io->section('Correction des dates pour les Réclamations...');
        $io->text('Les réclamations utilisent le champ dateCreation directement, pas de correction nécessaire.');

        $io->success(sprintf('Dates de traçabilité corrigées pour %d entités !', $updated));
        $io->note('Les dates ont été estimées basées sur les IDs ou les champs de date existants.');

        return Command::SUCCESS;
    }
}


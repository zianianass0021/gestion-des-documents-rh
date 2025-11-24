<?php

namespace App\Command;

use App\Entity\Employe;
use App\Entity\EmployeeContrat;
use App\Repository\EmployeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:fix-missing-created-by',
    description: 'Fix missing createdBy for employees and contracts',
)]
class FixMissingCreatedByCommand extends Command
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
        $io->title('Correction des champs createdBy manquants');

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

        // Fix Employes
        $io->section('Correction des Employés...');
        $conn = $this->entityManager->getConnection();
        
        // Use native SQL to find employees without createdBy and exclude RH roles
        $sql = "SELECT id FROM t_user WHERE created_by_id IS NULL 
                AND CAST(roles AS TEXT) NOT LIKE '%ROLE_RESPONSABLE_RH%' 
                AND CAST(roles AS TEXT) NOT LIKE '%ROLE_ADMINISTRATEUR_RH%'";
        $result = $conn->executeQuery($sql);
        $employeeIds = $result->fetchFirstColumn();
        $count = count($employeeIds);
        $io->text(sprintf('Trouvé %d employés sans createdBy...', $count));
        
        $employeCount = 0;
        $chunks = array_chunk($employeeIds, $batchSize);
        
        foreach ($chunks as $chunk) {
            foreach ($chunk as $employeeId) {
                $employe = $this->entityManager->find(Employe::class, $employeeId);
                if ($employe) {
                    // Assign random Responsable RH
                    $randomResponsableId = $responsableIds[array_rand($responsableIds)];
                    $responsable = $this->entityManager->find(Employe::class, $randomResponsableId);
                    if ($responsable) {
                        $employe->setCreatedBy($responsable);
                        $employeCount++;
                    }
                }
            }
            
            $this->entityManager->flush();
            $this->entityManager->clear();
        }
        $io->text(sprintf('✓ %d employés mis à jour', $employeCount));
        $updated += $employeCount;

        // Fix EmployeeContrats
        $io->section('Correction des Contrats...');
        $qb = $this->entityManager->getRepository(EmployeeContrat::class)
            ->createQueryBuilder('ec')
            ->where('ec.createdBy IS NULL');
        
        $count = (int) $this->entityManager->getRepository(EmployeeContrat::class)
            ->createQueryBuilder('ec')
            ->select('COUNT(ec.id)')
            ->where('ec.createdBy IS NULL')
            ->getQuery()
            ->getSingleScalarResult();
        $io->text(sprintf('Trouvé %d contrats sans createdBy...', $count));
        
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

        $io->success(sprintf('Champs createdBy corrigés pour %d entités !', $updated));

        return Command::SUCCESS;
    }
}


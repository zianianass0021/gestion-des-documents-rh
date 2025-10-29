<?php

namespace App\Command;

use App\Entity\Employe;
use App\Entity\EmployeeContrat;
use App\Entity\NatureContrat;
use App\Entity\Organisation;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:clean-test-data',
    description: 'Clean test data (employees with @test.ma email)',
)]
class CleanTestDataCommand extends Command
{
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Nettoyage des données de test');

        // Compter les employés de test
        $testEmployees = $this->entityManager->getRepository(Employe::class)
            ->createQueryBuilder('e')
            ->where('e.email LIKE :email')
            ->setParameter('email', '%@test.ma')
            ->getQuery()
            ->getResult();

        $count = count($testEmployees);

        if ($count === 0) {
            $io->info('Aucune donnée de test trouvée.');
            return Command::SUCCESS;
        }

        $io->warning("{$count} employés de test vont être supprimés.");

        if (!$io->confirm('Êtes-vous sûr de vouloir continuer ?', false)) {
            return Command::FAILURE;
        }

        $io->progressStart($count);

        // Supprimer les contrats d'abord
        foreach ($testEmployees as $employee) {
            $contracts = $this->entityManager->getRepository(EmployeeContrat::class)
                ->findBy(['employe' => $employee]);
            
            foreach ($contracts as $contract) {
                $this->entityManager->remove($contract);
            }
        }

        // Supprimer les employés
        foreach ($testEmployees as $employee) {
            $this->entityManager->remove($employee);
            $io->progressAdvance();
        }

        $this->entityManager->flush();
        $io->progressFinish();

        $io->success("{$count} employés de test ont été supprimés avec succès !");

        return Command::SUCCESS;
    }
}

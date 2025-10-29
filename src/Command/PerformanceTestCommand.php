<?php

namespace App\Command;

use App\Entity\Employe;
use App\Entity\EmployeeContrat;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:performance-test',
    description: 'Test performance with 1000 employees',
)]
class PerformanceTestCommand extends Command
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

        $io->title('Test de Performance - 1000 Employés');

        // Test 1: Compter tous les employés
        $io->section('Test 1: Compter tous les employés');
        $startTime = microtime(true);
        $employeeCount = $this->entityManager->getRepository(Employe::class)->count([]);
        $endTime = microtime(true);
        $io->text("Nombre d'employés: {$employeeCount}");
        $io->text("Temps d'exécution: " . round(($endTime - $startTime) * 1000, 2) . " ms");

        // Test 2: Récupérer tous les employés avec leurs contrats
        $io->section('Test 2: Récupérer tous les employés avec leurs contrats');
        $startTime = microtime(true);
        $employees = $this->entityManager->getRepository(Employe::class)
            ->createQueryBuilder('e')
            ->leftJoin('e.employeeContrats', 'c')
            ->addSelect('c')
            ->getQuery()
            ->getResult();
        $endTime = microtime(true);
        $io->text("Nombre d'employés récupérés: " . count($employees));
        $io->text("Temps d'exécution: " . round(($endTime - $startTime) * 1000, 2) . " ms");

        // Test 3: Recherche par nom
        $io->section('Test 3: Recherche par nom (Alami)');
        $startTime = microtime(true);
        $employeesByName = $this->entityManager->getRepository(Employe::class)
            ->createQueryBuilder('e')
            ->where('e.nom LIKE :nom')
            ->setParameter('nom', '%Alami%')
            ->getQuery()
            ->getResult();
        $endTime = microtime(true);
        $io->text("Nombre d'employés trouvés: " . count($employeesByName));
        $io->text("Temps d'exécution: " . round(($endTime - $startTime) * 1000, 2) . " ms");

        // Test 4: Recherche par email
        $io->section('Test 4: Recherche par email');
        $startTime = microtime(true);
        $employeesByEmail = $this->entityManager->getRepository(Employe::class)
            ->createQueryBuilder('e')
            ->where('e.email LIKE :email')
            ->setParameter('email', '%@test.ma')
            ->getQuery()
            ->getResult();
        $endTime = microtime(true);
        $io->text("Nombre d'employés de test: " . count($employeesByEmail));
        $io->text("Temps d'exécution: " . round(($endTime - $startTime) * 1000, 2) . " ms");

        // Test 5: Pagination (première page de 20 employés)
        $io->section('Test 5: Pagination (20 employés par page)');
        $startTime = microtime(true);
        $paginatedEmployees = $this->entityManager->getRepository(Employe::class)
            ->createQueryBuilder('e')
            ->setFirstResult(0)
            ->setMaxResults(20)
            ->getQuery()
            ->getResult();
        $endTime = microtime(true);
        $io->text("Nombre d'employés récupérés: " . count($paginatedEmployees));
        $io->text("Temps d'exécution: " . round(($endTime - $startTime) * 1000, 2) . " ms");

        // Test 6: Compter les contrats
        $io->section('Test 6: Compter tous les contrats');
        $startTime = microtime(true);
        $contractCount = $this->entityManager->getRepository(EmployeeContrat::class)->count([]);
        $endTime = microtime(true);
        $io->text("Nombre de contrats: {$contractCount}");
        $io->text("Temps d'exécution: " . round(($endTime - $startTime) * 1000, 2) . " ms");

        // Test 7: Statistiques des contrats par employé
        $io->section('Test 7: Statistiques des contrats par employé');
        $startTime = microtime(true);
        $contractStats = $this->entityManager->getRepository(EmployeeContrat::class)
            ->createQueryBuilder('c')
            ->select('COUNT(c.id) as contract_count')
            ->addSelect('COUNT(DISTINCT c.employe) as employee_count')
            ->getQuery()
            ->getSingleResult();
        $endTime = microtime(true);
        $io->text("Nombre total de contrats: " . $contractStats['contract_count']);
        $io->text("Nombre d'employés avec contrats: " . $contractStats['employee_count']);
        $io->text("Temps d'exécution: " . round(($endTime - $startTime) * 1000, 2) . " ms");

        // Test 8: Recherche complexe (employés avec plus d'un contrat)
        $io->section('Test 8: Employés avec plus d\'un contrat');
        $startTime = microtime(true);
        $employeesWithMultipleContracts = $this->entityManager->getRepository(Employe::class)
            ->createQueryBuilder('e')
            ->select('e.id, e.nom, e.prenom, COUNT(c.id) as contract_count')
            ->leftJoin('e.employeeContrats', 'c')
            ->groupBy('e.id')
            ->having('COUNT(c.id) > 1')
            ->getQuery()
            ->getResult();
        $endTime = microtime(true);
        $io->text("Nombre d'employés avec plusieurs contrats: " . count($employeesWithMultipleContracts));
        $io->text("Temps d'exécution: " . round(($endTime - $startTime) * 1000, 2) . " ms");

        $io->success('Tests de performance terminés !');

        return Command::SUCCESS;
    }
}

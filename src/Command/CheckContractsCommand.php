<?php

namespace App\Command;

use App\Entity\EmployeeContrat;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:check-contracts',
    description: 'Check contracts in database',
)]
class CheckContractsCommand extends Command
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

        $io->title('Vérification des Contrats');

        // Compter tous les contrats
        $totalContracts = $this->entityManager->getRepository(EmployeeContrat::class)->count([]);
        $io->text("Total de contrats: {$totalContracts}");

        // Afficher les 10 premiers contrats
        $contracts = $this->entityManager->getRepository(EmployeeContrat::class)
            ->createQueryBuilder('c')
            ->leftJoin('c.employe', 'e')
            ->addSelect('e')
            ->orderBy('c.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult();

        if ($contracts) {
            $io->section('Premiers 10 contrats:');
            $table = $io->createTable();
            $table->setHeaders(['ID', 'Employé', 'Type Contrat', 'Date Début', 'Date Fin', 'Statut', 'Salaire']);

            foreach ($contracts as $contract) {
                $table->addRow([
                    $contract->getId(),
                    $contract->getEmploye() ? $contract->getEmploye()->getPrenom() . ' ' . $contract->getEmploye()->getNom() : 'N/A',
                    $contract->getNatureContrat() ? $contract->getNatureContrat()->getDesignation() : 'N/A',
                    $contract->getDateDebut() ? $contract->getDateDebut()->format('Y-m-d') : 'N/A',
                    $contract->getDateFin() ? $contract->getDateFin()->format('Y-m-d') : 'N/A',
                    $contract->getStatut(),
                    $contract->getSalaire()
                ]);
            }

            $table->render();
        }

        // Statistiques par statut
        $io->section('Statistiques par statut:');
        $statusStats = $this->entityManager->getRepository(EmployeeContrat::class)
            ->createQueryBuilder('c')
            ->select('c.statut, COUNT(c.id) as count')
            ->groupBy('c.statut')
            ->orderBy('count', 'DESC')
            ->getQuery()
            ->getResult();

        foreach ($statusStats as $stat) {
            $io->text("Statut '{$stat['statut']}': {$stat['count']} contrats");
        }

        // Statistiques par type de contrat
        $io->section('Statistiques par type de contrat:');
        $typeStats = $this->entityManager->getRepository(EmployeeContrat::class)
            ->createQueryBuilder('c')
            ->leftJoin('c.natureContrat', 'nc')
            ->select('nc.designation, COUNT(c.id) as count')
            ->groupBy('nc.designation')
            ->orderBy('count', 'DESC')
            ->getQuery()
            ->getResult();

        foreach ($typeStats as $stat) {
            $designation = $stat['designation'] ?? 'Non défini';
            $io->text("Type '{$designation}': {$stat['count']} contrats");
        }

        // Contrats par employé
        $io->section('Statistiques des contrats par employé:');
        $employeeStats = $this->entityManager->getRepository(EmployeeContrat::class)
            ->createQueryBuilder('c')
            ->select('COUNT(c.id) as contract_count')
            ->addSelect('COUNT(DISTINCT c.employe) as employee_count')
            ->getQuery()
            ->getSingleResult();

        $io->text("Nombre total de contrats: " . $employeeStats['contract_count']);
        $io->text("Nombre d'employés avec contrats: " . $employeeStats['employee_count']);
        
        if ($employeeStats['employee_count'] > 0) {
            $avgContracts = round($employeeStats['contract_count'] / $employeeStats['employee_count'], 2);
            $io->text("Moyenne de contrats par employé: " . $avgContracts);
        }

        $io->success('Vérification terminée !');

        return Command::SUCCESS;
    }
}

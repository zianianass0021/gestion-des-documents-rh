<?php

namespace App\Command;

use App\Entity\Employe;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:show-test-usernames',
    description: 'Show examples of test usernames generated',
)]
class ShowTestUsernamesCommand extends Command
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

        $io->title('Exemples d\'Usernames de Test Générés');

        // Récupérer les 10 premiers employés de test
        $testEmployees = $this->entityManager->getRepository(Employe::class)
            ->createQueryBuilder('e')
            ->where('e.email LIKE :email')
            ->setParameter('email', '%@test.ma')
            ->orderBy('e.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult();

        if ($testEmployees) {
            $io->section('Premiers 10 usernames générés:');
            $table = $io->createTable();
            $table->setHeaders(['ID', 'Prénom', 'Nom', 'Username', 'Email']);

            foreach ($testEmployees as $employee) {
                $table->addRow([
                    $employee->getId(),
                    $employee->getPrenom(),
                    $employee->getNom(),
                    $employee->getUsername(),
                    $employee->getEmail()
                ]);
            }

            $table->render();
        }

        // Statistiques
        $totalTestEmployees = $this->entityManager->getRepository(Employe::class)
            ->createQueryBuilder('e')
            ->select('COUNT(e.id)')
            ->where('e.email LIKE :email')
            ->setParameter('email', '%@test.ma')
            ->getQuery()
            ->getSingleScalarResult();

        $io->section('Statistiques');
        $io->text("Total d'employés de test : {$totalTestEmployees}");
        $io->text("Format des usernames : prenom.nom{numero}");
        $io->text("Mot de passe : password123");
        $io->text("Domaine email : @test.ma");

        // Exemples de connexion
        $io->section('Exemples de Connexion');
        if ($testEmployees) {
            $firstEmployee = $testEmployees[0];
            $io->text("Username : {$firstEmployee->getUsername()}");
            $io->text("Email : {$firstEmployee->getEmail()}");
            $io->text("Mot de passe : password123");
        }

        $io->success('Affichage terminé !');

        return Command::SUCCESS;
    }
}

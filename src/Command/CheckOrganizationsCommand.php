<?php

namespace App\Command;

use App\Entity\Organisation;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:check-organizations',
    description: 'Check organizations in database',
)]
class CheckOrganizationsCommand extends Command
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

        $io->title('Vérification des Organisations');

        // Compter toutes les organisations
        $totalOrganizations = $this->entityManager->getRepository(Organisation::class)->count([]);
        $io->text("Total d'organisations: {$totalOrganizations}");

        // Afficher les 10 premières organisations
        $organizations = $this->entityManager->getRepository(Organisation::class)
            ->createQueryBuilder('o')
            ->orderBy('o.dossierDesignation', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult();

        if ($organizations) {
            $io->section('Premières 10 organisations:');
            $table = $io->createTable();
            $table->setHeaders(['ID', 'Code', 'DAS', 'Division', 'Groupement', 'Dossier', 'Désignation']);

            foreach ($organizations as $org) {
                $table->addRow([
                    $org->getId(),
                    $org->getCode(),
                    $org->getDas(),
                    $org->getDivisionActivitesStrategiques(),
                    $org->getGroupement(),
                    $org->getDossier(),
                    $org->getDossierDesignation()
                ]);
            }

            $table->render();
        }

        // Statistiques par DAS
        $io->section('Statistiques par DAS:');
        $dasStats = $this->entityManager->getRepository(Organisation::class)
            ->createQueryBuilder('o')
            ->select('o.das, COUNT(o.id) as count')
            ->groupBy('o.das')
            ->orderBy('count', 'DESC')
            ->getQuery()
            ->getResult();

        foreach ($dasStats as $stat) {
            $io->text("DAS {$stat['das']}: {$stat['count']} organisations");
        }

        $io->success('Vérification terminée !');

        return Command::SUCCESS;
    }
}

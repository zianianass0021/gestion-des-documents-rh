<?php

namespace App\Command;

use App\Entity\Placard;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:check-placards',
    description: 'Check placards in database',
)]
class CheckPlacardsCommand extends Command
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

        $io->title('Vérification des Placards');

        // Compter tous les placards
        $totalPlacards = $this->entityManager->getRepository(Placard::class)->count([]);
        $io->text("Total de placards: {$totalPlacards}");

        // Afficher tous les placards
        $placards = $this->entityManager->getRepository(Placard::class)
            ->createQueryBuilder('p')
            ->orderBy('p.id', 'ASC')
            ->getQuery()
            ->getResult();

        if ($placards) {
            $io->section('Tous les placards:');
            $table = $io->createTable();
            $table->setHeaders(['ID', 'Nom', 'Localisation', 'Dossiers', 'Créé le']);

            foreach ($placards as $placard) {
                $table->addRow([
                    $placard->getId(),
                    $placard->getName(),
                    $placard->getLocation() ? substr($placard->getLocation(), 0, 50) . '...' : 'N/A',
                    $placard->getDossiers()->count(),
                    $placard->getCreatedAt() ? $placard->getCreatedAt()->format('Y-m-d') : 'N/A'
                ]);
            }

            $table->render();
        }

        // Statistiques des dossiers par placard
        $io->section('Statistiques des dossiers par placard:');
        $dossierStats = $this->entityManager->getRepository(Placard::class)
            ->createQueryBuilder('p')
            ->select('p.name, COUNT(d.id) as count')
            ->leftJoin('p.dossiers', 'd')
            ->groupBy('p.id, p.name')
            ->orderBy('count', 'DESC')
            ->getQuery()
            ->getResult();

        foreach ($dossierStats as $stat) {
            $io->text("Placard '{$stat['name']}': {$stat['count']} dossiers");
        }

        // Placards vides
        $placardsVides = $this->entityManager->getRepository(Placard::class)
            ->createQueryBuilder('p')
            ->leftJoin('p.dossiers', 'd')
            ->where('d.id IS NULL')
            ->getQuery()
            ->getResult();

        $io->section('Placards vides:');
        $io->text("Nombre de placards vides: " . count($placardsVides));

        if (count($placardsVides) > 0) {
            foreach ($placardsVides as $placard) {
                $io->text("- {$placard->getName()}");
            }
        }

        $io->success('Vérification terminée !');

        return Command::SUCCESS;
    }
}

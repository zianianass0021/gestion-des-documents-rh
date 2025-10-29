<?php

namespace App\Command;

use App\Entity\Dossier;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:check-dossiers',
    description: 'Check dossiers in database',
)]
class CheckDossiersCommand extends Command
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

        $io->title('Vérification des Dossiers');

        // Compter tous les dossiers
        $totalDossiers = $this->entityManager->getRepository(Dossier::class)->count([]);
        $io->text("Total de dossiers: {$totalDossiers}");

        // Afficher les 10 premiers dossiers
        $dossiers = $this->entityManager->getRepository(Dossier::class)
            ->createQueryBuilder('d')
            ->leftJoin('d.employe', 'e')
            ->leftJoin('d.placard', 'p')
            ->addSelect('e', 'p')
            ->orderBy('d.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult();

        if ($dossiers) {
            $io->section('Premiers 10 dossiers:');
            $table = $io->createTable();
            $table->setHeaders(['ID', 'Employé', 'Placard', 'Statut', 'Documents', 'Créé le']);

            foreach ($dossiers as $dossier) {
                $table->addRow([
                    $dossier->getId(),
                    $dossier->getEmploye() ? $dossier->getEmploye()->getPrenom() . ' ' . $dossier->getEmploye()->getNom() : 'N/A',
                    $dossier->getPlacard() ? $dossier->getPlacard()->getName() : 'Aucun',
                    $dossier->getStatus(),
                    $dossier->getDocuments()->count(),
                    $dossier->getCreatedAt() ? $dossier->getCreatedAt()->format('Y-m-d') : 'N/A'
                ]);
            }

            $table->render();
        }

        // Statistiques par statut
        $io->section('Statistiques par statut:');
        $statusStats = $this->entityManager->getRepository(Dossier::class)
            ->createQueryBuilder('d')
            ->select('d.status, COUNT(d.id) as count')
            ->groupBy('d.status')
            ->orderBy('count', 'DESC')
            ->getQuery()
            ->getResult();

        foreach ($statusStats as $stat) {
            $status = $stat['status'] ?? 'Non défini';
            $io->text("Statut '{$status}': {$stat['count']} dossiers");
        }

        // Statistiques par placard
        $io->section('Statistiques par placard:');
        $placardStats = $this->entityManager->getRepository(Dossier::class)
            ->createQueryBuilder('d')
            ->leftJoin('d.placard', 'p')
            ->select('p.name, COUNT(d.id) as count')
            ->groupBy('p.name')
            ->orderBy('count', 'DESC')
            ->getQuery()
            ->getResult();

        foreach ($placardStats as $stat) {
            $placardName = $stat['name'] ?? 'Aucun placard';
            $io->text("Placard '{$placardName}': {$stat['count']} dossiers");
        }

        // Dossiers sans placard
        $dossiersSansPlacard = $this->entityManager->getRepository(Dossier::class)
            ->createQueryBuilder('d')
            ->where('d.placard IS NULL')
            ->getQuery()
            ->getResult();

        $io->section('Dossiers sans placard:');
        $io->text("Nombre de dossiers sans placard: " . count($dossiersSansPlacard));

        $io->success('Vérification terminée !');

        return Command::SUCCESS;
    }
}

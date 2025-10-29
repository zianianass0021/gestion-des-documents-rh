<?php

namespace App\Command;

use App\Entity\Employe;
use App\Entity\Dossier;
use App\Entity\Placard;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:create-missing-dossiers',
    description: 'Create missing dossiers for employees who don\'t have one',
)]
class CreateMissingDossiersCommand extends Command
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

        $io->title('Création des Dossiers Manquants');

        // Récupérer tous les employés qui n'ont pas de dossier (employés seulement)
        // Utilisation d'une requête SQL directe pour éviter les problèmes avec le type JSON
        $sql = "
            SELECT e.id 
            FROM t_employe e 
            LEFT JOIN t_dossier d ON e.id = d.employe_id 
            WHERE d.id IS NULL 
            AND e.roles::text LIKE '%ROLE_EMPLOYEE%'
        ";
        
        $connection = $this->entityManager->getConnection();
        $result = $connection->executeQuery($sql);
        $employeIds = $result->fetchFirstColumn();
        
        $employesSansDossier = [];
        if (!empty($employeIds)) {
            $employesSansDossier = $this->entityManager->getRepository(Employe::class)
                ->findBy(['id' => $employeIds]);
        }

        $io->text("Employés sans dossier trouvés: " . count($employesSansDossier));

        if (count($employesSansDossier) === 0) {
            $io->success('Tous les employés ont déjà un dossier !');
            return Command::SUCCESS;
        }

        // Récupérer tous les placards disponibles
        $placards = $this->entityManager->getRepository(Placard::class)->findAll();
        
        if (empty($placards)) {
            $io->error('Aucun placard trouvé ! Créez d\'abord des placards.');
            return Command::FAILURE;
        }

        $io->text("Placards disponibles: " . count($placards));

        // Créer les dossiers manquants
        $created = 0;
        $progressBar = $io->createProgressBar(count($employesSansDossier));
        $progressBar->start();

        foreach ($employesSansDossier as $employe) {
            $dossier = new Dossier();
            $dossier->setEmploye($employe);
            
            // Définir le nom du dossier
            $dossier->setNom("Dossier " . $employe->getPrenom() . " " . $employe->getNom());
            
            // Assigner un placard aléatoire
            $randomPlacard = $placards[array_rand($placards)];
            $dossier->setPlacard($randomPlacard);
            
            // Définir un statut aléatoire
            $statuses = ['actif', 'completed', 'in_progress', 'pending'];
            $dossier->setStatus($statuses[array_rand($statuses)]);
            
            // Ajouter une description
            $dossier->setDescription("Dossier personnel de " . $employe->getPrenom() . " " . $employe->getNom());

            $this->entityManager->persist($dossier);
            $created++;

            // Flush par batch de 100 pour éviter les problèmes de mémoire
            if ($created % 100 === 0) {
                $this->entityManager->flush();
                $this->entityManager->clear();
                
                // Recharger les placards après le clear
                $placards = $this->entityManager->getRepository(Placard::class)->findAll();
            }

            $progressBar->advance();
        }

        // Flush final
        $this->entityManager->flush();
        $progressBar->finish();

        $io->newLine(2);
        $io->success("Création terminée ! {$created} dossiers créés.");

        // Afficher les statistiques finales
        $totalDossiers = $this->entityManager->getRepository(Dossier::class)->count([]);
        $io->text("Total de dossiers maintenant: {$totalDossiers}");

        // Statistiques par placard
        $io->section('Répartition par placard:');
        foreach ($placards as $placard) {
            $count = $this->entityManager->getRepository(Dossier::class)
                ->createQueryBuilder('d')
                ->select('COUNT(d.id)')
                ->where('d.placard = :placard')
                ->setParameter('placard', $placard)
                ->getQuery()
                ->getSingleScalarResult();
            
            $io->text("{$placard->getName()}: {$count} dossiers");
        }

        return Command::SUCCESS;
    }
}

<?php

namespace App\Command;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:create-missing-dossiers-sql',
    description: 'Create missing dossiers using SQL queries',
)]
class CreateMissingDossiersSQLCommand extends Command
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

        $io->title('Création des Dossiers Manquants (SQL)');

        $connection = $this->entityManager->getConnection();

        // Compter les employés sans dossier
        $countSql = "
            SELECT COUNT(*) 
            FROM t_employe e 
            LEFT JOIN t_dossier d ON e.id = d.employe_id 
            WHERE d.id IS NULL 
            AND e.roles::text LIKE '%ROLE_EMPLOYEE%'
        ";
        
        $count = $connection->executeQuery($countSql)->fetchOne();
        $io->text("Employés sans dossier trouvés: {$count}");

        if ($count == 0) {
            $io->success('Tous les employés ont déjà un dossier !');
            return Command::SUCCESS;
        }

        // Récupérer les IDs des placards
        $placardSql = "SELECT id FROM p_placards ORDER BY id";
        $placardIds = $connection->executeQuery($placardSql)->fetchFirstColumn();
        
        if (empty($placardIds)) {
            $io->error('Aucun placard trouvé ! Créez d\'abord des placards.');
            return Command::FAILURE;
        }

        $io->text("Placards disponibles: " . count($placardIds));

        // Créer les dossiers manquants avec SQL
        $insertSql = "
            INSERT INTO t_dossier (id, employe_id, nom, description, status, created_at, placard_id, emplacement)
            SELECT 
                nextval('t_dossier_id_seq') as id,
                e.id as employe_id,
                'Dossier ' || e.prenom || ' ' || e.nom as nom,
                'Dossier personnel de ' || e.prenom || ' ' || e.nom as description,
                CASE (random() * 4)::int
                    WHEN 0 THEN 'actif'
                    WHEN 1 THEN 'completed'
                    WHEN 2 THEN 'in_progress'
                    ELSE 'pending'
                END as status,
                NOW() as created_at,
                p_placards.id as placard_id,
                NULL as emplacement
            FROM t_employe e
            CROSS JOIN LATERAL (
                SELECT id FROM p_placards 
                ORDER BY random() 
                LIMIT 1
            ) p_placards
            LEFT JOIN t_dossier d ON e.id = d.employe_id 
            WHERE d.id IS NULL 
            AND e.roles::text LIKE '%ROLE_EMPLOYEE%'
        ";

        $io->text("Création des dossiers en cours...");
        $result = $connection->executeStatement($insertSql);
        
        $io->success("Création terminée ! {$result} dossiers créés.");

        // Afficher les statistiques finales
        $totalDossiers = $connection->executeQuery("SELECT COUNT(*) FROM t_dossier")->fetchOne();
        $io->text("Total de dossiers maintenant: {$totalDossiers}");

        // Statistiques par placard
        $io->section('Répartition par placard:');
        $statsSql = "
            SELECT p.name, COUNT(d.id) as count
            FROM p_placards p
            LEFT JOIN t_dossier d ON p.id = d.placard_id
            GROUP BY p.id, p.name
            ORDER BY count DESC
        ";
        
        $stats = $connection->executeQuery($statsSql)->fetchAllAssociative();
        foreach ($stats as $stat) {
            $io->text("{$stat['name']}: {$stat['count']} dossiers");
        }

        // Statistiques par statut
        $io->section('Répartition par statut:');
        $statusSql = "
            SELECT status, COUNT(*) as count
            FROM t_dossier
            GROUP BY status
            ORDER BY count DESC
        ";
        
        $statusStats = $connection->executeQuery($statusSql)->fetchAllAssociative();
        foreach ($statusStats as $stat) {
            $io->text("Statut '{$stat['status']}': {$stat['count']} dossiers");
        }

        return Command::SUCCESS;
    }
}

<?php

namespace App\Command;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:rebalance-simple',
    description: 'Rebalance distribution using simple approach',
)]
class RebalanceSimpleCommand extends Command
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
        $io->title('Rééquilibrage Simple de la Distribution');

        $connection = $this->entityManager->getConnection();

        // 1. Rééquilibrer les dossiers dans les placards
        $io->section('Rééquilibrage des dossiers dans les placards...');
        
        // Récupérer tous les placards disponibles
        $placards = $connection->executeQuery("SELECT id FROM p_placards ORDER BY id")->fetchFirstColumn();
        $io->text("Placards disponibles: " . count($placards));
        
        // Réassigner tous les dossiers de manière cyclique
        $dossierSql = "
            WITH dossiers_numbered AS (
                SELECT id, ROW_NUMBER() OVER (ORDER BY id) as rn
                FROM t_dossier
            )
            UPDATE t_dossier 
            SET placard_id = (
                SELECT id FROM p_placards 
                ORDER BY id 
                LIMIT 1 OFFSET ((SELECT rn FROM dossiers_numbered WHERE dossiers_numbered.id = t_dossier.id) % " . count($placards) . ")
            )
        ";
        
        $dossierResult = $connection->executeStatement($dossierSql);
        $io->text("Réassignés {$dossierResult} dossiers");

        // Vérifier la nouvelle distribution des placards
        $placardDistribution = $connection->executeQuery("
            SELECT placard_id, COUNT(*) as nombre_dossiers 
            FROM t_dossier 
            GROUP BY placard_id 
            ORDER BY nombre_dossiers DESC
        ")->fetchAllAssociative();
        
        $io->text("Nouvelle distribution des placards:");
        foreach ($placardDistribution as $placard) {
            $io->text("Placard {$placard['placard_id']}: {$placard['nombre_dossiers']} dossiers");
        }

        // 2. Rééquilibrer les contrats dans les organisations
        $io->section('Rééquilibrage des contrats dans les organisations...');
        
        // Récupérer toutes les organisations disponibles
        $organizations = $connection->executeQuery("SELECT id FROM p_organisation ORDER BY id")->fetchFirstColumn();
        $io->text("Organisations disponibles: " . count($organizations));
        
        // Réassigner tous les contrats de manière cyclique
        $contractSql = "
            WITH contrats_numbered AS (
                SELECT id, ROW_NUMBER() OVER (ORDER BY id) as rn
                FROM t_organisation_employee_contrat
            )
            UPDATE t_organisation_employee_contrat 
            SET organisation_id = (
                SELECT id FROM p_organisation 
                ORDER BY id 
                LIMIT 1 OFFSET ((SELECT rn FROM contrats_numbered WHERE contrats_numbered.id = t_organisation_employee_contrat.id) % " . count($organizations) . ")
            )
        ";
        
        $contractResult = $connection->executeStatement($contractSql);
        $io->text("Réassignés {$contractResult} contrats");

        // Vérifier la nouvelle distribution des organisations
        $orgDistribution = $connection->executeQuery("
            SELECT organisation_id, COUNT(*) as nombre_contrats 
            FROM t_organisation_employee_contrat 
            GROUP BY organisation_id 
            ORDER BY nombre_contrats DESC
            LIMIT 15
        ")->fetchAllAssociative();
        
        $io->text("Nouvelle distribution des organisations (top 15):");
        foreach ($orgDistribution as $org) {
            $io->text("Organisation {$org['organisation_id']}: {$org['nombre_contrats']} contrats");
        }

        // 3. Statistiques finales
        $io->section('Statistiques finales');
        
        $totalDossiers = $connection->executeQuery("SELECT COUNT(*) FROM t_dossier")->fetchOne();
        $totalContrats = $connection->executeQuery("SELECT COUNT(*) FROM t_organisation_employee_contrat")->fetchOne();
        $totalPlacards = $connection->executeQuery("SELECT COUNT(*) FROM p_placards")->fetchOne();
        $totalOrganisations = $connection->executeQuery("SELECT COUNT(*) FROM p_organisation")->fetchOne();

        $io->text("Total dossiers: {$totalDossiers}");
        $io->text("Total contrats: {$totalContrats}");
        $io->text("Total placards: {$totalPlacards}");
        $io->text("Total organisations: {$totalOrganisations}");

        // Calculer les moyennes
        $moyenneDossiersParPlacard = round($totalDossiers / $totalPlacards, 2);
        $moyenneContratsParOrganisation = round($totalContrats / $totalOrganisations, 2);

        $io->text("Moyenne dossiers par placard: {$moyenneDossiersParPlacard}");
        $io->text("Moyenne contrats par organisation: {$moyenneContratsParOrganisation}");

        $io->success("Rééquilibrage terminé avec succès !");
        return Command::SUCCESS;
    }
}

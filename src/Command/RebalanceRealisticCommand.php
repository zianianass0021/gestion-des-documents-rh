<?php

namespace App\Command;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:rebalance-realistic',
    description: 'Rebalance distribution with realistic variation',
)]
class RebalanceRealisticCommand extends Command
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
        $io->title('Rééquilibrage Réaliste de la Distribution');

        $connection = $this->entityManager->getConnection();

        // 1. Rééquilibrer les dossiers dans les placards avec variation réaliste
        $io->section('Rééquilibrage réaliste des dossiers dans les placards...');
        
        // Récupérer tous les placards disponibles
        $placards = $connection->executeQuery("SELECT id FROM p_placards ORDER BY id")->fetchFirstColumn();
        $io->text("Placards disponibles: " . count($placards));
        
        // Calculer la distribution réaliste (variation de ±20%)
        $totalDossiers = $connection->executeQuery("SELECT COUNT(*) FROM t_dossier")->fetchOne();
        $moyenneParPlacard = $totalDossiers / count($placards);
        
        // Créer des poids réalistes pour chaque placard
        $placardWeights = [];
        foreach ($placards as $index => $placardId) {
            // Variation de 80% à 120% de la moyenne
            $variation = 0.8 + (rand(0, 40) / 100); // 0.8 à 1.2
            $placardWeights[$placardId] = $variation;
        }
        
        // Normaliser les poids pour qu'ils totalisent 1
        $totalWeight = array_sum($placardWeights);
        foreach ($placardWeights as $placardId => $weight) {
            $placardWeights[$placardId] = $weight / $totalWeight;
        }
        
        // Réassigner les dossiers selon les poids
        $dossierSql = "
            WITH dossiers_numbered AS (
                SELECT id, ROW_NUMBER() OVER (ORDER BY id) as rn
                FROM t_dossier
            ),
            placard_assignments AS (
                SELECT 
                    dn.id,
                    CASE 
                        WHEN dn.rn <= " . intval($placardWeights[$placards[0]] * $totalDossiers) . " THEN {$placards[0]}
                        " . implode(' ', array_map(function($index) use ($placards, $placardWeights, $totalDossiers) {
                            if ($index === 0) return '';
                            $cumulative = 0;
                            for ($i = 0; $i < $index; $i++) {
                                $cumulative += $placardWeights[$placards[$i]] * $totalDossiers;
                            }
                            $current = $placardWeights[$placards[$index]] * $totalDossiers;
                            return "WHEN dn.rn <= " . intval($cumulative + $current) . " THEN {$placards[$index]}";
                        }, range(1, count($placards) - 1))) . "
                        ELSE {$placards[count($placards) - 1]}
                    END as new_placard_id
                FROM dossiers_numbered dn
            )
            UPDATE t_dossier 
            SET placard_id = pa.new_placard_id
            FROM placard_assignments pa
            WHERE t_dossier.id = pa.id
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
        
        $io->text("Nouvelle distribution réaliste des placards:");
        foreach ($placardDistribution as $placard) {
            $io->text("Placard {$placard['placard_id']}: {$placard['nombre_dossiers']} dossiers");
        }

        // 2. Rééquilibrer les contrats dans les organisations avec variation réaliste
        $io->section('Rééquilibrage réaliste des contrats dans les organisations...');
        
        // Récupérer toutes les organisations disponibles
        $organizations = $connection->executeQuery("SELECT id FROM p_organisation ORDER BY id")->fetchFirstColumn();
        $io->text("Organisations disponibles: " . count($organizations));
        
        // Calculer la distribution réaliste pour les organisations
        $totalContrats = $connection->executeQuery("SELECT COUNT(*) FROM t_organisation_employee_contrat")->fetchOne();
        $moyenneParOrganisation = $totalContrats / count($organizations);
        
        // Créer des poids réalistes pour chaque organisation
        $orgWeights = [];
        foreach ($organizations as $index => $orgId) {
            // Variation de 70% à 130% de la moyenne (plus de variation pour les organisations)
            $variation = 0.7 + (rand(0, 60) / 100); // 0.7 à 1.3
            $orgWeights[$orgId] = $variation;
        }
        
        // Normaliser les poids
        $totalWeight = array_sum($orgWeights);
        foreach ($orgWeights as $orgId => $weight) {
            $orgWeights[$orgId] = $weight / $totalWeight;
        }
        
        // Réassigner les contrats selon les poids
        $contractSql = "
            WITH contrats_numbered AS (
                SELECT id, ROW_NUMBER() OVER (ORDER BY id) as rn
                FROM t_organisation_employee_contrat
            ),
            org_assignments AS (
                SELECT 
                    cn.id,
                    CASE 
                        WHEN cn.rn <= " . intval($orgWeights[$organizations[0]] * $totalContrats) . " THEN {$organizations[0]}
                        " . implode(' ', array_map(function($index) use ($organizations, $orgWeights, $totalContrats) {
                            if ($index === 0) return '';
                            $cumulative = 0;
                            for ($i = 0; $i < $index; $i++) {
                                $cumulative += $orgWeights[$organizations[$i]] * $totalContrats;
                            }
                            $current = $orgWeights[$organizations[$index]] * $totalContrats;
                            return "WHEN cn.rn <= " . intval($cumulative + $current) . " THEN {$organizations[$index]}";
                        }, range(1, count($organizations) - 1))) . "
                        ELSE {$organizations[count($organizations) - 1]}
                    END as new_org_id
                FROM contrats_numbered cn
            )
            UPDATE t_organisation_employee_contrat 
            SET organisation_id = oa.new_org_id
            FROM org_assignments oa
            WHERE t_organisation_employee_contrat.id = oa.id
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
        
        $io->text("Nouvelle distribution réaliste des organisations (top 15):");
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

        // Calculer les moyennes et écarts
        $placardStats = $connection->executeQuery("
            SELECT MIN(nombre_dossiers) as min, MAX(nombre_dossiers) as max, AVG(nombre_dossiers) as moyenne 
            FROM (SELECT COUNT(*) as nombre_dossiers FROM t_dossier GROUP BY placard_id) as stats
        ")->fetchAssociative();
        
        $orgStats = $connection->executeQuery("
            SELECT MIN(nombre_contrats) as min, MAX(nombre_contrats) as max, AVG(nombre_contrats) as moyenne 
            FROM (SELECT COUNT(*) as nombre_contrats FROM t_organisation_employee_contrat GROUP BY organisation_id) as stats
        ")->fetchAssociative();

        $io->text("Placards - Min: {$placardStats['min']}, Max: {$placardStats['max']}, Moyenne: " . round($placardStats['moyenne'], 2));
        $io->text("Organisations - Min: {$orgStats['min']}, Max: {$orgStats['max']}, Moyenne: " . round($orgStats['moyenne'], 2));

        $io->success("Rééquilibrage réaliste terminé avec succès !");
        return Command::SUCCESS;
    }
}

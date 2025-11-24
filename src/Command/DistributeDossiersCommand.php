<?php

namespace App\Command;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:distribute-dossiers',
    description: 'Vérifie et équilibre la distribution des dossiers selon l\'image de référence'
)]
class DistributeDossiersCommand extends Command
{
    // Tous les dossiers valides selon l'image Excel
    private const VALID_DOSSIERS = [
        // DAS-SIEGE (DSIG)
        'SFCZ', 'CCGA',
        // DAS-GESTION (DGST)
        'GACH', 'GCMP', 'GFIN', 'GRSH', 'GJUR', 'GBNQ', 'GPRG',
        // DAS-ACTIVITE SOCIALE (DASO)
        'SUMM', 'SASE', 'SPSI', 'SASI', 'SPSE',
        // DAS-ENSEIGNEMENT & FORMATION (DENS)
        'EUIA', 'ECSM', 'IFCP', 'ECFC', 'ECRI', 'ELEZ', 'EEAS',
        // DAS-SOINS (DSOI)
        'SHCZ', 'SLMG', 'SDNT', 'SHMK', 'SHMB', 'SHMY', 'SLPD', 'SRAD', 'SLAB', 
        'SAHR', 'SCOV', 'SOPH', 'SKIN', 'SCVP', 'SGHJ', 'SGYN', 'SURG', 'SHMS', 'SHCT', 'CSDA',
        // DAS-HOTELLERIE-RESTAURATION (DRST)
        'RRUR', 'RHUR', 'RHAR', 'RRER', 'RRHK', 'RRHY', 'RDAR', 'RRHR', 'RHSR', 'RCER', 'RBPR', 'RHCC',
        // DAS-INFORMATIQUE ET NUMERIQUE (DNUM)
        'NSIT', 'NARC', 'NITS', 'NNUM',
        // DAS-INGENIERIE & TRAVAUX (DING)
        'IMIN', 'IEIS', 'ICCI', 'IESV', 'ISAE', 'ISAM', 'IPTT', 'ISAA',
        // DAS-LOGISTIQUE & APPROVISIONNEMENT (DAPR)
        'APCC', 'APVR', 'APUR', 'APLM', 'APCH', 'LPDP',
        // DAS-PHARMACEUTIQUE (DPHR)
        'PVPH', 'PPPH', 'PAMD',
        // DAS-PRESTATIONS EXTERNALISEES & ACTIVITES DE PRODUCTION (DPRD)
        'PIMP', 'PPTM',
        // DAS-RECHERCHE & EXPERTISE (DEXP)
        'ECBE', 'ECRG', 'EEDF',
        // DAS-SERVICES DE PROXIMITE (DSPR)
        'PTEX', 'PBIR', 'PLAV', 'PCAP', 'PEPC', 'PCOF', 'PEVN',
        // EPHR (AFRICMED)
        'PGRO',
        // EPRD (SA2S-METIERS)
        'PSMS'
    ];

    public function __construct(
        private EntityManagerInterface $entityManager
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $conn = $this->entityManager->getConnection();

        $io->title('Vérification et distribution équitable des dossiers');

        // 1. Compter les organisations totales
        $sql = "SELECT COUNT(*) as total FROM p_organisation WHERE dossier IS NOT NULL AND dossier != ''";
        $stmt = $conn->prepare($sql);
        $result = $stmt->executeQuery();
        $totalOrgs = (int)$result->fetchOne();

        $io->section('Statistiques actuelles');
        $io->text("Total d'organisations : $totalOrgs");
        $io->text("Total de dossiers de référence : " . count(self::VALID_DOSSIERS));

        // 2. Vérifier quels dossiers sont présents dans la base
        $sql = "SELECT DISTINCT dossier FROM p_organisation WHERE dossier IS NOT NULL AND dossier != '' ORDER BY dossier";
        $stmt = $conn->prepare($sql);
        $result = $stmt->executeQuery();
        $presentDossiers = array_column($result->fetchAllAssociative(), 'dossier');
        
        $missingDossiers = array_diff(self::VALID_DOSSIERS, $presentDossiers);
        $extraDossiers = array_diff($presentDossiers, self::VALID_DOSSIERS);

        if (!empty($missingDossiers)) {
            $io->warning('Dossiers manquants (' . count($missingDossiers) . ') : ' . implode(', ', $missingDossiers));
        } else {
            $io->success('Tous les dossiers de référence sont présents');
        }

        if (!empty($extraDossiers)) {
            $io->warning('Dossiers supplémentaires non référencés (' . count($extraDossiers) . ') : ' . implode(', ', $extraDossiers));
        }

        // 3. Afficher la distribution actuelle
        $io->section('Distribution actuelle');
        $sql = "SELECT dossier, COUNT(*) as count FROM p_organisation WHERE dossier IS NOT NULL AND dossier != '' GROUP BY dossier ORDER BY count DESC, dossier";
        $stmt = $conn->prepare($sql);
        $result = $stmt->executeQuery();
        $distribution = $result->fetchAllAssociative();
        
        $io->table(['Dossier', 'Nombre d\'organisations'], $distribution);

        // 4. Calculer la distribution équitable
        $io->section('Distribution équitable');
        $targetPerDossier = (int)floor($totalOrgs / count(self::VALID_DOSSIERS));
        $remainder = $totalOrgs % count(self::VALID_DOSSIERS);
        
        $io->text("Organisations par dossier (base) : $targetPerDossier");
        $io->text("Dossiers avec une organisation supplémentaire : $remainder");

        // 5. Proposer la redistribution
        $io->section('Redistribution proposée');
        
        // Obtenir toutes les organisations avec leur dossier actuel
        $sql = "SELECT id, dossier FROM p_organisation WHERE dossier IS NOT NULL AND dossier != '' ORDER BY id";
        $stmt = $conn->prepare($sql);
        $result = $stmt->executeQuery();
        $organisations = $result->fetchAllAssociative();

        // Créer un tableau de distribution cible
        $targetDistribution = [];
        foreach (self::VALID_DOSSIERS as $dossier) {
            $targetDistribution[$dossier] = $targetPerDossier;
        }
        
        // Ajouter les organisations supplémentaires aux premiers dossiers
        $dossierIndex = 0;
        for ($i = 0; $i < $remainder; $i++) {
            $dossier = self::VALID_DOSSIERS[$dossierIndex];
            $targetDistribution[$dossier]++;
            $dossierIndex = ($dossierIndex + 1) % count(self::VALID_DOSSIERS);
        }

        // Afficher la distribution cible
        $targetTable = [];
        foreach ($targetDistribution as $dossier => $count) {
            $targetTable[] = [$dossier, $count];
        }
        $io->table(['Dossier', 'Nombre cible'], $targetTable);

        // 6. Effectuer la redistribution
        if ($io->confirm('Voulez-vous effectuer la redistribution maintenant ?', false)) {
            $io->section('Redistribution en cours...');
            
            // Réorganiser les organisations pour une distribution équitable
            $orgIndex = 0;
            $dossierIndex = 0;
            $dossierCounts = array_fill_keys(self::VALID_DOSSIERS, 0);
            
            foreach ($organisations as $org) {
                // Trouver le prochain dossier qui n'a pas atteint sa cible
                while ($dossierCounts[self::VALID_DOSSIERS[$dossierIndex]] >= $targetDistribution[self::VALID_DOSSIERS[$dossierIndex]]) {
                    $dossierIndex = ($dossierIndex + 1) % count(self::VALID_DOSSIERS);
                }
                
                $newDossier = self::VALID_DOSSIERS[$dossierIndex];
                
                if ($org['dossier'] !== $newDossier) {
                    $sql = "UPDATE p_organisation SET dossier = :new_dossier WHERE id = :id";
                    $stmt = $conn->prepare($sql);
                    $stmt->executeStatement([
                        'new_dossier' => $newDossier,
                        'id' => $org['id']
                    ]);
                }
                
                $dossierCounts[$newDossier]++;
                $dossierIndex = ($dossierIndex + 1) % count(self::VALID_DOSSIERS);
            }

            $io->success('Redistribution terminée !');

            // Afficher la nouvelle distribution
            $io->section('Nouvelle distribution');
            $sql = "SELECT dossier, COUNT(*) as count FROM p_organisation WHERE dossier IS NOT NULL AND dossier != '' GROUP BY dossier ORDER BY count DESC, dossier";
            $stmt = $conn->prepare($sql);
            $result = $stmt->executeQuery();
            $newDistribution = $result->fetchAllAssociative();
            $io->table(['Dossier', 'Nombre d\'organisations'], $newDistribution);
        } else {
            $io->note('Redistribution annulée');
        }

        return Command::SUCCESS;
    }
}


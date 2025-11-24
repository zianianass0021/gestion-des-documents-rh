<?php

namespace App\Command;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:sync-organisation-data',
    description: 'Synchronise les données d\'organisation avec les valeurs de référence (groupements, DAS, dossiers)'
)]
class SyncOrganisationDataCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $conn = $this->entityManager->getConnection();

        // Valeurs valides selon l'image fournie (référence Excel)
        $validGroupements = ['FCZ', 'RGA', 'SSS', 'SST'];
        
        $validDAS = [
            'DSIG',  // DAS-SIEGE
            'DGST',  // DAS-GESTION
            'DASO',  // DAS-ACTIVITE SOCIALE
            'DENS',  // DAS-ENSEIGNEMENT & FORMATION
            'DSOI',  // DAS-SOINS
            'DRST',  // DAS-HOTELLERIE-RESTAURATION
            'DNUM',  // DAS-INFORMATIQUE ET NUMERIQUE
            'DING',  // DAS-INGENIERIE & TRAVAUX
            'DAPR',  // DAS-LOGISTIQUE & APPROVISIONNEMENT
            'DPHR',  // DAS-PHARMACEUTIQUE
            'DPRD',  // DAS-PRESTATIONS EXTERNALISEES & ACTIVITES DE PRODUCTION
            'DEXP',  // DAS-RECHERCHE & EXPERTISE
            'DSPR',  // DAS-SERVICES DE PROXIMITE
            'EPHR',  // AFRICMED-COMMERCIALISATION PHARMACEUTIQUE
            'EPRD',  // SA2S-METIERS ET SERVICES
            'EING'   // SA2S-INGENIERIE & TRAVAUX
        ];
        
        // Dossiers valides selon l'image (tous les codes de dossier visibles)
        $validDossiers = [
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

        $io->title('Synchronisation des données d\'organisation');

        // 1. Remplacer GRP001 par FCZ (le groupement le plus utilisé)
        $io->section('Remplacement de GRP001 par FCZ');
        $sql = "UPDATE p_organisation SET groupement = 'FCZ' WHERE groupement = 'GRP001'";
        $stmt = $conn->prepare($sql);
        $result = $stmt->executeStatement();
        $io->success("$result organisation(s) mise(s) à jour : GRP001 → FCZ");

        // 2. Remplacer DAS001 par DSIG (DAS-SIEGE, le premier dans l'image)
        $io->section('Remplacement de DAS001 par DSIG');
        $sql = "UPDATE p_organisation SET das = 'DSIG' WHERE das = 'DAS001'";
        $stmt = $conn->prepare($sql);
        $result = $stmt->executeStatement();
        $io->success("$result organisation(s) mise(s) à jour : DAS001 → DSIG");

        // 3. Vérifier les groupements invalides
        $io->section('Vérification des groupements');
        $sql = "SELECT DISTINCT groupement FROM p_organisation WHERE groupement IS NOT NULL AND groupement != '' AND groupement NOT IN ('" . implode("','", $validGroupements) . "')";
        $stmt = $conn->prepare($sql);
        $result = $stmt->executeQuery();
        $invalidGroupements = $result->fetchAllAssociative();
        
        if (!empty($invalidGroupements)) {
            $io->warning('Groupements invalides trouvés : ' . implode(', ', array_column($invalidGroupements, 'groupement')));
            foreach ($invalidGroupements as $invalid) {
                $grp = $invalid['groupement'];
                $sql = "UPDATE p_organisation SET groupement = 'FCZ' WHERE groupement = :grp";
                $stmt = $conn->prepare($sql);
                $stmt->executeStatement(['grp' => $grp]);
                $io->info("$grp → FCZ");
            }
        } else {
            $io->success('Tous les groupements sont valides');
        }

        // 4. Vérifier les DAS invalides
        $io->section('Vérification des DAS');
        $sql = "SELECT DISTINCT das FROM p_organisation WHERE das IS NOT NULL AND das != '' AND das NOT IN ('" . implode("','", $validDAS) . "')";
        $stmt = $conn->prepare($sql);
        $result = $stmt->executeQuery();
        $invalidDAS = $result->fetchAllAssociative();
        
        if (!empty($invalidDAS)) {
            $io->warning('DAS invalides trouvés : ' . implode(', ', array_column($invalidDAS, 'das')));
            foreach ($invalidDAS as $invalid) {
                $das = $invalid['das'];
                $sql = "UPDATE p_organisation SET das = 'DSIG' WHERE das = :das";
                $stmt = $conn->prepare($sql);
                $stmt->executeStatement(['das' => $das]);
                $io->info("$das → DSIG");
            }
        } else {
            $io->success('Tous les DAS sont valides');
        }

        // 5. Vérifier les dossiers invalides
        $io->section('Vérification des dossiers');
        $sql = "SELECT DISTINCT dossier FROM p_organisation WHERE dossier IS NOT NULL AND dossier != '' AND dossier NOT IN ('" . implode("','", $validDossiers) . "')";
        $stmt = $conn->prepare($sql);
        $result = $stmt->executeQuery();
        $invalidDossiers = $result->fetchAllAssociative();
        
        if (!empty($invalidDossiers)) {
            $io->warning('Dossiers invalides trouvés : ' . implode(', ', array_column($invalidDossiers, 'dossier')));
            // Remplacer par le premier dossier valide (SFCZ)
            foreach ($invalidDossiers as $invalid) {
                $dossier = $invalid['dossier'];
                $sql = "UPDATE p_organisation SET dossier = 'SFCZ' WHERE dossier = :dossier";
                $stmt = $conn->prepare($sql);
                $stmt->executeStatement(['dossier' => $dossier]);
                $io->info("$dossier → SFCZ");
            }
        } else {
            $io->success('Tous les dossiers sont valides');
        }

        // 6. Statistiques finales
        $io->section('Statistiques finales');
        
        $sql = "SELECT groupement, COUNT(*) as count FROM p_organisation WHERE groupement IS NOT NULL AND groupement != '' GROUP BY groupement ORDER BY groupement";
        $stmt = $conn->prepare($sql);
        $result = $stmt->executeQuery();
        $groupements = $result->fetchAllAssociative();
        $io->table(['Groupement', 'Nombre'], $groupements);

        $sql = "SELECT das, COUNT(*) as count FROM p_organisation WHERE das IS NOT NULL AND das != '' GROUP BY das ORDER BY das";
        $stmt = $conn->prepare($sql);
        $result = $stmt->executeQuery();
        $das = $result->fetchAllAssociative();
        $io->table(['DAS', 'Nombre'], $das);

        // Vérifier que tous les groupements de l'image sont présents
        $io->section('Vérification de la présence de tous les groupements de référence');
        $sql = "SELECT DISTINCT groupement FROM p_organisation WHERE groupement IN ('" . implode("','", $validGroupements) . "')";
        $stmt = $conn->prepare($sql);
        $result = $stmt->executeQuery();
        $presentGroupements = array_column($result->fetchAllAssociative(), 'groupement');
        $missingGroupements = array_diff($validGroupements, $presentGroupements);
        if (!empty($missingGroupements)) {
            $io->warning('Groupements manquants : ' . implode(', ', $missingGroupements));
        } else {
            $io->success('Tous les groupements de référence sont présents');
        }

        // Vérifier que tous les DAS de l'image sont présents
        $io->section('Vérification de la présence de tous les DAS de référence');
        $sql = "SELECT DISTINCT das FROM p_organisation WHERE das IN ('" . implode("','", $validDAS) . "')";
        $stmt = $conn->prepare($sql);
        $result = $stmt->executeQuery();
        $presentDAS = array_column($result->fetchAllAssociative(), 'das');
        $missingDAS = array_diff($validDAS, $presentDAS);
        if (!empty($missingDAS)) {
            $io->warning('DAS manquants : ' . implode(', ', $missingDAS));
        } else {
            $io->success('Tous les DAS de référence sont présents');
        }

        // Vérifier que tous les dossiers de l'image sont présents
        $io->section('Vérification de la présence de tous les dossiers de référence');
        $sql = "SELECT DISTINCT dossier FROM p_organisation WHERE dossier IN ('" . implode("','", $validDossiers) . "')";
        $stmt = $conn->prepare($sql);
        $result = $stmt->executeQuery();
        $presentDossiers = array_column($result->fetchAllAssociative(), 'dossier');
        $missingDossiers = array_diff($validDossiers, $presentDossiers);
        if (!empty($missingDossiers)) {
            $io->warning('Dossiers manquants (' . count($missingDossiers) . ') : ' . implode(', ', array_slice($missingDossiers, 0, 10)) . (count($missingDossiers) > 10 ? '...' : ''));
            $io->note('Les dossiers manquants devront être créés manuellement dans les organisations si nécessaire.');
        } else {
            $io->success('Tous les dossiers de référence sont présents');
        }

        $io->success('Synchronisation terminée !');

        return Command::SUCCESS;
    }
}


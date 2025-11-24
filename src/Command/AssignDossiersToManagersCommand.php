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
    name: 'app:assign-dossiers-to-managers',
    description: 'Assigner aléatoirement des dossiers aux managers existants',
)]
class AssignDossiersToManagersCommand extends Command
{
    // Liste des dossiers valides (niveau 3 de contrôle)
    private const VALID_DOSSIERS = [
        'SFCZ', 'CCGA', 'GACH', 'GCMP', 'GFIN', 'GRSH', 'GJUR', 'GBNQ', 'GPRG', 'SUMM',
        'SASE', 'SPSI', 'SASI', 'SPSE', 'EUIA', 'ECSM', 'IFCP', 'ECFC', 'ECRI', 'ELEZ',
        'EEAS', 'SHCZ', 'SLMG', 'SDNT', 'SHMK', 'SHMB', 'SHMY', 'SLPD', 'SRAD', 'SLAB',
        'SAHR', 'SCOV', 'SOPH', 'SKIN', 'SCVP', 'SGHJ', 'SGYN', 'SURG', 'SHMS', 'SHCT',
        'CSDA', 'RRUR', 'RHUR', 'RHAR', 'RRER', 'RRHK', 'RRHY', 'RDAR', 'RRHR', 'RHSR',
        'RCER', 'RBPR', 'RHCC', 'NSIT', 'NARC', 'NITS', 'NNUM', 'IMIN', 'IEIS', 'ICCI',
        'IESV', 'ISAE', 'ISAM', 'IPTT', 'ISAA', 'APCC', 'APVR', 'APUR', 'APLM', 'APCH',
        'LPDP', 'PVPH', 'PPPH', 'PAMD', 'PIMP', 'PPTM', 'ECBE', 'ECRG', 'EEDF', 'PTEX',
        'PBIR', 'PLAV', 'PCAP', 'PEPC', 'PCOF', 'PEVN', 'PGRO', 'PSMS'
    ];

    public function __construct(
        private EntityManagerInterface $entityManager
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // Récupérer tous les managers (employés avec ROLE_MANAGER)
        $conn = $this->entityManager->getConnection();
        $sql = "SELECT id, nom, prenom, dossier_gere FROM t_user WHERE CAST(roles AS TEXT) LIKE '%ROLE_MANAGER%'";
        $stmt = $conn->prepare($sql);
        $result = $stmt->executeQuery();
        $managers = $result->fetchAllAssociative();

        if (empty($managers)) {
            $io->warning('Aucun manager trouvé dans la base de données.');
            return Command::FAILURE;
        }

        $io->info(sprintf('Nombre de managers trouvés : %d', count($managers)));

        // Récupérer les dossiers déjà assignés
        $sqlAssigned = "SELECT DISTINCT dossier_gere FROM t_user WHERE dossier_gere IS NOT NULL";
        $stmtAssigned = $conn->prepare($sqlAssigned);
        $resultAssigned = $stmtAssigned->executeQuery();
        $assignedDossiers = array_column($resultAssigned->fetchAllAssociative(), 'dossier_gere');

        // Filtrer les dossiers disponibles (non assignés)
        $availableDossiers = array_diff(self::VALID_DOSSIERS, $assignedDossiers);

        if (count($availableDossiers) < count($managers)) {
            $io->error(sprintf(
                'Pas assez de dossiers disponibles. Disponibles : %d, Managers : %d',
                count($availableDossiers),
                count($managers)
            ));
            return Command::FAILURE;
        }

        // Mélanger les dossiers disponibles pour un choix aléatoire
        $availableDossiers = array_values($availableDossiers);
        shuffle($availableDossiers);

        $io->section('Assignation des dossiers :');

        // Assigner un dossier à chaque manager
        foreach ($managers as $index => $manager) {
            $managerId = $manager['id'];
            $managerName = $manager['nom'] . ' ' . $manager['prenom'];
            $currentDossier = $manager['dossier_gere'];

            // Si le manager a déjà un dossier, on le garde
            if ($currentDossier) {
                $io->note(sprintf(
                    'Manager %s (ID: %d) a déjà le dossier %s - conservé',
                    $managerName,
                    $managerId,
                    $currentDossier
                ));
                continue;
            }

            // Assigner un nouveau dossier
            $assignedDossier = $availableDossiers[$index];
            
            $updateSql = "UPDATE t_user SET dossier_gere = :dossier WHERE id = :id";
            $updateStmt = $conn->prepare($updateSql);
            $updateStmt->executeQuery([
                'dossier' => $assignedDossier,
                'id' => $managerId
            ]);

            $io->success(sprintf(
                'Manager %s (ID: %d) → Dossier %s',
                $managerName,
                $managerId,
                $assignedDossier
            ));
        }

        $io->success('Assignation terminée avec succès !');

        return Command::SUCCESS;
    }
}


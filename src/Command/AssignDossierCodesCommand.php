<?php

namespace App\Command;

use App\Entity\Dossier;
use App\Entity\Employe;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:assign-dossier-codes',
    description: 'Assign dossier codes (3rd level of control) to all existing dossiers based on employee organization',
)]
class AssignDossierCodesCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Show what would be done without making changes');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = $input->getOption('dry-run');

        $io->title('Assignation des codes dossier aux dossiers existants');

        $conn = $this->entityManager->getConnection();

        // Récupérer tous les dossiers sans code dossier
        $sql = 'SELECT d.id, d.employe_id 
                FROM t_dossier d
                WHERE d.dossier_code IS NULL
                ORDER BY d.id';
        
        $dossiers = $conn->fetchAllAssociative($sql);
        
        if (empty($dossiers)) {
            $io->success('Tous les dossiers ont déjà un code dossier assigné.');
            return Command::SUCCESS;
        }

        $io->info(sprintf('Trouvé %d dossier(s) sans code dossier.', count($dossiers)));

        if ($dryRun) {
            $io->note('Mode dry-run activé - aucune modification ne sera effectuée');
        }

        $assigned = 0;
        $failed = 0;
        $stats = [];

        foreach ($dossiers as $dossierData) {
            $dossierId = $dossierData['id'];
            $employeId = $dossierData['employe_id'];

            // Récupérer le code dossier de l'organisation de l'employé (via son contrat actif)
            $sql = 'SELECT o.dossier
                    FROM p_organisation o
                    INNER JOIN t_organisation_employee_contrat oec ON o.id = oec.organisation_id
                    INNER JOIN t_employee_contrat ec ON oec.employee_contrat_id = ec.id
                    WHERE ec.employe_id = :employe_id
                    AND ec.statut = :statut
                    LIMIT 1';
            
            $stmt = $conn->prepare($sql);
            $result = $stmt->executeQuery([
                'employe_id' => $employeId,
                'statut' => 'actif'
            ]);

            $dossierCode = $result->fetchOne();

            if ($dossierCode) {
                if (!$dryRun) {
                    $updateSql = 'UPDATE t_dossier SET dossier_code = :dossier_code WHERE id = :id';
                    $conn->executeStatement($updateSql, [
                        'dossier_code' => $dossierCode,
                        'id' => $dossierId
                    ]);
                }
                
                $assigned++;
                $stats[$dossierCode] = ($stats[$dossierCode] ?? 0) + 1;
                
                $io->writeln(sprintf('  ✓ Dossier #%d -> %s', $dossierId, $dossierCode));
            } else {
                $failed++;
                $io->warning(sprintf('  ✗ Dossier #%d (Employé #%d): Aucun contrat actif avec organisation trouvé', $dossierId, $employeId));
            }
        }

        $io->newLine();
        $io->section('Résumé');
        $io->table(['Statut', 'Nombre'], [
            ['Assignés', $assigned],
            ['Échoués', $failed],
            ['Total', count($dossiers)],
        ]);

        if (!empty($stats)) {
            $io->section('Distribution par code dossier');
            arsort($stats);
            $tableData = [];
            foreach ($stats as $code => $count) {
                $tableData[] = [$code, $count];
            }
            $io->table(['Code dossier', 'Nombre'], $tableData);
        }

        if ($dryRun) {
            $io->note('Pour appliquer ces changements, exécutez la commande sans --dry-run');
            return Command::SUCCESS;
        }

        if ($assigned > 0) {
            $io->success(sprintf('%d dossier(s) mis à jour avec succès!', $assigned));
        }

        if ($failed > 0) {
            $io->warning(sprintf('%d dossier(s) n\'ont pas pu être mis à jour (aucun contrat actif trouvé).', $failed));
        }

        return Command::SUCCESS;
    }
}


<?php

namespace App\Command;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:update-employee-names',
    description: 'Update employee names to use diverse first and last names',
)]
class UpdateEmployeeNamesCommand extends Command
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
        $io->title('Mise à jour des noms des employés');

        $connection = $this->entityManager->getConnection();

        // Arrays of diverse Moroccan names
        $prenoms = ['Ahmed', 'Mohamed', 'Hassan', 'Omar', 'Youssef', 'Karim', 'Rachid', 'Said', 'Ali', 'Mustapha',
                    'Fatima', 'Aicha', 'Khadija', 'Zineb', 'Sanae', 'Nadia', 'Samira', 'Latifa', 'Houda', 'Salma',
                    'Brahim', 'Chakib', 'Driss', 'Fouad', 'Ghassan', 'Hicham', 'Ibrahim', 'Jamal', 'Khalid', 'Lahcen',
                    'Meryem', 'Nabila', 'Rim', 'Souad', 'Wafa', 'Yasmine', 'Zahra', 'Amal', 'Bouchra', 'Chaimae'];
        
        $noms = ['Alaoui', 'Benali', 'Chraibi', 'Dakir', 'El Fassi', 'Gharbi', 'Hassani', 'Idrissi', 'Jabri', 'Kabbaj',
                 'Lahlou', 'Mansouri', 'Naciri', 'Ouafi', 'Rahmani', 'Saadi', 'Tazi', 'Zahiri', 'Amrani', 'Bennani',
                 'Cherkaoui', 'Daoudi', 'El Idrissi', 'El Mansouri', 'El Ouafi', 'El Yousfi', 'Fassi', 'Lazrak', 'Mekouar', 'Naji',
                 'Ouali', 'Qadiri', 'Rachidi', 'Sefrioui', 'Tahiri', 'Zeroual', 'Ait', 'Bouazza', 'Chakir', 'Dahbi'];
        
        // Create SQL arrays
        $prenomsArray = "ARRAY['" . implode("', '", $prenoms) . "']";
        $nomsArray = "ARRAY['" . implode("', '", $noms) . "']";
        $prenomsLength = count($prenoms);
        $nomsLength = count($noms);

        // Update employees that match the pattern "Hassan" + number or "Chraibi" + number
        $io->section('Mise à jour des noms des employés...');
        $sql = "UPDATE t_user 
                SET 
                    prenom = (" . $prenomsArray . ")[1 + floor(random() * " . $prenomsLength . ")::int],
                    nom = (" . $nomsArray . ")[1 + floor(random() * " . $nomsLength . ")::int],
                    email = LOWER((" . $prenomsArray . ")[1 + floor(random() * " . $prenomsLength . ")::int]) || '.' || 
                            LOWER((" . $nomsArray . ")[1 + floor(random() * " . $nomsLength . ")::int]) || id || '@uiass.ma',
                    username = LOWER((" . $prenomsArray . ")[1 + floor(random() * " . $prenomsLength . ")::int]) || '.' || 
                               LOWER((" . $nomsArray . ")[1 + floor(random() * " . $nomsLength . ")::int]) || id
                WHERE prenom LIKE 'Hassan%' OR nom LIKE 'Chraibi%' OR email LIKE 'hassan%@uiass.ma'";

        $result = $connection->executeStatement($sql);
        $io->text("Mis à jour {$result} employés");

        // Update dossier names to match the new employee names
        $io->section('Mise à jour des noms des dossiers...');
        $dossierSql = "UPDATE t_dossier 
                       SET 
                           nom = 'Dossier ' || e.prenom || ' ' || e.nom,
                           description = 'Dossier personnel de ' || e.prenom || ' ' || e.nom
                       FROM t_user e
                       WHERE t_dossier.employe_id = e.id
                       AND (t_dossier.nom LIKE 'Dossier Hassan%' OR t_dossier.nom LIKE 'Dossier %Chraibi%' OR t_dossier.description LIKE 'Dossier personnel de Hassan%')";
        
        $dossierResult = $connection->executeStatement($dossierSql);
        $io->text("Mis à jour {$dossierResult} dossiers");

        $io->success("Mise à jour terminée avec succès !");
        return Command::SUCCESS;
    }
}


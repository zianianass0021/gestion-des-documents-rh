<?php

namespace App\Command;

use App\Entity\Employe;
use App\Entity\EmployeeContrat;
use App\Entity\NatureContrat;
use App\Entity\Organisation;
use App\Entity\OrganisationEmployeeContrat;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:insert-test-employees',
    description: 'Insert 1000 test employees with Moroccan names and contract distribution',
)]
class InsertTestEmployeesCommand extends Command
{
    private EntityManagerInterface $entityManager;
    private UserPasswordHasherInterface $passwordHasher;

    public function __construct(EntityManagerInterface $entityManager, UserPasswordHasherInterface $passwordHasher)
    {
        $this->entityManager = $entityManager;
        $this->passwordHasher = $passwordHasher;
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Insertion de 1000 employés de test');

        // Vérifier si des employés existent déjà
        $existingEmployees = $this->entityManager->getRepository(Employe::class)->count([]);
        if ($existingEmployees > 0) {
            $io->warning("Il y a déjà {$existingEmployees} employés dans la base de données.");
            if (!$io->confirm('Voulez-vous continuer ?', false)) {
                return Command::FAILURE;
            }
        }

        // Créer les types de contrats s'ils n'existent pas
        $this->createContractTypes();
        
        // Créer les organisations s'elles n'existent pas
        $this->createOrganizations();

        // Noms marocains
        $moroccanFirstNames = [
            'Ahmed', 'Mohamed', 'Hassan', 'Omar', 'Youssef', 'Karim', 'Rachid', 'Said', 'Abdel', 'Mustapha',
            'Fatima', 'Aicha', 'Khadija', 'Zineb', 'Naima', 'Hakima', 'Samira', 'Latifa', 'Malika', 'Zakia',
            'Ibrahim', 'Abdellah', 'Abdelkader', 'Abdelaziz', 'Abdelmajid', 'Abdelhak', 'Abdelghani', 'Abdeljalil',
            'Amina', 'Khadija', 'Zineb', 'Naima', 'Hakima', 'Samira', 'Latifa', 'Malika', 'Zakia', 'Souad',
            'Laila', 'Nadia', 'Rachida', 'Saida', 'Khadija', 'Zineb', 'Naima', 'Hakima', 'Samira', 'Latifa'
        ];

        $moroccanLastNames = [
            'Alami', 'Benjelloun', 'Chraibi', 'Daoudi', 'El Fassi', 'El Idrissi', 'El Mansouri', 'El Ouafi',
            'El Yousfi', 'Fassi', 'Gharbi', 'Hassani', 'Idrissi', 'Jabri', 'Kabbaj', 'Lahlou', 'Mansouri',
            'Naciri', 'Ouafi', 'Rahmani', 'Saadi', 'Tazi', 'Yousfi', 'Zerouali', 'Bennani', 'Cherkaoui',
            'Dakir', 'El Amrani', 'El Fassi', 'El Idrissi', 'El Mansouri', 'El Ouafi', 'El Yousfi', 'Fassi',
            'Gharbi', 'Hassani', 'Idrissi', 'Jabri', 'Kabbaj', 'Lahlou', 'Mansouri', 'Naciri', 'Ouafi', 'Rahmani'
        ];

        $io->progressStart(1000);

        for ($i = 1; $i <= 1000; $i++) {
            // Générer un nom marocain
            $firstName = $moroccanFirstNames[array_rand($moroccanFirstNames)];
            $lastName = $moroccanLastNames[array_rand($moroccanLastNames)];
            
            // Créer l'employé
            $employee = new Employe();
            $employee->setNom($lastName);
            $employee->setPrenom($firstName);
            $employee->setEmail(strtolower($firstName . '.' . $lastName . $i . '@test.ma'));
            $employee->setUsername(strtolower($firstName . '.' . $lastName . $i));
            $employee->setTelephone('06' . str_pad(rand(10000000, 99999999), 8, '0', STR_PAD_LEFT));
            $employee->setRoles(['ROLE_EMPLOYEE']);
            $employee->setIsActive(true);
            
            // Hasher le mot de passe
            $hashedPassword = $this->passwordHasher->hashPassword($employee, 'password123');
            $employee->setPassword($hashedPassword);

            $this->entityManager->persist($employee);

            // Déterminer le nombre de contrats selon la distribution
            $contractCount = $this->getContractCount($i);
            
            // Créer les contrats
            for ($j = 1; $j <= $contractCount; $j++) {
                $contract = new EmployeeContrat();
                $contract->setEmploye($employee);
                $contract->setNatureContrat($this->getRandomContractType());
                $contract->setDateDebut(new \DateTime('2020-01-01'));
                $contract->setDateFin(new \DateTime('2025-12-31'));
                $contract->setSalaire((string)rand(5000, 15000));
                $contract->setStatut('actif');
                
                $this->entityManager->persist($contract);
                
                // Créer la relation avec l'organisation
                $orgContract = new OrganisationEmployeeContrat();
                $orgContract->setEmployeeContrat($contract);
                $orgContract->setOrganisation($this->getRandomOrganization());
                $orgContract->setDateDebut(new \DateTime('2020-01-01'));
                $orgContract->setDateFin(new \DateTime('2025-12-31'));
                
                $this->entityManager->persist($orgContract);
            }

            // Flush tous les 50 employés pour éviter les problèmes de mémoire
            if ($i % 50 === 0) {
                $this->entityManager->flush();
                $this->entityManager->clear();
            }

            $io->progressAdvance();
        }

        $this->entityManager->flush();
        $io->progressFinish();

        $io->success('1000 employés de test ont été insérés avec succès !');
        $io->note('Mot de passe pour tous les employés : password123');

        return Command::SUCCESS;
    }

    private function getContractCount(int $employeeIndex): int
    {
        // Distribution des contrats :
        // 14% avec 2 contrats
        // 4% avec 3 contrats  
        // 1% avec 4-5 contrats
        // 81% avec 1 contrat

        $random = rand(1, 100);
        
        if ($random <= 1) {
            // 1% avec 4-5 contrats
            return rand(4, 5);
        } elseif ($random <= 5) {
            // 4% avec 3 contrats
            return 3;
        } elseif ($random <= 19) {
            // 14% avec 2 contrats
            return 2;
        } else {
            // 81% avec 1 contrat
            return 1;
        }
    }

    private function createContractTypes(): void
    {
        $contractTypes = [
            'CDI' => 'Contrat à Durée Indéterminée',
            'CDD' => 'Contrat à Durée Déterminée',
            'Stage' => 'Contrat de Stage',
            'Freelance' => 'Contrat Freelance',
            'Consultant' => 'Contrat de Consultant'
        ];

        foreach ($contractTypes as $code => $designation) {
            $existing = $this->entityManager->getRepository(NatureContrat::class)->findOneBy(['code' => $code]);
            if (!$existing) {
                $contractType = new NatureContrat();
                $contractType->setCode($code);
                $contractType->setDesignation($designation);
                $this->entityManager->persist($contractType);
            }
        }
        $this->entityManager->flush();
    }

    private function createOrganizations(): void
    {
        $organizations = [
            'ACME' => 'ACME Corporation',
            'TECH' => 'Technologies du Maroc',
            'INNO' => 'Innovation Software',
            'DATA' => 'DataFlow Solutions',
            'CLOUD' => 'Cloud Technology'
        ];

        foreach ($organizations as $code => $designation) {
            $existing = $this->entityManager->getRepository(Organisation::class)->findOneBy(['code' => $code]);
            if (!$existing) {
                $organization = new Organisation();
                $organization->setCode($code);
                $organization->setDivisionActivitesStrategiques($designation);
                $organization->setDas('DAS001');
                $organization->setGroupement('GRP001');
                $organization->setDossier('DOS001');
                $organization->setDossierDesignation($designation);
                $this->entityManager->persist($organization);
            }
        }
        $this->entityManager->flush();
    }

    private function getRandomContractType(): NatureContrat
    {
        $contractTypes = $this->entityManager->getRepository(NatureContrat::class)->findAll();
        return $contractTypes[array_rand($contractTypes)];
    }

    private function getRandomOrganization(): Organisation
    {
        $organizations = $this->entityManager->getRepository(Organisation::class)->findAll();
        return $organizations[array_rand($organizations)];
    }
}

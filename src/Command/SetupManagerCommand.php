<?php

namespace App\Command;

use App\Entity\Employe;
use App\Entity\EmployeeContrat;
use App\Entity\Dossier;
use App\Entity\Document;
use App\Entity\OrganisationEmployeeContrat;
use App\Repository\EmployeRepository;
use App\Repository\NatureContratRepository;
use App\Repository\OrganisationRepository;
use App\Service\DocumentRequirementService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:setup-manager',
    description: 'Setup manager with contract, dossier, documents (~60% completion), and organization assignment',
)]
class SetupManagerCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private EmployeRepository $employeRepository,
        private NatureContratRepository $natureContratRepository,
        private OrganisationRepository $organisationRepository,
        private DocumentRequirementService $documentRequirementService
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // Find manager
        $manager = $this->employeRepository->findOneBy(['email' => 'manager@example.com']);
        
        if (!$manager) {
            $io->error('Manager not found! Please create the manager first using app:create-manager');
            return Command::FAILURE;
        }

        $io->title('Setting up manager: ' . $manager->getPrenom() . ' ' . $manager->getNom());

        // 1. Add ROLE_EMPLOYEE to manager if not already present
        $roles = $manager->getRoles();
        if (!in_array('ROLE_EMPLOYEE', $roles)) {
            $roles[] = 'ROLE_EMPLOYEE';
            $manager->setRoles($roles);
            $io->text('✓ Added ROLE_EMPLOYEE to manager');
        } else {
            $io->text('✓ Manager already has ROLE_EMPLOYEE');
        }

        // 2. Create a contract if manager doesn't have one
        if ($manager->getEmployeeContrats()->isEmpty()) {
            $natureContrats = $this->natureContratRepository->findAll();
            if (empty($natureContrats)) {
                $io->error('No contract types found! Please create contract types first.');
                return Command::FAILURE;
            }

            $randomNatureContrat = $natureContrats[array_rand($natureContrats)];
            
            $contrat = new EmployeeContrat();
            $contrat->setEmploye($manager);
            $contrat->setNatureContrat($randomNatureContrat);
            $contrat->setDateDebut(new \DateTime());
            $contrat->setDateFin(new \DateTime('+1 year'));
            $contrat->setSalaire(rand(15000, 30000));
            $contrat->setStatut('actif');
            
            $this->entityManager->persist($contrat);
            $io->text('✓ Created contract: ' . $randomNatureContrat->getDesignation());
        } else {
            $io->text('✓ Manager already has a contract');
            $contrat = $manager->getEmployeeContrats()->first();
        }

        // 3. Assign to organization
        $organisations = $this->organisationRepository->findAll();
        if (empty($organisations)) {
            $io->error('No organizations found! Please create organizations first.');
            return Command::FAILURE;
        }

        $randomOrganisation = $organisations[array_rand($organisations)];
        
        // Check if already assigned
        $existingOrgContrat = $this->entityManager->getRepository(OrganisationEmployeeContrat::class)
            ->findOneBy(['employeeContrat' => $contrat, 'organisation' => $randomOrganisation]);
        
        if (!$existingOrgContrat) {
            $orgContrat = new OrganisationEmployeeContrat();
            $orgContrat->setEmployeeContrat($contrat);
            $orgContrat->setOrganisation($randomOrganisation);
            $orgContrat->setDateDebut($contrat->getDateDebut());
            $orgContrat->setDateFin($contrat->getDateFin());
            
            $this->entityManager->persist($orgContrat);
            $io->text('✓ Assigned to organization: ' . $randomOrganisation->getDivisionActivitesStrategiques());
        } else {
            $io->text('✓ Already assigned to organization: ' . $randomOrganisation->getDivisionActivitesStrategiques());
        }

        // 4. Create dossier if doesn't exist
        $dossier = $manager->getDossier();
        if (!$dossier) {
            $dossier = new Dossier();
            $dossier->setEmploye($manager);
            $dossier->setNom('Dossier Personnel - ' . $manager->getPrenom() . ' ' . $manager->getNom());
            $dossier->setDescription('Dossier administratif et professionnel de ' . $manager->getPrenom() . ' ' . $manager->getNom());
            $dossier->setStatus('in_progress');
            $dossier->setCreatedAt(new \DateTime());
            
            $this->entityManager->persist($dossier);
            $io->text('✓ Created dossier');
        } else {
            $io->text('✓ Dossier already exists');
        }

        // 5. Get document requirements and add documents for ~60% completion
        $this->entityManager->flush(); // Flush to ensure contract is saved before getting requirements
        
        $documentRequirements = $this->documentRequirementService->getEmployeeDocumentRequirements($manager);
        
        // Helper function to get document full name
        $getDocumentFullName = function($abbreviation) {
            $mapping = [
                'CV' => 'Curriculum Vitae',
                'LET_MOT' => 'Lettre de Motivation',
                'COP_DIP' => 'Copie Diplôme',
                'COP_ID' => 'Copie Carte d\'Identité',
                'COP_RIB' => 'Copie RIB',
                'PHOTO' => 'Photo d\'Identité',
                'CERT_MED' => 'Certificat Médical',
                'CONV_STAGE' => 'Convention de Stage',
            ];
            return $mapping[$abbreviation] ?? $abbreviation;
        };
        
        if (empty($documentRequirements)) {
            $io->warning('No document requirements found. Creating sample documents...');
            // Create some default documents
            $defaultDocs = [
                ['abbreviation' => 'CV', 'required' => true],
                ['abbreviation' => 'LET_MOT', 'required' => true],
                ['abbreviation' => 'COP_DIP', 'required' => true],
                ['abbreviation' => 'COP_ID', 'required' => true],
                ['abbreviation' => 'COP_RIB', 'required' => true],
                ['abbreviation' => 'PHOTO', 'required' => false],
            ];
            
            $requiredDocs = array_filter($defaultDocs, fn($doc) => $doc['required']);
            $totalRequired = count($requiredDocs);
            $docsToAdd = (int) ceil($totalRequired * 0.6); // 60% of required docs
            
            $docsAdded = 0;
            foreach ($defaultDocs as $docData) {
                if ($docsAdded >= $docsToAdd) {
                    break;
                }
                
                // Check if document already exists
                $existingDoc = $dossier->getDocuments()->filter(
                    fn($doc) => $doc->getAbbreviation() === $docData['abbreviation']
                )->first();
                
                if (!$existingDoc) {
                    $document = new Document();
                    $document->setAbbreviation($docData['abbreviation']);
                    $document->setLibelleComplet($getDocumentFullName($docData['abbreviation']));
                    $document->setTypeDocument('Personnel');
                    $document->setUsage('Personnel');
                    $document->setDossier($dossier);
                    $document->setStatutAjout('ajoute');
                    $document->setStatutTelechargement('non_telecharge');
                    $document->setUploadedBy($manager->getPrenom() . ' ' . $manager->getNom());
                    
                    $this->entityManager->persist($document);
                    $docsAdded++;
                }
            }
            
            $io->text("✓ Added {$docsAdded} documents (~60% completion)");
        } else {
            // Get required documents
            $requiredDocs = array_filter($documentRequirements, fn($req) => $req['required'] ?? false);
            $totalRequired = count($requiredDocs);
            $docsToAdd = (int) ceil($totalRequired * 0.6); // 60% of required docs
            
            $docsAdded = 0;
            $existingDocs = $dossier->getDocuments()->toArray();
            $existingAbbreviations = array_map(fn($doc) => $doc->getAbbreviation(), $existingDocs);
            
            foreach ($requiredDocs as $req) {
                if ($docsAdded >= $docsToAdd) {
                    break;
                }
                
                $abbreviation = $req['abbreviation'] ?? '';
                if (empty($abbreviation) || in_array($abbreviation, $existingAbbreviations)) {
                    continue;
                }
                
                $document = new Document();
                $document->setAbbreviation($abbreviation);
                $document->setLibelleComplet($getDocumentFullName($abbreviation));
                $document->setTypeDocument('Personnel');
                $document->setUsage('Personnel');
                $document->setDossier($dossier);
                $document->setStatutAjout('ajoute');
                $document->setStatutTelechargement('non_telecharge');
                $document->setUploadedBy($manager->getPrenom() . ' ' . $manager->getNom());
                
                $this->entityManager->persist($document);
                $existingAbbreviations[] = $abbreviation; // Add to array to avoid duplicates
                $docsAdded++;
            }
            
            $io->text("✓ Added {$docsAdded} documents (~60% completion)");
        }

        // Flush all changes
        $this->entityManager->flush();

        $io->success('Manager setup completed successfully!');
        $io->note('Manager now has:');
        $io->listing([
            'ROLE_EMPLOYEE and ROLE_MANAGER roles',
            'A contract with ' . $contrat->getNatureContrat()->getDesignation(),
            'Assignment to organization: ' . $randomOrganisation->getDivisionActivitesStrategiques(),
            'A dossier with ~60% document completion',
        ]);

        return Command::SUCCESS;
    }
}


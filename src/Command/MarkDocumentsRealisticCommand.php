<?php

namespace App\Command;

use App\Repository\EmployeRepository;
use App\Service\DocumentRequirementService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:documents:mark-realistic',
    description: 'Mark random documents as "ajouté" to make data realistic'
)]
class MarkDocumentsRealisticCommand extends Command
{
    private EntityManagerInterface $entityManager;
    private EmployeRepository $employeRepository;
    private DocumentRequirementService $documentRequirementService;

    public function __construct(
        EntityManagerInterface $entityManager,
        EmployeRepository $employeRepository,
        DocumentRequirementService $documentRequirementService
    ) {
        parent::__construct();
        $this->entityManager = $entityManager;
        $this->employeRepository = $employeRepository;
        $this->documentRequirementService = $documentRequirementService;
    }

    /**
     * Get document full name from abbreviation
     */
    private function getDocumentFullName(string $abbreviation): string
    {
        $mapping = [
            'CIN' => 'Carte Nationale d\'Identité',
            'PASS' => 'Passeport',
            'CAE' => 'Carte d\'Accès Électronique',
            'ADN' => 'Acte de Naissance',
            'FORM' => 'Formulaire de Recrutement',
            'CTR' => 'Contrat de Travail',
            'DMED' => 'Déclaration Médicale',
            'BAC' => 'Bulletin d\'Admission au Concours',
            'DIP' => 'Diplôme',
            'CV' => 'Curriculum Vitae',
            'COP_DIP' => 'Copie Diplôme',
            'COP_ID' => 'Copie Carte d\'Identité',
            'COP_RIB' => 'Copie RIB',
            'RIB' => 'Relevé d\'Identité Bancaire',
            'FANT' => 'Fiche d\'Antécédents',
            'PHOTO' => 'Photo d\'Identité',
            'EMPR' => 'Attestation d\'Emploi',
            'AUTBIO' => 'Autobiographie',
            'AMAR' => 'Attestation de Mariage',
            'CINCONJ' => 'Carte d\'Identité du Conjoint',
            'ANENF' => 'Acte de Naissance des Enfants',
            'CINENF' => 'Carte d\'Identité des Enfants',
            'CINPAR' => 'Carte d\'Identité des Parents',
            'LET_MOT' => 'Lettre de Motivation',
            'CERT_MED' => 'Certificat Médical',
            'CONV_STAGE' => 'Convention de Stage',
            'BULLETIN_SALAIRE' => 'Bulletin de Salaire',
            'CERTIFICAT_TRAVAIL' => 'Certificat de Travail',
            'ATTESTATION_EMPLOI' => 'Attestation d\'Emploi'
        ];

        return $mapping[$abbreviation] ?? $abbreviation;
    }

    protected function configure(): void
    {
        $this->addOption(
            'obligatoire-percentage',
            null,
            InputOption::VALUE_OPTIONAL,
            'Percentage of obligatoire documents to mark as ajouté (default: 70)',
            70
        )
        ->addOption(
            'complementaire-percentage',
            null,
            InputOption::VALUE_OPTIONAL,
            'Percentage of complémentaire documents to mark as ajouté (default: 40)',
            40
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // Increase memory limit for large datasets
        ini_set('memory_limit', '2048M');
        set_time_limit(600);

        $io = new SymfonyStyle($input, $output);
        $io->title('Mark Documents as Realistic');

        $obligatoirePercentage = (int)$input->getOption('obligatoire-percentage');
        $complementairePercentage = (int)$input->getOption('complementaire-percentage');

        $io->note("Configuration:");
        $io->text("  - Obligatoires: {$obligatoirePercentage}% will be marked as 'ajouté'");
        $io->text("  - Complémentaires: {$complementairePercentage}% will be marked as 'ajouté'");

        // Get all employee IDs with dossiers first to avoid loading all entities at once
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('e.id')
           ->from('App\Entity\Employe', 'e')
           ->innerJoin('e.dossier', 'd')
           ->where('d.id IS NOT NULL');
        
        $employeeIds = array_column($qb->getQuery()->getScalarResult(), 'id');
        $totalEmployees = count($employeeIds);
        
        $employeesWithDossier = 0;
        $documentsCreated = 0;
        $documentsUpdated = 0;
        $obligatoireMarked = 0;
        $complementaireMarked = 0;
        $obligatoireTotal = 0;
        $complementaireTotal = 0;

        $io->progressStart($totalEmployees);

        $processed = 0;
        $batchSize = 20;
        
        foreach ($employeeIds as $employeeId) {
            $io->progressAdvance();

            try {
                // Reload employee after potential clear()
                $employee = $this->employeRepository->find($employeeId);
                if (!$employee || !$employee->getDossier()) {
                    continue;
                }

                $employeesWithDossier++;

                // Get required documents for this employee
                $requirements = $this->documentRequirementService->getEmployeeDocumentRequirements($employee);
                
                $dossier = $employee->getDossier();
                $existingDocuments = [];
                
                // Get existing documents by abbreviation
                foreach ($dossier->getDocuments() as $doc) {
                    $existingDocuments[$doc->getAbbreviation()] = $doc;
                }

                foreach ($requirements as $requirement) {
                    $abbreviation = $requirement['abbreviation'];
                    $isObligatoire = $requirement['required'] ?? true;
                    $existingDoc = $requirement['document'] ?? $existingDocuments[$abbreviation] ?? null;

                    // Determine if this document should be marked as "ajouté"
                    $percentage = $isObligatoire ? $obligatoirePercentage : $complementairePercentage;
                    $shouldMarkAsAdded = (mt_rand(1, 100) <= $percentage);

                    if ($isObligatoire) {
                        $obligatoireTotal++;
                    } else {
                        $complementaireTotal++;
                    }

                    if ($shouldMarkAsAdded) {
                        if ($existingDoc) {
                            // Update existing document
                            if ($existingDoc->getStatutAjout() !== 'ajoute') {
                                $existingDoc->setStatutAjout('ajoute');
                                $this->entityManager->persist($existingDoc);
                                $documentsUpdated++;
                                
                                if ($isObligatoire) {
                                    $obligatoireMarked++;
                                } else {
                                    $complementaireMarked++;
                                }
                            }
                        } else {
                            // Create new document with statutAjout='ajoute'
                            $document = new \App\Entity\Document();
                            $document->setAbbreviation($abbreviation);
                            
                            // Get full name from mapping
                            $libelleComplet = $this->getDocumentFullName($abbreviation);
                            $document->setLibelleComplet($libelleComplet);
                            $document->setTypeDocument('À définir');
                            $document->setUsage($isObligatoire ? 'Personnel' : 'Ayant Droits');
                            $document->setDossier($dossier);
                            $document->setStatutAjout('ajoute');
                            $document->setStatutTelechargement('non_telecharge');
                            
                            $dossier->addDocument($document);
                            $this->entityManager->persist($document);
                            $documentsCreated++;
                            
                            if ($isObligatoire) {
                                $obligatoireMarked++;
                            } else {
                                $complementaireMarked++;
                            }
                        }
                    }
                }

                // Flush and clear every batch to avoid memory issues
                $processed++;
                if ($processed % $batchSize === 0) {
                    $this->entityManager->flush();
                    $this->entityManager->clear();
                }
            } catch (\Exception $e) {
                $io->warning("Error processing employee ID {$employeeId}: " . $e->getMessage());
                continue;
            }
        }

        // Final flush
        $this->entityManager->flush();
        $io->progressFinish();

        // Summary
        $io->section('Summary');
        $io->table(
            ['Metric', 'Count'],
            [
                ['Total employees', $totalEmployees],
                ['Employees with dossier', $employeesWithDossier],
                ['Documents created', $documentsCreated],
                ['Documents updated', $documentsUpdated],
                ['Obligatoire total', $obligatoireTotal],
                ['Obligatoire marked as ajouté', $obligatoireMarked],
                ['Complémentaire total', $complementaireTotal],
                ['Complémentaire marked as ajouté', $complementaireMarked],
            ]
        );

        if ($obligatoireTotal > 0) {
            $obligatoireActualPercentage = round(($obligatoireMarked / $obligatoireTotal) * 100, 1);
            $io->text("Obligatoire actual percentage: {$obligatoireActualPercentage}%");
        }

        if ($complementaireTotal > 0) {
            $complementaireActualPercentage = round(($complementaireMarked / $complementaireTotal) * 100, 1);
            $io->text("Complémentaire actual percentage: {$complementaireActualPercentage}%");
        }

        $io->success("Successfully processed {$employeesWithDossier} employees!");
        $io->note("Created {$documentsCreated} documents and updated {$documentsUpdated} documents.");

        return Command::SUCCESS;
    }
}


<?php

namespace App\Service;

use App\Entity\Employe;
use App\Entity\Document;
use App\Repository\NatureContratTypeDocumentRepository;
use App\Repository\DocumentRepository;
use Doctrine\ORM\EntityManagerInterface;

class DocumentRequirementService
{
    private NatureContratTypeDocumentRepository $natureContratTypeDocumentRepository;
    private DocumentRepository $documentRepository;
    private EntityManagerInterface $entityManager;

    public function __construct(
        NatureContratTypeDocumentRepository $natureContratTypeDocumentRepository,
        DocumentRepository $documentRepository,
        EntityManagerInterface $entityManager
    ) {
        $this->natureContratTypeDocumentRepository = $natureContratTypeDocumentRepository;
        $this->documentRepository = $documentRepository;
        $this->entityManager = $entityManager;
    }

    /**
     * Get all required documents for an employee with their upload status
     */
    public function getEmployeeDocumentRequirements(Employe $employee): array
    {
        // Get all required documents from employee's contracts
        $requiredDocs = $employee->getAllRequiredDocuments($this->natureContratTypeDocumentRepository);
        
        // If no requirements found, create sample requirements based on active contracts
        if (empty($requiredDocs)) {
            $requiredDocs = $this->getSampleDocumentRequirements($employee);
        }
        
        // Get existing documents for this employee
        $existingDocuments = $this->getExistingDocumentsForEmployee($employee);
        
        $result = [];
        
        foreach ($requiredDocs as $requiredDoc) {
            $abbreviation = $requiredDoc['abbreviation'];
            
            // Find if document already exists
            $existingDoc = $this->findDocumentByAbbreviation($existingDocuments, $abbreviation);
            
            $isUploaded = $existingDoc ? $existingDoc->isUploaded() : false;
            
            $result[] = [
                'abbreviation' => $abbreviation,
                'required' => $requiredDoc['required'],
                'contractType' => $requiredDoc['contractType'],
                'uploaded' => $isUploaded,
                'document' => $existingDoc,
                'status' => $existingDoc ? $existingDoc->getUploadStatus() : 'pending'
            ];
        }
        
        return $result;
    }

    /**
     * Get sample document requirements when no data exists in database
     */
    private function getSampleDocumentRequirements(Employe $employee): array
    {
        $sampleRequirements = [];
        $activeContracts = $employee->getActiveContracts();
        
        // Default document requirements
        $defaultDocs = [
            'CV' => ['name' => 'Curriculum Vitae', 'required' => true],
            'LET_MOT' => ['name' => 'Lettre de Motivation', 'required' => true],
            'COP_DIP' => ['name' => 'Copie Diplôme', 'required' => true],
            'COP_ID' => ['name' => 'Copie Carte d\'Identité', 'required' => true],
            'COP_RIB' => ['name' => 'Copie RIB', 'required' => true],
            'PHOTO' => ['name' => 'Photo d\'Identité', 'required' => false],
        ];
        
        foreach ($activeContracts as $contract) {
            $contractType = $contract->getNatureContrat()->getDesignation();
            
            foreach ($defaultDocs as $abbreviation => $docInfo) {
                $sampleRequirements[] = [
                    'abbreviation' => $abbreviation,
                    'required' => $docInfo['required'],
                    'contractType' => $contractType
                ];
            }
            
            // Add contract-specific documents
            if (strpos($contractType, 'CDI') !== false) {
                $sampleRequirements[] = [
                    'abbreviation' => 'CERT_MED',
                    'required' => true,
                    'contractType' => $contractType
                ];
            } elseif (strpos($contractType, 'Stagiaire') !== false) {
                $sampleRequirements[] = [
                    'abbreviation' => 'CONV_STAGE',
                    'required' => true,
                    'contractType' => $contractType
                ];
            }
        }
        
        // Remove duplicates
        $uniqueRequirements = [];
        foreach ($sampleRequirements as $req) {
            $key = $req['abbreviation'];
            if (!isset($uniqueRequirements[$key]) || $req['required']) {
                $uniqueRequirements[$key] = $req;
            }
        }
        
        return array_values($uniqueRequirements);
    }

    /**
     * Get existing documents for an employee
     */
    private function getExistingDocumentsForEmployee(Employe $employee): array
    {
        $documents = [];
        
        if ($employee->getDossier()) {
            foreach ($employee->getDossier()->getDocuments() as $document) {
                $documents[] = $document;
            }
        }
        
        return $documents;
    }

    /**
     * Find document by abbreviation
     */
    private function findDocumentByAbbreviation(array $documents, string $abbreviation): ?Document
    {
        foreach ($documents as $document) {
            if ($document->getAbbreviation() === $abbreviation) {
                return $document;
            }
        }
        
        return null;
    }

    /**
     * Create a new document for an employee if it doesn't exist
     */
    public function createDocumentIfNotExists(Employe $employee, string $abbreviation, string $libelleComplet, string $typeDocument, string $usage, \App\Entity\Dossier $dossier = null): ?Document
    {
        // Check if document already exists
        $existingDocuments = $this->getExistingDocumentsForEmployee($employee);
        $existingDoc = $this->findDocumentByAbbreviation($existingDocuments, $abbreviation);
        
        if ($existingDoc) {
            return $existingDoc;
        }
        
        // Create new document
        $document = new Document();
        $document->setAbbreviation($abbreviation);
        $document->setLibelleComplet($libelleComplet);
        $document->setTypeDocument($typeDocument);
        $document->setUsage($usage);
        
        // Use provided dossier or add to employee's dossier or create a default one
        if ($dossier) {
            $document->setDossier($dossier);
            $dossier->addDocument($document);
        } else {
            $employeeDossier = $employee->getDossier();
            if (!$employeeDossier) {
                // Create a default dossier
                $dossier = new \App\Entity\Dossier();
                $dossier->setNom('Documents par défaut');
                $dossier->setDescription('Dossier créé automatiquement pour les documents requis');
                $dossier->setEmploye($employee);
                $this->entityManager->persist($dossier);
                $employee->setDossier($dossier);
                $employeeDossier = $dossier;
            }
            
            $document->setDossier($employeeDossier);
            $employeeDossier->addDocument($document);
        }
        
        $this->entityManager->persist($document);
        $this->entityManager->flush();
        
        return $document;
    }

    /**
     * Get completion percentage for employee documents
     */
    public function getCompletionPercentage(Employe $employee): int
    {
        $requirements = $this->getEmployeeDocumentRequirements($employee);
        
        if (empty($requirements)) {
            return 100;
        }
        
        $uploaded = 0;
        foreach ($requirements as $requirement) {
            if ($requirement['uploaded']) {
                $uploaded++;
            }
        }
        
        return round(($uploaded / count($requirements)) * 100);
    }
}

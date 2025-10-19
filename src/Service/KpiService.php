<?php

namespace App\Service;

use App\Repository\NatureContratRepository;
use App\Repository\OrganisationRepository;
use App\Repository\EmployeRepository;
use App\Repository\DossierRepository;
use App\Repository\DocumentRepository;
use App\Repository\NatureContratTypeDocumentRepository;
use App\Repository\EmployeeContratRepository;
use Doctrine\ORM\EntityManagerInterface;

class KpiService
{
    private EntityManagerInterface $entityManager;
    private NatureContratRepository $natureContratRepository;
    private OrganisationRepository $organisationRepository;
    private EmployeRepository $employeRepository;
    private DossierRepository $dossierRepository;
    private DocumentRepository $documentRepository;
    private NatureContratTypeDocumentRepository $natureContratTypeDocumentRepository;
    private EmployeeContratRepository $employeeContratRepository;

    public function __construct(
        EntityManagerInterface $entityManager,
        NatureContratRepository $natureContratRepository,
        OrganisationRepository $organisationRepository,
        EmployeRepository $employeRepository,
        DossierRepository $dossierRepository,
        DocumentRepository $documentRepository,
        NatureContratTypeDocumentRepository $natureContratTypeDocumentRepository,
        EmployeeContratRepository $employeeContratRepository
    ) {
        $this->entityManager = $entityManager;
        $this->natureContratRepository = $natureContratRepository;
        $this->organisationRepository = $organisationRepository;
        $this->employeRepository = $employeRepository;
        $this->dossierRepository = $dossierRepository;
        $this->documentRepository = $documentRepository;
        $this->natureContratTypeDocumentRepository = $natureContratTypeDocumentRepository;
        $this->employeeContratRepository = $employeeContratRepository;
    }

    /**
     * A. FIABILISATION DOSSIER RH PAR NATURE DE CONTRAT
     */
    public function getDocumentReliabilityByContractType(): array
    {
        // For now, return sample data based on the JSON structure
        // This will be replaced with real database queries once we fix the entity relationships
        return [
            [
                'contract_type' => 'NATIONAL SALARIÉ PERMANENT CDI',
                'personnel' => [
                    'completion_percentage' => 84.83,
                    'missing_documents' => 2264
                ],
                'ayant_droits' => [
                    'completion_percentage' => 6.22,
                    'missing_documents' => 5833
                ]
            ],
            [
                'contract_type' => 'NATIONAL SALARIÉ PERMANENT CDD',
                'personnel' => [
                    'completion_percentage' => 73.10,
                    'missing_documents' => 2963
                ],
                'ayant_droits' => [
                    'completion_percentage' => 2.92,
                    'missing_documents' => 4456
                ]
            ],
            [
                'contract_type' => 'NATIONAL SALARIÉ CONTRACTUEL',
                'personnel' => [
                    'completion_percentage' => 74.01,
                    'missing_documents' => 315
                ],
                'ayant_droits' => [
                    'completion_percentage' => 4.55,
                    'missing_documents' => 482
                ]
            ]
        ];
    }

    /**
     * B. FIABILISATION DOSSIER RH PAR DAS
     */
    public function getDocumentReliabilityByDAS(): array
    {
        // Sample data based on the JSON structure
        return [
            [
                'das_name' => 'DSOI',
                'personnel' => [
                    'completion_percentage' => 73.72,
                ],
                'ayant_droits' => [
                    'completion_percentage' => 3.40,
                ]
            ],
            [
                'das_name' => 'DENS',
                'personnel' => [
                    'completion_percentage' => 39.38,
                ],
                'ayant_droits' => [
                    'completion_percentage' => 1.06,
                ]
            ],
            [
                'das_name' => 'EPHR',
                'personnel' => [
                    'completion_percentage' => 70.40,
                ],
                'ayant_droits' => [
                    'completion_percentage' => 5.37,
                ]
            ]
        ];
    }

    /**
     * C. DETAILS FIABILISATION DOSSIER RH PAR DAS
     */
    public function getDetailedDocumentReliabilityByDAS(): array
    {
        // Sample data based on the JSON structure
        return [
            [
                'das_name' => 'DSOI',
                'document_stats' => [
                    'CIN' => 90.42,
                    'ACTE DE NAISSANCE' => 69.44,
                    'FORMULAIRE' => 78.59,
                    'CONTRAT' => 41.30,
                    'DOSSIER MEDICAL' => 69.62,
                    'BAC' => 51.61,
                    'DIPLÔMES' => 77.87,
                    'CV' => 83.87,
                    'RIB' => 86.78,
                    'FICHE ANTHRO' => 74.95,
                    'PHOTO' => 84.11,
                    'EMPREINTE' => 76.11,
                    'ACTE DE MARIAGE' => 9.58,
                    'CIN CONJOINT' => 3.58,
                    'ACTES NAISS ENFANT' => 3.58,
                    'CIN ENFANTS' => 0.24,
                    'CIN PARENTS' => 0.24
                ]
            ],
            [
                'das_name' => 'DENS',
                'document_stats' => [
                    'CIN' => 86.76,
                    'ACTE DE NAISSANCE' => 17.55,
                    'FORMULAIRE' => 41.73,
                    'CONTRAT' => 16.55,
                    'DOSSIER MEDICAL' => 2.01,
                    'BAC' => 9.21,
                    'DIPLÔMES' => 52.66,
                    'CV' => 51.08,
                    'RIB' => 70.07,
                    'FICHE ANTHRO' => 24.75,
                    'PHOTO' => 48.06,
                    'EMPREINTE' => 52.09,
                    'ACTE DE MARIAGE' => 1.87,
                    'CIN CONJOINT' => 2.30,
                    'ACTES NAISS ENFANT' => 0.72,
                    'CIN ENFANTS' => 0.43,
                    'CIN PARENTS' => 0.86
                ]
            ]
        ];
    }

    /**
     * D. DETAILS FIABILISATION DOSSIER RH PAR NATURE DE CONTRAT
     */
    public function getDetailedDocumentReliabilityByContractType(): array
    {
        // Sample data based on the JSON structure
        return [
            [
                'contract_type' => 'NATIONAL SALARIÉ PERMANENT CDI',
                'document_stats' => [
                    'CIN' => 99.12,
                    'ACTE DE NAISSANCE' => 74.68,
                    'FORMULAIRE' => 98.47,
                    'CONTRAT' => 48.79,
                    'DOSSIER MEDICAL' => 66.16,
                    'BAC' => 54.98,
                    'DIPLÔMES' => 95.34,
                    'CV' => 99.04,
                    'RIB' => 99.12,
                    'FICHE ANTHRO' => 89.79,
                    'PHOTO' => 99.04,
                    'EMPREINTE' => 93.49,
                    'ACTE DE MARIAGE' => 14.79,
                    'CIN CONJOINT' => 9.32,
                    'ACTES NAISS ENFANT' => 5.95,
                    'CIN ENFANTS' => 1.05,
                    'CIN PARENTS' => 1.21
                ]
            ]
        ];
    }

    /**
     * E. MATRICE PIECES "PERSONNEL"
     */
    public function getPersonnelDocumentMatrix(): array
    {
        // Sample data based on the JSON structure
        return [
            [
                'contract_type' => 'NATIONAL SALARIÉ PERMANENT CDI',
                'DSOI' => 89.67,
                'DENS' => 72.74,
                'EPHR' => 82.46,
                'DING' => 76.75,
                'DSPR' => 80.90,
                'DNUM' => 81.25,
                'DAPR' => 77.16,
                'DGST' => 79.32,
                'DSIG' => 74.17,
                'DRST' => 74.63,
                'DASO' => 76.75,
                'EING' => 74.55,
                'EPRD' => 76.52,
                'DEXP' => 73.33,
                'DPRD' => 87.50,
                'DPHR' => 76.19
            ],
            [
                'contract_type' => 'NATIONAL SALARIÉ PERMANENT CDD',
                'DSOI' => 83.84,
                'DENS' => 58.91,
                'EPHR' => 60.87,
                'DING' => 56.37,
                'DSPR' => 60.14,
                'DNUM' => 73.91,
                'DAPR' => 69.88,
                'DGST' => 77.61,
                'DSIG' => 72.42,
                'DRST' => 62.87,
                'DASO' => 87.50,
                'EING' => 56.84,
                'EPRD' => 68.87,
                'DEXP' => 73.96,
                'DPRD' => 72.22,
                'DPHR' => 77.08
            ]
        ];
    }

    /**
     * F. MATRICE PIECES "AYANT DROITS"
     */
    public function getAyantDroitsDocumentMatrix(): array
    {
        // Sample data based on the JSON structure
        return [
            [
                'contract_type' => 'NATIONAL SALARIÉ PERMANENT CDI',
                'DSOI' => 5.33,
                'DENS' => 4.86,
                'EPHR' => 7.89,
                'DING' => 9.47,
                'DSPR' => 20.83,
                'DNUM' => 4.38,
                'DAPR' => 5.93,
                'DGST' => 2.22,
                'DSIG' => 0.00,
                'DRST' => 13.24,
                'DASO' => 9.47,
                'EING' => 6.55,
                'EPRD' => 8.18,
                'DEXP' => 0.00,
                'DPRD' => 30.00,
                'DPHR' => 22.86
            ],
            [
                'contract_type' => 'NATIONAL SALARIÉ PERMANENT CDD',
                'DSOI' => 2.05,
                'DENS' => 2.79,
                'EPHR' => 1.74,
                'DING' => 5.88,
                'DSPR' => 2.70,
                'DNUM' => 3.48,
                'DAPR' => 3.86,
                'DGST' => 1.57,
                'DSIG' => 3.33,
                'DRST' => 8.61,
                'DASO' => 0.00,
                'EING' => 3.33,
                'EPRD' => 2.94,
                'DEXP' => 6.25,
                'DPRD' => 5.33,
                'DPHR' => 5.00
            ]
        ];
    }

    // Helper methods

    private function getEmployeesByContractType(string $contractType): array
    {
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('e')
           ->from('App\Entity\Employe', 'e')
           ->join('e.employeeContrats', 'c')
           ->join('c.natureContrat', 'nc')
           ->where('nc.designation = :contractType')
           ->setParameter('contractType', $contractType);
           
        return $qb->getQuery()->getResult();
    }

    private function getEmployeesByDAS(string $dasName): array
    {
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('e')
           ->from('App\Entity\Employe', 'e')
           ->join('e.employeeContrats', 'c')
           ->join('c.organisationEmployeeContrats', 'oec')
           ->join('oec.organisation', 'o')
           ->where('o.dossierDesignation = :dasName')
           ->setParameter('dasName', $dasName);
           
        return $qb->getQuery()->getResult();
    }

    private function getEmployeesByContractTypeAndDAS(string $contractType, string $dasName): array
    {
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('e')
           ->from('App\Entity\Employe', 'e')
           ->join('e.employeeContrats', 'c')
           ->join('c.natureContrat', 'nc')
           ->join('c.organisationEmployeeContrats', 'oec')
           ->join('oec.organisation', 'o')
           ->where('nc.designation = :contractType')
           ->andWhere('o.dossierDesignation = :dasName')
           ->setParameter('contractType', $contractType)
           ->setParameter('dasName', $dasName);
           
        return $qb->getQuery()->getResult();
    }

    private function calculatePersonnelStats(array $employees, string $contractType): array
    {
        $requiredDocuments = $this->getRequiredDocumentsByType('Personnel');
        $totalRequired = count($requiredDocuments) * count($employees);
        $totalUploaded = 0;
        
        foreach ($employees as $employee) {
            foreach ($requiredDocuments as $document) {
                $hasDocument = $this->hasDocument($employee, $document['abbreviation']);
                if ($hasDocument) {
                    $totalUploaded++;
                }
            }
        }
        
        $completionPercentage = $totalRequired > 0 ? ($totalUploaded / $totalRequired) * 100 : 0;
        $missingDocuments = $totalRequired - $totalUploaded;
        
        return [
            'completion_percentage' => round($completionPercentage, 2),
            'missing_documents' => $missingDocuments
        ];
    }

    private function calculateAyantDroitsStats(array $employees, string $contractType): array
    {
        $requiredDocuments = $this->getRequiredDocumentsByType('Ayant Droits');
        $totalRequired = count($requiredDocuments) * count($employees);
        $totalUploaded = 0;
        
        foreach ($employees as $employee) {
            foreach ($requiredDocuments as $document) {
                $hasDocument = $this->hasDocument($employee, $document['abbreviation']);
                if ($hasDocument) {
                    $totalUploaded++;
                }
            }
        }
        
        $completionPercentage = $totalRequired > 0 ? ($totalUploaded / $totalRequired) * 100 : 0;
        $missingDocuments = $totalRequired - $totalUploaded;
        
        return [
            'completion_percentage' => round($completionPercentage, 2),
            'missing_documents' => $missingDocuments
        ];
    }

    private function calculatePersonnelStatsByDAS(array $employees, string $dasName): array
    {
        return $this->calculatePersonnelStats($employees, '');
    }

    private function calculateAyantDroitsStatsByDAS(array $employees, string $dasName): array
    {
        return $this->calculateAyantDroitsStats($employees, '');
    }

    private function getRequiredDocumentsByType(string $type): array
    {
        // Get documents from p_document table filtered by type
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('d')
           ->from('App\Entity\Document', 'd')
           ->where('d.usage = :type')
           ->setParameter('type', $type);
           
        $documents = $qb->getQuery()->getResult();
        
        $result = [];
        foreach ($documents as $document) {
            $result[] = [
                'abbreviation' => $document->getAbbreviation(),
                'libelle' => $document->getLibelleComplet()
            ];
        }
        
        return $result;
    }

    private function calculateDocumentCompletion(array $employees, string $documentAbbreviation): float
    {
        $totalEmployees = count($employees);
        $employeesWithDocument = 0;
        
        foreach ($employees as $employee) {
            if ($this->hasDocument($employee, $documentAbbreviation)) {
                $employeesWithDocument++;
            }
        }
        
        return $totalEmployees > 0 ? round(($employeesWithDocument / $totalEmployees) * 100, 2) : 0;
    }

    private function hasDocument($employee, string $documentAbbreviation): bool
    {
        // Check if employee has uploaded this document type
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('COUNT(d)')
           ->from('App\Entity\Document', 'd')
           ->join('d.dossier', 'dos')
           ->where('dos.employe = :employee')
           ->andWhere('d.abbreviation = :abbreviation')
           ->setParameter('employee', $employee)
           ->setParameter('abbreviation', $documentAbbreviation);
           
        $count = $qb->getQuery()->getSingleScalarResult();
        
        return $count > 0;
    }
}
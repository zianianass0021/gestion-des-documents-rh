<?php

namespace App\Service;

use App\Entity\Employe;
use App\Entity\NatureContrat;
use App\Entity\Organisation;
use App\Repository\EmployeRepository;
use App\Repository\NatureContratRepository;
use App\Repository\OrganisationRepository;
use App\Service\DocumentRequirementService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * ETL Service for syncing PostgreSQL data to ClickHouse OLAP cube
 */
class OlapEtlService
{
    private ClickHouseClient $clickHouseClient;
    private EntityManagerInterface $entityManager;
    private EmployeRepository $employeRepository;
    private NatureContratRepository $natureContratRepository;
    private OrganisationRepository $organisationRepository;
    private DocumentRequirementService $documentRequirementService;
    private ?LoggerInterface $logger;
    private int $batchSize;

    public function __construct(
        ClickHouseClient $clickHouseClient,
        EntityManagerInterface $entityManager,
        EmployeRepository $employeRepository,
        NatureContratRepository $natureContratRepository,
        OrganisationRepository $organisationRepository,
        DocumentRequirementService $documentRequirementService,
        ?LoggerInterface $logger = null,
        int $batchSize = 100
    ) {
        $this->clickHouseClient = $clickHouseClient;
        $this->entityManager = $entityManager;
        $this->employeRepository = $employeRepository;
        $this->natureContratRepository = $natureContratRepository;
        $this->organisationRepository = $organisationRepository;
        $this->documentRequirementService = $documentRequirementService;
        $this->logger = $logger;
        $this->batchSize = $batchSize;
    }

    /**
     * Sync all KPIs to ClickHouse
     */
    public function syncAll(): array
    {
        $results = [
            'kpi_a' => $this->syncKpiA(),
            'kpi_b' => $this->syncKpiB(),
            'kpi_c' => $this->syncKpiC(),
            'kpi_d' => $this->syncKpiD(),
            'kpi_e' => $this->syncKpiE(),
            'kpi_f' => $this->syncKpiF(),
        ];

        return $results;
    }

    /**
     * KPI A: Performance par Nature de Contrat
     */
    public function syncKpiA(): array
    {
        $this->logger?->info('Starting KPI A sync');
        
        // Delete today's data
        $this->deleteTodayData('kpi_a_contract_type_performance');
        
        // Get all contract types
        $contractTypes = $this->natureContratRepository->findAll();
        
        $batch = [];
        $processed = 0;
        
        foreach ($contractTypes as $contractType) {
            $stats = $this->calculateContractTypeStats($contractType);
            
            $batch[] = [
                'event_date' => date('Y-m-d'),
                'contract_type' => $contractType->getDesignation(),
                'personnel_completion' => $stats['personnel']['completion_percentage'],
                'ayant_droits_completion' => $stats['ayant_droits']['completion_percentage'],
                'missing_docs_personnel' => $stats['personnel']['missing_documents'],
                'missing_docs_ayantdroits' => $stats['ayant_droits']['missing_documents'],
            ];
            
            if (count($batch) >= $this->batchSize) {
                $this->insertBatch('kpi_a_contract_type_performance', $batch);
                $processed += count($batch);
                $batch = [];
            }
        }
        
        // Insert remaining
        if (!empty($batch)) {
            $this->insertBatch('kpi_a_contract_type_performance', $batch);
            $processed += count($batch);
        }
        
        $this->logger?->info('KPI A sync completed', ['rows' => $processed]);
        
        return ['status' => 'success', 'rows' => $processed];
    }

    /**
     * KPI B: Performance par Organisation (DAS)
     * Aggregates data by DAS code instead of individual organization
     */
    public function syncKpiB(): array
    {
        $this->logger?->info('Starting KPI B sync');
        
        // Delete today's data
        $this->deleteTodayData('kpi_b_doc_reliability_by_org');
        
        // Get all organizations grouped by DAS
        $organisations = $this->organisationRepository->findAll();
        
        // Group organizations by DAS code
        $organisationsByDas = [];
        foreach ($organisations as $organisation) {
            $dasCode = $organisation->getDas() ?? '';
            
            // Skip if no DAS code
            if (empty($dasCode)) {
                continue;
            }
            
            if (!isset($organisationsByDas[$dasCode])) {
                $organisationsByDas[$dasCode] = [];
            }
            
            $organisationsByDas[$dasCode][] = $organisation;
        }
        
        $batch = [];
        $processed = 0;
        
        // Process each DAS
        foreach ($organisationsByDas as $dasCode => $orgsForDas) {
            // Aggregate stats for all organizations in this DAS
            $personnelTotalRequired = 0;
            $personnelTotalUploaded = 0;
            $ayantDroitsTotalRequired = 0;
            $ayantDroitsTotalUploaded = 0;
            
            // Calculate stats for each organization and aggregate
            foreach ($orgsForDas as $org) {
                $stats = $this->calculateOrganisationStatsWithPersonnelAyantDroits($org);
                
                // Aggregate counts
                $personnelTotalRequired += $stats['personnel']['required'] ?? 0;
                $personnelTotalUploaded += $stats['personnel']['uploaded'] ?? 0;
                $ayantDroitsTotalRequired += $stats['ayant_droits']['required'] ?? 0;
                $ayantDroitsTotalUploaded += $stats['ayant_droits']['uploaded'] ?? 0;
            }
            
            // Calculate completion percentages from aggregated totals
            $personnelCompletion = $personnelTotalRequired > 0 
                ? ($personnelTotalUploaded / $personnelTotalRequired * 100) 
                : 0;
            $ayantDroitsCompletion = $ayantDroitsTotalRequired > 0 
                ? ($ayantDroitsTotalUploaded / $ayantDroitsTotalRequired * 100) 
                : 0;
            
            $batch[] = [
                'event_date' => date('Y-m-d'),
                'organization' => $dasCode,
                'personnel_completion' => $personnelCompletion,
                'ayant_droits_completion' => $ayantDroitsCompletion,
            ];
            
            // Clear entity manager periodically to manage memory
            if (count($batch) >= 10) {
                $this->entityManager->clear();
            }
            
            if (count($batch) >= $this->batchSize) {
                $this->insertBatch('kpi_b_doc_reliability_by_org', $batch);
                $processed += count($batch);
                $batch = [];
            }
        }
        
        // Insert remaining
        if (!empty($batch)) {
            $this->insertBatch('kpi_b_doc_reliability_by_org', $batch);
            $processed += count($batch);
        }
        
        $this->logger?->info('KPI B sync completed', ['rows' => $processed]);
        
        return ['status' => 'success', 'rows' => $processed];
    }

    /**
     * KPI C: Performance Détaillée par Organisation (Document matrix)
     */
    public function syncKpiC(): array
    {
        $this->logger?->info('Starting KPI C sync');
        
        // Delete today's data
        $this->deleteTodayData('kpi_c_document_matrix');
        
        // Get all organizations grouped by DAS
        $organisations = $this->organisationRepository->findAll();
        
        // Group organizations by DAS code
        $organisationsByDas = [];
        foreach ($organisations as $organisation) {
            $dasCode = $organisation->getDas() ?? '';
            
            // Skip if no DAS code
            if (empty($dasCode)) {
                continue;
            }
            
            if (!isset($organisationsByDas[$dasCode])) {
                $organisationsByDas[$dasCode] = [];
            }
            
            $organisationsByDas[$dasCode][] = $organisation;
        }
        
        $batch = [];
        $processed = 0;
        
        // Documents to track (matching the image structure)
        $personnelDocuments = ['CIN', 'ADN', 'FORM', 'CTR', 'DMED', 'BAC', 'DIP', 'CV', 'RIB', 'FANT', 'PHOTO', 'EMPR'];
        $ayantDroitsDocuments = ['AMAR', 'CINCONJ', 'ANENF', 'CINENF', 'CINPAR'];
        
        // Process each DAS
        foreach ($organisationsByDas as $dasCode => $orgsForDas) {
            // Aggregate document stats for all organizations in this DAS
            $documentStats = [];
            
            // Calculate stats for each organization and aggregate
            foreach ($orgsForDas as $org) {
                $stats = $this->calculateDocumentStatsByTypeForOrganisation($org);
                
                // Aggregate stats by document abbreviation and type
                foreach ($stats as $docData) {
                    $docAbbr = $docData['document_abbreviation'];
                    $docType = $docData['document_type']; // 'personnel' or 'ayant_droits'
                    
                    if (!isset($documentStats[$docAbbr])) {
                        $documentStats[$docAbbr] = [];
                    }
                    if (!isset($documentStats[$docAbbr][$docType])) {
                        $documentStats[$docAbbr][$docType] = ['required' => 0, 'uploaded' => 0];
                    }
                    
                    $documentStats[$docAbbr][$docType]['required'] += $docData['required'];
                    $documentStats[$docAbbr][$docType]['uploaded'] += $docData['uploaded'];
                }
            }
            
            // Calculate completion percentages and insert into batch
            // Only include documents that were actually required (have required > 0)
            foreach ($documentStats as $docAbbr => $docTypes) {
                foreach ($docTypes as $docType => $counts) {
                    // Only include if there are required documents
                    if ($counts['required'] > 0) {
                        $completion = ($counts['uploaded'] / $counts['required'] * 100);
                        
                        $batch[] = [
                            'event_date' => date('Y-m-d'),
                            'das_code' => $dasCode,
                            'document_abbreviation' => $docAbbr,
                            'document_type' => $docType,
                            'completion_percentage' => $completion,
                        ];
                    }
                }
            }
            
            // Clear entity manager periodically to manage memory
            if (count($batch) >= 50) {
                $this->insertBatch('kpi_c_document_matrix', $batch);
                $processed += count($batch);
                $batch = [];
                $this->entityManager->clear();
            }
        }
        
        // Insert remaining
        if (!empty($batch)) {
            $this->insertBatch('kpi_c_document_matrix', $batch);
            $processed += count($batch);
        }
        
        $this->logger?->info('KPI C sync completed', ['rows' => $processed]);
        
        return ['status' => 'success', 'rows' => $processed];
    }
    
    /**
     * Calculate document stats by type for an organisation
     */
    private function calculateDocumentStatsByTypeForOrganisation(Organisation $organisation): array
    {
        $employees = $this->getEmployeesByOrganisation($organisation);
        $documentStats = [];
        
        // Process in smaller batches
        $batchSize = 10;
        for ($i = 0; $i < count($employees); $i += $batchSize) {
            $batch = array_slice($employees, $i, $batchSize);
            
            foreach ($batch as $employee) {
                if (!$employee->getDossier()) {
                    continue;
                }
                
                try {
                    $requirements = $this->documentRequirementService->getEmployeeDocumentRequirements($employee);
                    
                    foreach ($requirements as $req) {
                        $docAbbr = $req['abbreviation'] ?? '';
                        if (empty($docAbbr)) {
                            continue;
                        }
                        
                        $isObligatoire = $req['required'] ?? true;
                        $docType = $isObligatoire ? 'personnel' : 'ayant_droits';
                        
                        if (!isset($documentStats[$docAbbr])) {
                            $documentStats[$docAbbr] = [];
                        }
                        if (!isset($documentStats[$docAbbr][$docType])) {
                            $documentStats[$docAbbr][$docType] = ['required' => 0, 'uploaded' => 0];
                        }
                        
                        $documentStats[$docAbbr][$docType]['required']++;
                        if ($req['uploaded'] ?? false) {
                            $documentStats[$docAbbr][$docType]['uploaded']++;
                        }
                    }
                } catch (\Exception $e) {
                    $this->logger?->warning('Failed to get requirements for employee', [
                        'employee_id' => $employee->getId(),
                        'error' => $e->getMessage()
                    ]);
                    continue;
                }
            }
            
            $this->entityManager->clear();
        }
        
        // Return structure: [{document_abbreviation, document_type, required, uploaded}]
        $result = [];
        foreach ($documentStats as $docAbbr => $docTypes) {
            foreach ($docTypes as $docType => $counts) {
                $result[] = [
                    'document_abbreviation' => $docAbbr,
                    'document_type' => $docType,
                    'required' => $counts['required'],
                    'uploaded' => $counts['uploaded'],
                ];
            }
        }
        
        return $result;
    }

    /**
     * KPI D: Performance Détaillée par Type de Contrat (Document matrix)
     */
    public function syncKpiD(): array
    {
        $this->logger?->info('Starting KPI D sync');
        
        // Delete today's data
        $this->deleteTodayData('kpi_d_evolution');
        
        // Get all contract types
        $contractTypes = $this->natureContratRepository->findAll();
        
        $batch = [];
        $processed = 0;
        
        // Process each contract type
        foreach ($contractTypes as $contractType) {
            try {
                $contractTypeName = $contractType->getDesignation();
                
                // Calculate document stats for this contract type
                $stats = $this->calculateDocumentStatsByTypeForContractType($contractType);
                
                // Insert into batch
                foreach ($stats as $docData) {
                    // Only include if there are required documents
                    if ($docData['required'] > 0) {
                        $completion = ($docData['uploaded'] / $docData['required'] * 100);
                        
                        $batch[] = [
                            'event_date' => date('Y-m-d'),
                            'contract_type' => $contractTypeName,
                            'document_abbreviation' => $docData['document_abbreviation'],
                            'document_type' => $docData['document_type'],
                            'completion_percentage' => $completion,
                        ];
                    }
                }
                
                // Clear entity manager periodically to manage memory
                if (count($batch) >= 50) {
                    $this->insertBatch('kpi_d_evolution', $batch);
                    $processed += count($batch);
                    $batch = [];
                    $this->entityManager->clear();
                }
            } catch (\Exception $e) {
                $this->logger?->warning('Failed to process contract type', [
                    'contract_type_id' => $contractType->getId(),
                    'error' => $e->getMessage()
                ]);
                continue;
            }
        }
        
        // Insert remaining
        if (!empty($batch)) {
            $this->insertBatch('kpi_d_evolution', $batch);
            $processed += count($batch);
        }
        
        $this->logger?->info('KPI D sync completed', ['rows' => $processed]);
        
        return ['status' => 'success', 'rows' => $processed];
    }
    
    /**
     * Calculate document stats by type for a contract type
     */
    private function calculateDocumentStatsByTypeForContractType(NatureContrat $contractType): array
    {
        $employees = $this->getEmployeesByContractType($contractType);
        $documentStats = [];
        
        // Process in smaller batches
        $batchSize = 10;
        for ($i = 0; $i < count($employees); $i += $batchSize) {
            $batch = array_slice($employees, $i, $batchSize);
            
            foreach ($batch as $employee) {
                if (!$employee->getDossier()) {
                    continue;
                }
                
                try {
                    $requirements = $this->documentRequirementService->getEmployeeDocumentRequirements($employee);
                    
                    foreach ($requirements as $req) {
                        $docAbbr = $req['abbreviation'] ?? '';
                        if (empty($docAbbr)) {
                            continue;
                        }
                        
                        $isObligatoire = $req['required'] ?? true;
                        $docType = $isObligatoire ? 'personnel' : 'ayant_droits';
                        
                        if (!isset($documentStats[$docAbbr])) {
                            $documentStats[$docAbbr] = [];
                        }
                        if (!isset($documentStats[$docAbbr][$docType])) {
                            $documentStats[$docAbbr][$docType] = ['required' => 0, 'uploaded' => 0];
                        }
                        
                        $documentStats[$docAbbr][$docType]['required']++;
                        if ($req['uploaded'] ?? false) {
                            $documentStats[$docAbbr][$docType]['uploaded']++;
                        }
                    }
                } catch (\Exception $e) {
                    $this->logger?->warning('Failed to get requirements for employee', [
                        'employee_id' => $employee->getId(),
                        'error' => $e->getMessage()
                    ]);
                    continue;
                }
            }
            
            $this->entityManager->clear();
        }
        
        // Return structure: [{document_abbreviation, document_type, required, uploaded}]
        $result = [];
        foreach ($documentStats as $docAbbr => $docTypes) {
            foreach ($docTypes as $docType => $counts) {
                $result[] = [
                    'document_abbreviation' => $docAbbr,
                    'document_type' => $docType,
                    'required' => $counts['required'],
                    'uploaded' => $counts['uploaded'],
                ];
            }
        }
        
        return $result;
    }

    /**
     * KPI E: Matrice Personnel (Contrat × DAS)
     */
    public function syncKpiE(): array
    {
        $this->logger?->info('Starting KPI E sync');
        
        // Delete today's data
        $this->deleteTodayData('kpi_e_personnel_matrix');
        
        // Get all contract types
        $contractTypes = $this->natureContratRepository->findAll();
        
        // Get all organizations grouped by DAS
        $organisations = $this->organisationRepository->findAll();
        $organisationsByDas = [];
        foreach ($organisations as $organisation) {
            $dasCode = $organisation->getDas() ?? '';
            if (empty($dasCode)) {
                continue;
            }
            if (!isset($organisationsByDas[$dasCode])) {
                $organisationsByDas[$dasCode] = [];
            }
            $organisationsByDas[$dasCode][] = $organisation;
        }
        
        $batch = [];
        $processed = 0;
        
        // For each contract type × DAS combination, calculate personnel completion
        foreach ($contractTypes as $contractType) {
            $contractTypeName = $contractType->getDesignation();
            
            foreach ($organisationsByDas as $dasCode => $orgsForDas) {
                // Calculate stats for this contract type within this DAS
                $stats = $this->calculateContractTypeStatsByDas($contractType, $orgsForDas);
                
                if ($stats['personnel']['required'] > 0) {
                    $completion = $stats['personnel']['completion_percentage'];
                    
                    $batch[] = [
                        'event_date' => date('Y-m-d'),
                        'contract_type' => $contractTypeName,
                        'das_code' => $dasCode,
                        'completion_percentage' => $completion,
                    ];
                }
                
                // Clear entity manager periodically
                if (count($batch) >= 50) {
                    $this->insertBatch('kpi_e_personnel_matrix', $batch);
                    $processed += count($batch);
                    $batch = [];
                    $this->entityManager->clear();
                }
            }
        }
        
        // Insert remaining
        if (!empty($batch)) {
            $this->insertBatch('kpi_e_personnel_matrix', $batch);
            $processed += count($batch);
        }
        
        $this->logger?->info('KPI E sync completed', ['rows' => $processed]);
        
        return ['status' => 'success', 'rows' => $processed];
    }
    
    /**
     * Calculate contract type stats for a specific DAS (optimized with direct SQL)
     */
    private function calculateContractTypeStatsByDas(NatureContrat $contractType, array $organisationsInDas): array
    {
        $personnelStats = ['required' => 0, 'uploaded' => 0, 'completion_percentage' => 0];
        
        if (empty($organisationsInDas)) {
            return ['personnel' => $personnelStats];
        }
        
        // Get organization IDs
        $orgIds = array_map(fn($org) => $org->getId(), $organisationsInDas);
        
        if (empty($orgIds)) {
            return ['personnel' => $personnelStats];
        }
        
        // Use direct SQL query to get employee IDs with this contract type in these organizations
        $conn = $this->entityManager->getConnection();
        $placeholders = implode(',', array_fill(0, count($orgIds), '?'));
        $sql = "SELECT DISTINCT e.id 
                FROM t_user e 
                JOIN t_employee_contrat ec ON ec.employe_id = e.id
                JOIN t_organisation_employee_contrat oec ON oec.employee_contrat_id = ec.id
                WHERE ec.nature_contrat_id = ? 
                AND oec.organisation_id IN ($placeholders)
                AND ec.statut = ? 
                AND e.is_active = ?
                LIMIT 2000";
        
        // Build parameters array
        $params = array_merge(
            [$contractType->getId()],
            $orgIds,
            ['actif', true]
        );
        
        $stmt = $conn->prepare($sql);
        $result = $stmt->executeQuery($params);
        
        $employeeIds = $result->fetchFirstColumn();
        
        if (empty($employeeIds)) {
            return ['personnel' => $personnelStats];
        }
        
        // Process employees in batches
        $batchSize = 20;
        for ($i = 0; $i < count($employeeIds); $i += $batchSize) {
            $batchIds = array_slice($employeeIds, $i, $batchSize);
            
            // Load employees in this batch
            $qb = $this->entityManager->createQueryBuilder();
            $employees = $qb->select('e')
                ->from(Employe::class, 'e')
                ->where('e.id IN (:ids)')
                ->setParameter('ids', $batchIds)
                ->getQuery()
                ->getResult();
            
            foreach ($employees as $employee) {
                if (!$employee->getDossier()) {
                    continue;
                }
                
                try {
                    $requirements = $this->documentRequirementService->getEmployeeDocumentRequirements($employee);
                    
                    foreach ($requirements as $req) {
                        $isObligatoire = $req['required'] ?? true;
                        
                        if ($isObligatoire) {
                            // Document is obligatoire (Personnel)
                            $personnelStats['required']++;
                            if ($req['uploaded'] ?? false) {
                                $personnelStats['uploaded']++;
                            }
                        }
                    }
                } catch (\Exception $e) {
                    $this->logger?->warning('Failed to get requirements for employee', [
                        'employee_id' => $employee->getId(),
                        'error' => $e->getMessage()
                    ]);
                    continue;
                }
            }
            
            // Clear entity manager after each batch
            $this->entityManager->clear();
        }
        
        $personnelStats['completion_percentage'] = $personnelStats['required'] > 0 
            ? ($personnelStats['uploaded'] / $personnelStats['required'] * 100) 
            : 0;
        
        return [
            'personnel' => $personnelStats,
        ];
    }

    /**
     * KPI F: Matrice Ayant Droits (Contrat × DAS)
     */
    public function syncKpiF(): array
    {
        $this->logger?->info('Starting KPI F sync');
        
        // Delete today's data
        $this->deleteTodayData('kpi_f_comparative');
        
        // Get all contract types
        $contractTypes = $this->natureContratRepository->findAll();
        
        // Get all organizations grouped by DAS
        $organisations = $this->organisationRepository->findAll();
        $organisationsByDas = [];
        foreach ($organisations as $organisation) {
            $dasCode = $organisation->getDas() ?? '';
            if (empty($dasCode)) {
                continue;
            }
            if (!isset($organisationsByDas[$dasCode])) {
                $organisationsByDas[$dasCode] = [];
            }
            $organisationsByDas[$dasCode][] = $organisation;
        }
        
        $batch = [];
        $processed = 0;
        
        // For each contract type × DAS combination, calculate ayant droits completion
        foreach ($contractTypes as $contractType) {
            $contractTypeName = $contractType->getDesignation();
            
            foreach ($organisationsByDas as $dasCode => $orgsForDas) {
                // Calculate stats for this contract type within this DAS (for Ayant Droits)
                $stats = $this->calculateContractTypeStatsByDasForAyantDroits($contractType, $orgsForDas);
                
                if ($stats['ayant_droits']['required'] > 0) {
                    $completion = $stats['ayant_droits']['completion_percentage'];
                    
                    $batch[] = [
                        'event_date' => date('Y-m-d'),
                        'contract_type' => $contractTypeName,
                        'das_code' => $dasCode,
                        'completion_percentage' => $completion,
                    ];
                }
                
                // Clear entity manager periodically
                if (count($batch) >= 50) {
                    $this->insertBatch('kpi_f_comparative', $batch);
                    $processed += count($batch);
                    $batch = [];
                    $this->entityManager->clear();
                }
            }
        }
        
        // Insert remaining
        if (!empty($batch)) {
            $this->insertBatch('kpi_f_comparative', $batch);
            $processed += count($batch);
        }
        
        $this->logger?->info('KPI F sync completed', ['rows' => $processed]);
        
        return ['status' => 'success', 'rows' => $processed];
    }
    
    /**
     * Calculate contract type stats for Ayant Droits in a specific DAS (optimized with direct SQL)
     */
    private function calculateContractTypeStatsByDasForAyantDroits(NatureContrat $contractType, array $organisationsInDas): array
    {
        $ayantDroitsStats = ['required' => 0, 'uploaded' => 0, 'completion_percentage' => 0];
        
        if (empty($organisationsInDas)) {
            return ['ayant_droits' => $ayantDroitsStats];
        }
        
        // Get organization IDs
        $orgIds = array_map(fn($org) => $org->getId(), $organisationsInDas);
        
        if (empty($orgIds)) {
            return ['ayant_droits' => $ayantDroitsStats];
        }
        
        // Use direct SQL query to get employee IDs with this contract type in these organizations
        $conn = $this->entityManager->getConnection();
        $placeholders = implode(',', array_fill(0, count($orgIds), '?'));
        $sql = "SELECT DISTINCT e.id 
                FROM t_user e 
                JOIN t_employee_contrat ec ON ec.employe_id = e.id
                JOIN t_organisation_employee_contrat oec ON oec.employee_contrat_id = ec.id
                WHERE ec.nature_contrat_id = ? 
                AND oec.organisation_id IN ($placeholders)
                AND ec.statut = ? 
                AND e.is_active = ?
                LIMIT 2000";
        
        // Build parameters array
        $params = array_merge(
            [$contractType->getId()],
            $orgIds,
            ['actif', true]
        );
        
        $stmt = $conn->prepare($sql);
        $result = $stmt->executeQuery($params);
        
        $employeeIds = $result->fetchFirstColumn();
        
        if (empty($employeeIds)) {
            return ['ayant_droits' => $ayantDroitsStats];
        }
        
        // Process employees in batches
        $batchSize = 20;
        for ($i = 0; $i < count($employeeIds); $i += $batchSize) {
            $batchIds = array_slice($employeeIds, $i, $batchSize);
            
            // Load employees in this batch
            $qb = $this->entityManager->createQueryBuilder();
            $employees = $qb->select('e')
                ->from(Employe::class, 'e')
                ->where('e.id IN (:ids)')
                ->setParameter('ids', $batchIds)
                ->getQuery()
                ->getResult();
            
            foreach ($employees as $employee) {
                if (!$employee->getDossier()) {
                    continue;
                }
                
                try {
                    $requirements = $this->documentRequirementService->getEmployeeDocumentRequirements($employee);
                    
                    foreach ($requirements as $req) {
                        $isObligatoire = $req['required'] ?? true;
                        
                        if (!$isObligatoire) {
                            // Document is complementaire (Ayant Droits)
                            $ayantDroitsStats['required']++;
                            if ($req['uploaded'] ?? false) {
                                $ayantDroitsStats['uploaded']++;
                            }
                        }
                    }
                } catch (\Exception $e) {
                    $this->logger?->warning('Failed to get requirements for employee', [
                        'employee_id' => $employee->getId(),
                        'error' => $e->getMessage()
                    ]);
                    continue;
                }
            }
            
            // Clear entity manager after each batch
            $this->entityManager->clear();
        }
        
        $ayantDroitsStats['completion_percentage'] = $ayantDroitsStats['required'] > 0 
            ? ($ayantDroitsStats['uploaded'] / $ayantDroitsStats['required'] * 100) 
            : 0;
        
        return [
            'ayant_droits' => $ayantDroitsStats,
        ];
    }

    /**
     * Calculate stats for a contract type (optimized to avoid memory issues)
     */
    private function calculateContractTypeStats(NatureContrat $contractType): array
    {
        $employees = $this->getEmployeesByContractType($contractType);
        
        $personnelStats = ['required' => 0, 'uploaded' => 0, 'completion_percentage' => 0, 'missing_documents' => 0];
        $ayantDroitsStats = ['required' => 0, 'uploaded' => 0, 'completion_percentage' => 0, 'missing_documents' => 0];
        
        // Process in smaller batches to avoid memory exhaustion
        $batchSize = 10;
        for ($i = 0; $i < count($employees); $i += $batchSize) {
            $batch = array_slice($employees, $i, $batchSize);
            
            foreach ($batch as $employee) {
                // Skip if no dossier (can't check documents without dossier)
                if (!$employee->getDossier()) {
                    continue;
                }
                
                try {
                    $requirements = $this->documentRequirementService->getEmployeeDocumentRequirements($employee);
                    
                    // Separate obligatoire (Personnel) and complementaire (Ayant Droits) documents
                    // Obligatoire = required === true (Personnel)
                    // Complementaire = required === false (Ayant Droits)
                    foreach ($requirements as $req) {
                        // Check if document is obligatoire (required === true) or complementaire (required === false)
                        $isObligatoire = $req['required'] ?? true; // Default to obligatoire if not specified
                        
                        if ($isObligatoire) {
                            // Document is obligatoire (Personnel)
                            $personnelStats['required']++;
                            if ($req['uploaded'] ?? false) {
                                $personnelStats['uploaded']++;
                            }
                        } else {
                            // Document is complementaire (Ayant Droits)
                            $ayantDroitsStats['required']++;
                            if ($req['uploaded'] ?? false) {
                                $ayantDroitsStats['uploaded']++;
                            }
                        }
                    }
                } catch (\Exception $e) {
                    $this->logger?->warning('Failed to get requirements for employee', [
                        'employee_id' => $employee->getId(),
                        'error' => $e->getMessage()
                    ]);
                    continue;
                }
            }
            
            // Clear entity manager after each batch
            $this->entityManager->clear();
        }
        
        $personnelStats['completion_percentage'] = $personnelStats['required'] > 0 
            ? ($personnelStats['uploaded'] / $personnelStats['required'] * 100) 
            : 0;
        $personnelStats['missing_documents'] = $personnelStats['required'] - $personnelStats['uploaded'];
        
        $ayantDroitsStats['completion_percentage'] = $ayantDroitsStats['required'] > 0 
            ? ($ayantDroitsStats['uploaded'] / $ayantDroitsStats['required'] * 100) 
            : 0;
        $ayantDroitsStats['missing_documents'] = $ayantDroitsStats['required'] - $ayantDroitsStats['uploaded'];
        
        return [
            'personnel' => $personnelStats,
            'ayant_droits' => $ayantDroitsStats,
        ];
    }

    /**
     * Calculate stats for an organisation (optimized to avoid memory issues)
     */
    private function calculateOrganisationStats(Organisation $organisation): array
    {
        $employees = $this->getEmployeesByOrganisation($organisation);
        
        $totalRequired = 0;
        $totalUploaded = 0;
        
        // Process in smaller batches
        $batchSize = 10;
        for ($i = 0; $i < count($employees); $i += $batchSize) {
            $batch = array_slice($employees, $i, $batchSize);
            
            foreach ($batch as $employee) {
                try {
                    $requirements = $this->documentRequirementService->getEmployeeDocumentRequirements($employee);
                    foreach ($requirements as $req) {
                        if ($req['required']) {
                            $totalRequired++;
                            if ($req['uploaded']) {
                                $totalUploaded++;
                            }
                        }
                    }
                } catch (\Exception $e) {
                    $this->logger?->warning('Failed to get requirements for employee', [
                        'employee_id' => $employee->getId(),
                        'error' => $e->getMessage()
                    ]);
                    continue;
                }
            }
            
            // Clear entity manager after each batch
            $this->entityManager->clear();
        }
        
        return [
            'completion_percentage' => $totalRequired > 0 ? ($totalUploaded / $totalRequired * 100) : 0,
            'missing_documents' => $totalRequired - $totalUploaded,
        ];
    }

    /**
     * Calculate stats for an organisation with separate Personnel and Ayant Droits stats
     */
    private function calculateOrganisationStatsWithPersonnelAyantDroits(Organisation $organisation): array
    {
        $employees = $this->getEmployeesByOrganisation($organisation);
        
        $personnelStats = ['required' => 0, 'uploaded' => 0, 'completion_percentage' => 0];
        $ayantDroitsStats = ['required' => 0, 'uploaded' => 0, 'completion_percentage' => 0];
        
        // Process in smaller batches to avoid memory exhaustion
        $batchSize = 10;
        for ($i = 0; $i < count($employees); $i += $batchSize) {
            $batch = array_slice($employees, $i, $batchSize);
            
            foreach ($batch as $employee) {
                // Skip if no dossier (can't check documents without dossier)
                if (!$employee->getDossier()) {
                    continue;
                }
                
                try {
                    $requirements = $this->documentRequirementService->getEmployeeDocumentRequirements($employee);
                    
                    // Separate obligatoire (Personnel) and complementaire (Ayant Droits) documents
                    foreach ($requirements as $req) {
                        $isObligatoire = $req['required'] ?? true; // Default to obligatoire if not specified
                        
                        if ($isObligatoire) {
                            // Document is obligatoire (Personnel)
                            $personnelStats['required']++;
                            if ($req['uploaded'] ?? false) {
                                $personnelStats['uploaded']++;
                            }
                        } else {
                            // Document is complementaire (Ayant Droits)
                            $ayantDroitsStats['required']++;
                            if ($req['uploaded'] ?? false) {
                                $ayantDroitsStats['uploaded']++;
                            }
                        }
                    }
                } catch (\Exception $e) {
                    $this->logger?->warning('Failed to get requirements for employee', [
                        'employee_id' => $employee->getId(),
                        'error' => $e->getMessage()
                    ]);
                    continue;
                }
            }
            
            // Clear entity manager after each batch
            $this->entityManager->clear();
        }
        
        $personnelStats['completion_percentage'] = $personnelStats['required'] > 0 
            ? ($personnelStats['uploaded'] / $personnelStats['required'] * 100) 
            : 0;
        
        $ayantDroitsStats['completion_percentage'] = $ayantDroitsStats['required'] > 0 
            ? ($ayantDroitsStats['uploaded'] / $ayantDroitsStats['required'] * 100) 
            : 0;
        
        return [
            'personnel' => $personnelStats,
            'ayant_droits' => $ayantDroitsStats,
        ];
    }

    /**
     * Get document stats for a list of employees
     */
    private function getDocumentStatsForEmployees(array $employees): array
    {
        $docStats = [];
        
        foreach ($employees as $employee) {
            $requirements = $this->documentRequirementService->getEmployeeDocumentRequirements($employee);
            
            foreach ($requirements as $req) {
                $docName = $req['abbreviation'] ?? 'UNKNOWN';
                
                if (!isset($docStats[$docName])) {
                    $docStats[$docName] = ['have' => 0, 'missing' => 0];
                }
                
                if ($req['required']) {
                    if ($req['uploaded']) {
                        $docStats[$docName]['have']++;
                    } else {
                        $docStats[$docName]['missing']++;
                    }
                }
            }
        }
        
        return $docStats;
    }

    /**
     * Get employees by contract type (returns only IDs to avoid memory issues)
     */
    private function getEmployeesByContractType(NatureContrat $contractType): array
    {
        $conn = $this->entityManager->getConnection();
        $sql = 'SELECT DISTINCT e.id FROM t_user e 
                JOIN t_employee_contrat ec ON ec.employe_id = e.id
                WHERE ec.nature_contrat_id = :contractTypeId 
                AND ec.statut = :statut 
                AND e.is_active = :isActive
                LIMIT 1000';
        
        $stmt = $conn->prepare($sql);
        $result = $stmt->executeQuery([
            'contractTypeId' => $contractType->getId(),
            'statut' => 'actif',
            'isActive' => true
        ]);
        
        $ids = $result->fetchFirstColumn();
        
        if (empty($ids)) {
            return [];
        }
        
        // Load entities in batches to avoid memory issues
        $employees = [];
        $batchSize = 50;
        for ($i = 0; $i < count($ids); $i += $batchSize) {
            $batch = array_slice($ids, $i, $batchSize);
            $qb = $this->entityManager->createQueryBuilder();
            $batchEmployees = $qb->select('e')
                ->from(Employe::class, 'e')
                ->where('e.id IN (:ids)')
                ->setParameter('ids', $batch)
                ->getQuery()
                ->getResult();
            
            $employees = array_merge($employees, $batchEmployees);
            
            // Clear entity manager to free memory
            $this->entityManager->clear();
        }
        
        return $employees;
    }

    /**
     * Get employees by organisation (returns only IDs to avoid memory issues)
     */
    private function getEmployeesByOrganisation(Organisation $organisation): array
    {
        $conn = $this->entityManager->getConnection();
        $sql = 'SELECT DISTINCT e.id FROM t_user e 
                JOIN t_employee_contrat ec ON ec.employe_id = e.id
                JOIN t_organisation_employee_contrat oec ON oec.employee_contrat_id = ec.id
                WHERE oec.organisation_id = :orgId 
                AND ec.statut = :statut 
                AND e.is_active = :isActive
                LIMIT 1000';
        
        $stmt = $conn->prepare($sql);
        $result = $stmt->executeQuery([
            'orgId' => $organisation->getId(),
            'statut' => 'actif',
            'isActive' => true
        ]);
        
        $ids = $result->fetchFirstColumn();
        
        if (empty($ids)) {
            return [];
        }
        
        // Load entities in batches to avoid memory issues
        $employees = [];
        $batchSize = 50;
        for ($i = 0; $i < count($ids); $i += $batchSize) {
            $batch = array_slice($ids, $i, $batchSize);
            $qb = $this->entityManager->createQueryBuilder();
            $batchEmployees = $qb->select('e')
                ->from(Employe::class, 'e')
                ->where('e.id IN (:ids)')
                ->setParameter('ids', $batch)
                ->getQuery()
                ->getResult();
            
            $employees = array_merge($employees, $batchEmployees);
            
            // Clear entity manager to free memory
            $this->entityManager->clear();
        }
        
        return $employees;
    }

    /**
     * Delete today's data from a ClickHouse table
     * Uses ALTER TABLE DELETE mutation (async in ClickHouse)
     */
    private function deleteTodayData(string $tableName): void
    {
        $query = "ALTER TABLE rh_olap.{$tableName} DELETE WHERE event_date = today()";
        try {
            $this->clickHouseClient->execute($query);
            $this->logger?->info("Initiated deletion of today's data from {$tableName}");
        } catch (\Exception $e) {
            // ALTER TABLE DELETE is async and might fail if mutation is not supported
            // For MergeTree, we'll just continue and let the INSERT overwrite with deduplication
            $this->logger?->warning("Could not delete today's data from {$tableName} (mutation may not be supported)", [
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Insert a batch of data into ClickHouse
     */
    private function insertBatch(string $tableName, array $batch): void
    {
        if (empty($batch)) {
            return;
        }
        
        // Get column order from first row
        $columns = array_keys($batch[0]);
        
        // Use VALUES format which is simpler and more compatible
        $this->insertBatchValues($tableName, $batch, $columns);
    }

    /**
     * Insert batch using VALUES format (simpler, more compatible)
     */
    private function insertBatchValues(string $tableName, array $batch, array $columns): void
    {
        $values = [];
        foreach ($batch as $row) {
            $rowValues = [];
            foreach ($columns as $col) {
                $value = $row[$col] ?? null;
                if ($value === null) {
                    $rowValues[] = 'NULL';
                } elseif (is_string($value)) {
                    // Escape single quotes for SQL
                    $escaped = str_replace("'", "''", $value);
                    $rowValues[] = "'{$escaped}'";
                } elseif (is_float($value)) {
                    $rowValues[] = number_format($value, 2, '.', '');
                } else {
                    $rowValues[] = (string) $value;
                }
            }
            $values[] = '(' . implode(', ', $rowValues) . ')';
        }
        
        $columnList = implode(', ', $columns);
        $query = "INSERT INTO rh_olap.{$tableName} ({$columnList}) VALUES " . implode(', ', $values);
        
        $this->clickHouseClient->insert($query);
    }
}


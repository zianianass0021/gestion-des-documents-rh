<?php

namespace App\Controller;

use App\Repository\EmployeRepository;
use App\Repository\DossierRepository;
use App\Repository\DocumentRepository;
use App\Repository\DemandeRepository;
use App\Repository\ReclamationRepository;
// TypeDocumentRepository supprimé car l'entité TypeDocument n'existe plus
use App\Repository\EmployeeContratRepository;
use App\Service\DocumentRequirementService;
use App\Service\KpiService;
use App\Service\KpiOlapService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class DashboardController extends AbstractController
{
    #[Route('/dashboard', name: 'app_dashboard')]
    public function index(
        EmployeRepository $employeRepository,
        DossierRepository $dossierRepository,
        DocumentRepository $documentRepository,
        DemandeRepository $demandeRepository,
        ReclamationRepository $reclamationRepository,
        EmployeeContratRepository $contratRepository,
        DocumentRequirementService $documentRequirementService
    ): Response {
        $user = $this->getUser();
        $roles = $user ? $user->getRoles() : [];
        
        // Si c'est un employé ou un manager (qui est aussi un employé), afficher son dashboard personnel
        if (in_array('ROLE_EMPLOYEE', $roles) || in_array('ROLE_MANAGER', $roles)) {
            return $this->renderEmployeeDashboard($user, $contratRepository, $dossierRepository, $documentRepository, $documentRequirementService);
        }
        
        // Si c'est un administrateur RH, rediriger vers son dashboard spécifique
        if (in_array('ROLE_ADMINISTRATEUR_RH', $roles)) {
            return $this->redirectToRoute('administrateur_rh_dashboard');
        }
        
        // Sinon, afficher le dashboard global pour les responsables RH
        // KPI généraux
        try {
            $kpis = [
                'total_employees' => $employeRepository->count([]),
                'total_dossiers' => $dossierRepository->count([]),
                'total_documents' => $documentRepository->count([]),
                'total_demandes' => $demandeRepository->count([]),
                'total_reclamations' => $reclamationRepository->count([]),
                'total_contrats' => $contratRepository->count([]),
            ];
        } catch (\Doctrine\DBAL\Exception\TableNotFoundException $e) {
            $this->addFlash('error', 'Les tables de la base de données n\'existent pas. Veuillez exécuter: php bin/console doctrine:migrations:migrate');
            $kpis = [
                'total_employees' => 0,
                'total_dossiers' => 0,
                'total_documents' => 0,
                'total_demandes' => 0,
                'total_reclamations' => 0,
                'total_contrats' => 0,
            ];
        }

        // KPI pour les dossiers
        $dossiers_stats = [
            'completed' => $dossierRepository->count(['status' => 'completed']),
            'in_progress' => $dossierRepository->count(['status' => 'in_progress']),
            'pending' => $dossierRepository->count(['status' => 'pending']),
        ];

        // KPI pour les documents obligatoires (supprimé car TypeDocument n'existe plus)
        $obligatory_docs_count = 0;

        // KPI pour les demandes
        $demandes_stats = [
            'en_attente' => $demandeRepository->countEnAttente(),
            'acceptees' => $demandeRepository->count(['statut' => 'acceptee']),
            'refusees' => $demandeRepository->count(['statut' => 'refusee']),
        ];

        // KPI pour les contrats
        $contrats_stats = [
            'actifs' => $contratRepository->count(['statut' => 'actif']),
            'expires' => $contratRepository->count(['statut' => 'expire']),
            'suspendus' => $contratRepository->count(['statut' => 'suspendu']),
        ];

        // Statistiques par organisation supprimées

        // Documents récents
        $recent_documents = $documentRepository->findBy([], ['id' => 'DESC'], 5);

        // Demandes récentes
        $recent_demandes = $demandeRepository->findBy([], ['dateCreation' => 'DESC'], 5);

        // Don't calculate reports on page load - load via AJAX when user opens modal
        $performanceData = [
            'rapport-a' => ['title' => 'A. Performance par Nature de Contrat', 'data' => null, 'loaded' => false],
            'rapport-b' => ['title' => 'B. Performance par Organisation', 'data' => null, 'loaded' => false],
            'rapport-c' => ['title' => 'C. Performance Détaillée par Organisation', 'data' => null, 'loaded' => false],
            'rapport-d' => ['title' => 'D. Performance Détaillée par Nature de Contrat', 'data' => null, 'loaded' => false],
            'rapport-e' => ['title' => 'E. Matrice Personnel', 'data' => null, 'loaded' => false],
            'rapport-f' => ['title' => 'F. Matrice Ayant Droits', 'data' => null, 'loaded' => false],
        ];

        return $this->render('dashboard/index.html.twig', [
            'kpis' => $kpis,
            'dossiers_stats' => $dossiers_stats,
            'obligatory_docs_count' => $obligatory_docs_count,
            'demandes_stats' => $demandes_stats,
            'contrats_stats' => $contrats_stats,
            'recent_documents' => $recent_documents,
            'recent_demandes' => $recent_demandes,
            'performanceData' => $performanceData,
        ]);
    }

    private function renderEmployeeDashboard($employee, EmployeeContratRepository $contratRepository, DossierRepository $dossierRepository, DocumentRepository $documentRepository, DocumentRequirementService $documentRequirementService): Response
    {
        // Récupérer les informations de l'employé connecté
        $contrats = $contratRepository->findBy(['employe' => $employee]);
        $dossiers = $dossierRepository->findBy(['employe' => $employee]);
        
        // Only load documents for this employee's dossier - avoid loading ALL documents!
        $documents = [];
        if ($employee->getDossier()) {
            $documents = $employee->getDossier()->getDocuments()->toArray();
        }

        // Get document requirements once (don't call it in a loop!)
        $documentRequirements = $documentRequirementService->getEmployeeDocumentRequirements($employee);
        
        // Calculer le statut réel de chaque dossier basé sur les documents requis
        $dossiersWithRealStatus = [];
        foreach ($dossiers as $dossier) {
            // Reuse the same requirements for each dossier
            $requiredDocs = array_filter($documentRequirements, function($req) { return $req['required']; });
            $uploadedRequiredDocs = array_filter($requiredDocs, function($req) { return $req['uploaded']; });
            
            $completionRate = count($requiredDocs) > 0 ? (count($uploadedRequiredDocs) / count($requiredDocs) * 100) : 100;
            
            // Déterminer le statut basé sur le taux de completion
            if ($completionRate == 100) {
                $realStatus = 'completed';
            } elseif ($completionRate >= 50) {
                $realStatus = 'in_progress';
            } else {
                $realStatus = 'pending';
            }
            
            $dossiersWithRealStatus[] = [
                'dossier' => $dossier,
                'realStatus' => $realStatus,
                'completionRate' => round($completionRate)
            ];
        }

        // Calculate meaningful KPIs for the dashboard (reuse requirements already fetched)
        $requiredDocs = array_filter($documentRequirements, function($req) { return $req['required']; });
        $uploadedRequiredDocs = array_filter($requiredDocs, function($req) { return $req['uploaded']; });
        $completionRate = count($requiredDocs) > 0 ? (count($uploadedRequiredDocs) / count($requiredDocs) * 100) : 100;
        
        // Use documents already loaded earlier
        $employeeDocuments = $documents;
        
        // Count demandes (requests) - assuming this relationship exists
        $demandesCount = 0;
        if (method_exists($employee, 'getDemandes')) {
            $demandesCount = $employee->getDemandes()->count();
        }

        $response = $this->render('employee/dashboard.html.twig', [
            'employee' => $employee,
            'contrats' => $contrats,
            'dossiers' => $dossiers,
            'dossiersWithRealStatus' => $dossiersWithRealStatus,
            'documents' => $documents,
            'employeeDocuments' => $employeeDocuments,
            'documentRequirements' => $documentRequirements,
            'requiredDocsCount' => count($requiredDocs),
            'uploadedRequiredDocsCount' => count($uploadedRequiredDocs),
            'completionRate' => round($completionRate),
            'demandesCount' => $demandesCount
        ]);
        
        $response->headers->set('Cache-Control', 'no-cache, no-store, must-revalidate, private');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', '0');
        
        return $response;
    }
    
    #[Route('/api/report/{reportType}', name: 'api_report', methods: ['GET'])]
    public function getReport(string $reportType, KpiOlapService $kpiOlapService): Response
    {
        try {
            // Disable cache for this endpoint
            $response = $this->json([], 200);
            $response->headers->set('Cache-Control', 'no-cache, no-store, must-revalidate');
            $response->headers->set('Pragma', 'no-cache');
            $response->headers->set('Expires', '0');
            
            $rawData = null;
            $title = '';
            
            switch ($reportType) {
                case 'rapport-a':
                    $title = 'A. Fiabilisation par Nature de Contrat';
                    try {
                        $rawData = $kpiOlapService->getKpiA();
                        error_log('getReport rapport-a - getKpiA() returned ' . count($rawData) . ' rows');
                        if (empty($rawData)) {
                            error_log('getReport rapport-a - WARNING: getKpiA() returned empty array!');
                        } else {
                            error_log('getReport rapport-a - First row: ' . json_encode($rawData[0] ?? []));
                        }
                    } catch (\Exception $e) {
                        error_log('getReport rapport-a - Exception in getKpiA(): ' . $e->getMessage());
                        error_log('getReport rapport-a - Stack trace: ' . $e->getTraceAsString());
                        $rawData = [];
                    }
                    $formattedData = $this->formatReportAData($rawData);
                    error_log('getReport rapport-a - formatReportAData returned ' . count($formattedData) . ' rows');
                    if (!empty($formattedData)) {
                        error_log('getReport rapport-a - Header row length: ' . count($formattedData[0] ?? []));
                        error_log('getReport rapport-a - Header row: ' . json_encode($formattedData[0] ?? []));
                    }
                    break;
                    
                case 'rapport-b':
                    $title = 'B. Fiabilisation par DAS';
                    $rawData = $kpiOlapService->getKpiB();
                    $formattedData = $this->formatReportBData($rawData);
                    break;
                    
                case 'rapport-c':
                    $title = 'C. Détails par DAS';
                    $rawData = $kpiOlapService->getKpiC();
                    $formattedData = $this->formatReportCData($rawData);
                    break;
                    
                case 'rapport-d':
                    $title = 'D. Détails par Contrat';
                    $rawData = $kpiOlapService->getKpiD();
                    $formattedData = $this->formatReportDData($rawData);
                    break;
                    
                case 'rapport-e':
                    $title = 'E. Matrice Personnel';
                    $rawData = $kpiOlapService->getKpiE();
                    $formattedData = $this->formatReportEData($rawData);
                    break;
                    
                case 'rapport-f':
                    $title = 'F. Matrice Ayant Droits';
                    $rawData = $kpiOlapService->getKpiF();
                    $formattedData = $this->formatReportFData($rawData);
                    break;
                    
                default:
                    return $this->json(['error' => 'Invalid report type'], 400);
            }
            
            $debugInfo = [];
            if ($reportType === 'rapport-a') {
                $debugInfo = [
                    'rawDataCount' => isset($rawData) ? count($rawData) : 0,
                    'formattedDataCount' => count($formattedData),
                    'headerLength' => !empty($formattedData) ? count($formattedData[0] ?? []) : 0,
                ];
            }
            
            $response = $this->json([
                'title' => $title,
                'data' => $formattedData,
                '_debug' => $debugInfo
            ]);
            
            // Disable cache
            $response->headers->set('Cache-Control', 'no-cache, no-store, must-revalidate');
            $response->headers->set('Pragma', 'no-cache');
            $response->headers->set('Expires', '0');
            
            // Debug: log response size
            error_log("getReport {$reportType} - Returning " . count($formattedData) . " rows, header length: " . (count($formattedData[0] ?? [])));
            
            return $response;
        } catch (\Exception $e) {
            error_log("getReport {$reportType} - Error: " . $e->getMessage());
            return $this->json(['error' => $e->getMessage()], 500);
        }
    }
    
    private function getPlaceholderReport(string $title): array
    {
        return [
            'title' => $title,
            'data' => [
                ['Status', 'Message'],
                ['Not Implemented', 'Ce rapport sera implémenté prochainement.']
            ]
        ];
    }
    
    // TODO: Will be implemented when requested
    private function formatReportAData(array $rawData): array
    {
        // Debug logging
        error_log('formatReportAData - Input rawData count: ' . count($rawData));
        if (!empty($rawData)) {
            error_log('formatReportAData - First row structure: ' . json_encode(array_keys($rawData[0] ?? [])));
            error_log('formatReportAData - First row sample: ' . json_encode($rawData[0] ?? []));
        }
        
        if (empty($rawData)) {
            error_log('formatReportAData - rawData is empty, returning placeholder');
            return [
                ['', ''],
                ['DOSSIER PERSONNEL', 'Avancement', 'N/A'],
                ['', 'Nombre de documents manquants', 'N/A'],
                ['AYANT DROITS', 'Avancement', 'N/A'],
                ['', 'Nombre de documents manquants', 'N/A']
            ];
        }
        
        // Structure based on the example image:
        // Row 0: Header ['', '', contract_type1, contract_type2, ...]
        // Row 1: ['DOSSIER PERSONNEL', 'Avancement', percentage1, percentage2, ...]
        // Row 2: ['', 'Nombre de documents manquants', count1, count2, ...]
        // Row 3: ['AYANT DROITS', 'Avancement', percentage1, percentage2, ...]
        // Row 4: ['', 'Nombre de documents manquants', count1, count2, ...]
        
        // Initialize rows
        $header = ['', ''];
        $dossierPersonnelAvancement = ['DOSSIER PERSONNEL', 'Avancement'];
        $dossierPersonnelManquants = ['', 'Nombre de documents manquants'];
        $ayantDroitsAvancement = ['AYANT DROITS', 'Avancement'];
        $ayantDroitsManquants = ['', 'Nombre de documents manquants'];
        
        $processedCount = 0;
        $skippedCount = 0;
        
        // Process each contract type
        foreach ($rawData as $index => $row) {
            // Extract contract type
            $contractType = '';
            if (isset($row['contract_type'])) {
                $contractType = trim($row['contract_type']);
            } elseif (isset($row[0]) && is_string($row[0])) {
                $contractType = trim($row[0]);
            }
            
            if (empty($contractType)) {
                $skippedCount++;
                error_log("formatReportAData - Skipping row {$index}: empty contract_type");
                continue;
            }
            
            // Extract personnel data
            $personnelCompletion = 0.0;
            $personnelMissing = 0;
            
            if (isset($row['personnel']['completion_percentage'])) {
                $personnelCompletion = (float)$row['personnel']['completion_percentage'];
                $personnelMissing = (int)($row['personnel']['missing_documents'] ?? 0);
            } elseif (isset($row['personnel_completion'])) {
                $personnelCompletion = (float)$row['personnel_completion'];
                $personnelMissing = (int)($row['missing_docs_personnel'] ?? 0);
            }
            
            // Extract ayant_droits data
            $ayantDroitsCompletion = 0.0;
            $ayantDroitsMissing = 0;
            
            if (isset($row['ayant_droits']['completion_percentage'])) {
                $ayantDroitsCompletion = (float)$row['ayant_droits']['completion_percentage'];
                $ayantDroitsMissing = (int)($row['ayant_droits']['missing_documents'] ?? 0);
            } elseif (isset($row['ayant_droits_completion'])) {
                $ayantDroitsCompletion = (float)$row['ayant_droits_completion'];
                $ayantDroitsMissing = (int)($row['missing_docs_ayantdroits'] ?? 0);
            }
            
            // Add to header and data rows
            $header[] = $contractType;
            $dossierPersonnelAvancement[] = number_format($personnelCompletion, 2) . '%';
            $dossierPersonnelManquants[] = (string)$personnelMissing;
            $ayantDroitsAvancement[] = number_format($ayantDroitsCompletion, 2) . '%';
            $ayantDroitsManquants[] = (string)$ayantDroitsMissing;
            $processedCount++;
        }
        
        error_log("formatReportAData - Processed: {$processedCount}, Skipped: {$skippedCount}, Header length: " . count($header));
        
        // Ensure all rows have the same length
        $columnCount = count($header);
        
        // Pad rows if needed (shouldn't happen, but safety check)
        while (count($dossierPersonnelAvancement) < $columnCount) {
            $dossierPersonnelAvancement[] = '';
        }
        while (count($dossierPersonnelManquants) < $columnCount) {
            $dossierPersonnelManquants[] = '';
        }
        while (count($ayantDroitsAvancement) < $columnCount) {
            $ayantDroitsAvancement[] = '';
        }
        while (count($ayantDroitsManquants) < $columnCount) {
            $ayantDroitsManquants[] = '';
        }
        
        error_log("formatReportAData - Final header length: {$columnCount}, returning 5 rows");
        
        // Return the matrix structure matching the example
        return [
            $header,
            $dossierPersonnelAvancement,
            $dossierPersonnelManquants,
            $ayantDroitsAvancement,
            $ayantDroitsManquants
        ];
    }
    
    private function formatReportBData(array $rawData): array
    {
        if (empty($rawData)) {
            return [
                ['', 'Aucune donnée disponible'],
                ['DOSSIER PERSONNEL', 'Veuillez exécuter la synchronisation OLAP'],
                ['AYANT DROITS', '']
            ];
        }
        
        // Structure like the example:
        // Row 0: ['', '', org1, org2, ...] - header with organizations
        // Row 1: ['DOSSIER PERSONNEL', '', percentage1, percentage2, ...] - personnel completion per org
        // Row 2: ['AYANT DROITS', '', percentage1, percentage2, ...] - ayant droits completion per org
        
        // Only include the 16 organizations from the example, in the exact order shown
        // Always include all 16, even if they don't have data (will show 0%)
        $organizations = ['DSOI', 'DENS', 'EPHR', 'DING', 'DSPR', 'DNUM', 'DAPR', 'DGST', 'DSIG', 'DRST', 'DASO', 'EING', 'EPRD', 'DEXP', 'DPRD', 'DPHR'];
        
        // Build header row: ['', '', org1, org2, ...]
        $header = ['', ''];
        $header = array_merge($header, $organizations);
        
        // Build DOSSIER PERSONNEL row: ['DOSSIER PERSONNEL', '', percentage1, percentage2, ...]
        $personnelRow = ['DOSSIER PERSONNEL', ''];
        foreach ($organizations as $org) {
            $personnelCompletion = 0;
            foreach ($rawData as $row) {
                $rowOrg = trim(strtoupper($row['organization'] ?? ''));
                $searchOrg = trim(strtoupper($org));
                if ($rowOrg === $searchOrg) {
                    $personnelCompletion = (float)($row['personnel_completion'] ?? 0);
                    break;
                }
            }
            $personnelRow[] = number_format($personnelCompletion, 2) . '%';
        }
        
        // Build AYANT DROITS row: ['AYANT DROITS', '', percentage1, percentage2, ...]
        $ayantDroitsRow = ['AYANT DROITS', ''];
        foreach ($organizations as $org) {
            $ayantDroitsCompletion = 0;
            foreach ($rawData as $row) {
                $rowOrg = trim(strtoupper($row['organization'] ?? ''));
                $searchOrg = trim(strtoupper($org));
                if ($rowOrg === $searchOrg) {
                    $ayantDroitsCompletion = (float)($row['ayant_droits_completion'] ?? 0);
                    break;
                }
            }
            $ayantDroitsRow[] = number_format($ayantDroitsCompletion, 2) . '%';
        }
        
        return [$header, $personnelRow, $ayantDroitsRow];
    }
    
    private function formatReportCData(array $rawData): array
    {
        if (empty($rawData)) {
            return [
                ['', 'Aucune donnée disponible'],
                ['DOSSIER PERSONNEL', 'Veuillez exécuter la synchronisation OLAP'],
                ['AYANT DROITS', '']
            ];
        }
        
        // Structure matching the image:
        // Row 0: Header ['', 'DOSSIER PERSONNEL', doc1, doc2, ..., '% DOSSIER PERSONNEL', 'AYANT DROITS', doc1, doc2, ..., '% AYANT DROITS']
        // Row 1+: Data rows [DAS, '', percentage1, percentage2, ..., personnel%, '', percentage1, percentage2, ..., ayant_droits%]
        
        // Document columns in order (matching image)
        $personnelDocuments = ['CIN', 'ACTE DE NAISSANCE', 'FORMULAIRE', 'CONTRAT', 'DOSSIER MEDICAL', 'BAC', 'DIPLOMES', 'CV', 'RB', 'FICHE ANTHRO', 'PHOTO', 'EMPREINTE'];
        $ayantDroitsDocuments = ['ACTE DE MARIAGE', 'CIN CONJOINT', 'ACTES NAISS ENFANT', 'CIN ENFANTS', 'CIN PARENTS'];
        
        // Map abbreviations to full names
        $abbreviationMap = [
            'CIN' => 'CIN',
            'ADN' => 'ACTE DE NAISSANCE',
            'FORM' => 'FORMULAIRE',
            'CTR' => 'CONTRAT',
            'DMED' => 'DOSSIER MEDICAL',
            'BAC' => 'BAC',
            'DIP' => 'DIPLOMES',
            'CV' => 'CV',
            'RIB' => 'RB',
            'FANT' => 'FICHE ANTHRO',
            'PHOTO' => 'PHOTO',
            'EMPR' => 'EMPREINTE',
            'AMAR' => 'ACTE DE MARIAGE',
            'CINCONJ' => 'CIN CONJOINT',
            'ANENF' => 'ACTES NAISS ENFANT',
            'CINENF' => 'CIN ENFANTS',
            'CINPAR' => 'CIN PARENTS',
        ];
        
        // Build header row
        $header = ['', 'DOSSIER PERSONNEL'];
        $header = array_merge($header, $personnelDocuments);
        $header[] = '% DOSSIER PERSONNEL';
        $header[] = 'AYANT DROITS';
        $header = array_merge($header, $ayantDroitsDocuments);
        $header[] = '% AYANT DROITS';
        
        // Only include the 16 DAS codes from the example, in exact order
        $targetDasCodes = ['DSOI', 'DENS', 'EPHR', 'DING', 'DSPR', 'DNUM', 'DAPR', 'DGST', 'DSIG', 'DRST', 'DASO', 'EING', 'EPRD', 'DEXP', 'DPRD', 'DPHR'];
        
        // Build data map from rawData
        $dataMap = [];
        foreach ($rawData as $row) {
            $dasCode = trim(strtoupper($row['das_code'] ?? ''));
            if (!empty($dasCode)) {
                $dataMap[$dasCode] = $row['documents'] ?? [];
            }
        }
        
        // Build data rows
        $rows = [$header];
        foreach ($targetDasCodes as $dasCode) {
            $dasRow = [$dasCode, '']; // DAS code, empty cell
            
            // Personnel documents
            $personnelTotal = 0;
            $personnelCount = 0;
            foreach ($personnelDocuments as $docName) {
                // Find matching abbreviation
                $docAbbr = null;
                foreach ($abbreviationMap as $abbr => $fullName) {
                    if ($fullName === $docName) {
                        $docAbbr = $abbr;
                        break;
                    }
                }
                
                $completion = 0.0;
                if ($docAbbr && isset($dataMap[$dasCode][$docAbbr . '_personnel'])) {
                    $completion = (float)($dataMap[$dasCode][$docAbbr . '_personnel']['completion_percentage'] ?? 0);
                    $personnelTotal += $completion;
                    $personnelCount++;
                }
                $dasRow[] = number_format($completion, 2) . '%';
            }
            
            // Personnel overall percentage
            $personnelOverall = $personnelCount > 0 ? ($personnelTotal / $personnelCount) : 0;
            $dasRow[] = number_format($personnelOverall, 2) . '%';
            
            // Ayant Droits section separator
            $dasRow[] = '';
            
            // Ayant Droits documents
            $ayantDroitsTotal = 0;
            $ayantDroitsCount = 0;
            foreach ($ayantDroitsDocuments as $docName) {
                // Find matching abbreviation
                $docAbbr = null;
                foreach ($abbreviationMap as $abbr => $fullName) {
                    if ($fullName === $docName) {
                        $docAbbr = $abbr;
                        break;
                    }
                }
                
                $completion = 0.0;
                if ($docAbbr && isset($dataMap[$dasCode][$docAbbr . '_ayant_droits'])) {
                    $completion = (float)($dataMap[$dasCode][$docAbbr . '_ayant_droits']['completion_percentage'] ?? 0);
                    $ayantDroitsTotal += $completion;
                    $ayantDroitsCount++;
                }
                $dasRow[] = number_format($completion, 2) . '%';
            }
            
            // Ayant Droits overall percentage
            $ayantDroitsOverall = $ayantDroitsCount > 0 ? ($ayantDroitsTotal / $ayantDroitsCount) : 0;
            $dasRow[] = number_format($ayantDroitsOverall, 2) . '%';
            
            $rows[] = $dasRow;
        }
        
        return $rows;
    }
    
    private function formatReportDData(array $rawData): array
    {
        if (empty($rawData)) {
            return [
                ['', 'Aucune donnée disponible'],
                ['DOSSIER PERSONNEL', 'Veuillez exécuter la synchronisation OLAP'],
                ['AYANT DROITS', '']
            ];
        }
        
        // Structure matching the image:
        // Row 0: Header ['', 'DOSSIER PERSONNEL', doc1, doc2, ..., '% DOSSIER PERSONNEL', 'AYANT DROITS', doc1, doc2, ..., '% AYANT DROITS']
        // Row 1+: Data rows [NATURE/ContractType, '', percentage1, percentage2, ..., personnel%, '', percentage1, percentage2, ..., ayant_droits%]
        
        // Document columns in order (matching image - same as KPI C)
        $personnelDocuments = ['CIN', 'ACTE DE NAISSANCE', 'FORMULAIRE', 'CONTRAT', 'DOSSIER MEDICAL', 'BAC', 'DIPLOMES', 'CV', 'RB', 'FICHE ANTHRO', 'PHOTO', 'EMPREINTE'];
        $ayantDroitsDocuments = ['ACTE DE MARIAGE', 'CIN CONJOINT', 'ACTES NAISS ENFANT', 'CIN ENFANTS', 'CIN PARENTS'];
        
        // Map abbreviations to full names
        $abbreviationMap = [
            'CIN' => 'CIN',
            'ADN' => 'ACTE DE NAISSANCE',
            'FORM' => 'FORMULAIRE',
            'CTR' => 'CONTRAT',
            'DMED' => 'DOSSIER MEDICAL',
            'BAC' => 'BAC',
            'DIP' => 'DIPLOMES',
            'CV' => 'CV',
            'RIB' => 'RB',
            'FANT' => 'FICHE ANTHRO',
            'PHOTO' => 'PHOTO',
            'EMPR' => 'EMPREINTE',
            'AMAR' => 'ACTE DE MARIAGE',
            'CINCONJ' => 'CIN CONJOINT',
            'ANENF' => 'ACTES NAISS ENFANT',
            'CINENF' => 'CIN ENFANTS',
            'CINPAR' => 'CIN PARENTS',
        ];
        
        // Build header row
        $header = ['', 'DOSSIER PERSONNEL'];
        $header = array_merge($header, $personnelDocuments);
        $header[] = '% DOSSIER PERSONNEL';
        $header[] = 'AYANT DROITS';
        $header = array_merge($header, $ayantDroitsDocuments);
        $header[] = '% AYANT DROITS';
        
        // Build data map from rawData
        $dataMap = [];
        foreach ($rawData as $row) {
            $contractType = trim($row['contract_type'] ?? '');
            if (!empty($contractType)) {
                $dataMap[$contractType] = $row['documents'] ?? [];
            }
        }
        
        // Get all contract types from database (sorted by designation)
        // We'll use all contract types that exist in the data
        $contractTypes = array_keys($dataMap);
        sort($contractTypes);
        
        // Build data rows
        $rows = [$header];
        foreach ($contractTypes as $contractType) {
            $contractRow = [$contractType, '']; // Contract type, empty cell
            
            // Personnel documents
            $personnelTotal = 0;
            $personnelCount = 0;
            foreach ($personnelDocuments as $docName) {
                // Find matching abbreviation
                $docAbbr = null;
                foreach ($abbreviationMap as $abbr => $fullName) {
                    if ($fullName === $docName) {
                        $docAbbr = $abbr;
                        break;
                    }
                }
                
                $completion = 0.0;
                if ($docAbbr && isset($dataMap[$contractType][$docAbbr . '_personnel'])) {
                    $completion = (float)($dataMap[$contractType][$docAbbr . '_personnel']['completion_percentage'] ?? 0);
                    $personnelTotal += $completion;
                    $personnelCount++;
                }
                $contractRow[] = number_format($completion, 2) . '%';
            }
            
            // Personnel overall percentage
            $personnelOverall = $personnelCount > 0 ? ($personnelTotal / $personnelCount) : 0;
            $contractRow[] = number_format($personnelOverall, 2) . '%';
            
            // Ayant Droits section separator
            $contractRow[] = '';
            
            // Ayant Droits documents
            $ayantDroitsTotal = 0;
            $ayantDroitsCount = 0;
            foreach ($ayantDroitsDocuments as $docName) {
                // Find matching abbreviation
                $docAbbr = null;
                foreach ($abbreviationMap as $abbr => $fullName) {
                    if ($fullName === $docName) {
                        $docAbbr = $abbr;
                        break;
                    }
                }
                
                $completion = 0.0;
                if ($docAbbr && isset($dataMap[$contractType][$docAbbr . '_ayant_droits'])) {
                    $completion = (float)($dataMap[$contractType][$docAbbr . '_ayant_droits']['completion_percentage'] ?? 0);
                    $ayantDroitsTotal += $completion;
                    $ayantDroitsCount++;
                }
                $contractRow[] = number_format($completion, 2) . '%';
            }
            
            // Ayant Droits overall percentage
            $ayantDroitsOverall = $ayantDroitsCount > 0 ? ($ayantDroitsTotal / $ayantDroitsCount) : 0;
            $contractRow[] = number_format($ayantDroitsOverall, 2) . '%';
            
            $rows[] = $contractRow;
        }
        
        return $rows;
    }
    
    private function formatReportEData(array $rawData): array
    {
        if (empty($rawData)) {
            return [
                ['', 'Aucune donnée disponible'],
                ['NATURE', 'Veuillez exécuter la synchronisation OLAP']
            ];
        }
        
        // Structure matching the image:
        // Row 0: Header ['', 'DAS1', 'DAS2', ...] - header with DAS codes
        // Row 1+: Data rows [ContractType, percentage1, percentage2, ...] - one row per contract type
        
        // Only include the 16 DAS codes from the example, in exact order
        $targetDasCodes = ['DSOI', 'DENS', 'EPHR', 'DING', 'DSPR', 'DNUM', 'DAPR', 'DGST', 'DSIG', 'DRST', 'DASO', 'EING', 'EPRD', 'DEXP', 'DPRD', 'DPHR'];
        
        // Build data map: contract_type => [das_code => completion_percentage]
        $dataMap = [];
        foreach ($rawData as $row) {
            $contractType = trim($row['contract_type'] ?? '');
            $dasCode = trim(strtoupper($row['das_code'] ?? ''));
            $completion = (float)($row['completion_percentage'] ?? 0);
            
            if (!empty($contractType) && !empty($dasCode)) {
                if (!isset($dataMap[$contractType])) {
                    $dataMap[$contractType] = [];
                }
                $dataMap[$contractType][$dasCode] = $completion;
            }
        }
        
        // Get all contract types (sorted)
        $contractTypes = array_keys($dataMap);
        sort($contractTypes);
        
        // Build header row
        $header = [''];
        $header = array_merge($header, $targetDasCodes);
        
        // Build data rows
        $rows = [$header];
        foreach ($contractTypes as $contractType) {
            $contractRow = [$contractType];
            
            foreach ($targetDasCodes as $dasCode) {
                $completion = $dataMap[$contractType][$dasCode] ?? 0.0;
                $contractRow[] = number_format($completion, 2) . '%';
            }
            
            $rows[] = $contractRow;
        }
        
        return $rows;
    }
    
    private function formatReportFData(array $rawData): array
    {
        if (empty($rawData)) {
            return [
                ['', 'Aucune donnée disponible'],
                ['NATURE', 'Veuillez exécuter la synchronisation OLAP']
            ];
        }
        
        // Structure matching the image (same as KPI E but for Ayant Droits):
        // Row 0: Header ['', 'DAS1', 'DAS2', ...] - header with DAS codes
        // Row 1+: Data rows [ContractType, percentage1, percentage2, ...] - one row per contract type
        
        // Only include the 16 DAS codes from the example, in exact order
        $targetDasCodes = ['DSOI', 'DENS', 'EPHR', 'DING', 'DSPR', 'DNUM', 'DAPR', 'DGST', 'DSIG', 'DRST', 'DASO', 'EING', 'EPRD', 'DEXP', 'DPRD', 'DPHR'];
        
        // Build data map: contract_type => [das_code => completion_percentage]
        $dataMap = [];
        foreach ($rawData as $row) {
            $contractType = trim($row['contract_type'] ?? '');
            $dasCode = trim(strtoupper($row['das_code'] ?? ''));
            $completion = (float)($row['completion_percentage'] ?? 0);
            
            if (!empty($contractType) && !empty($dasCode)) {
                if (!isset($dataMap[$contractType])) {
                    $dataMap[$contractType] = [];
                }
                $dataMap[$contractType][$dasCode] = $completion;
            }
        }
        
        // Get all contract types (sorted)
        $contractTypes = array_keys($dataMap);
        sort($contractTypes);
        
        // Build header row
        $header = [''];
        $header = array_merge($header, $targetDasCodes);
        
        // Build data rows
        $rows = [$header];
        foreach ($contractTypes as $contractType) {
            $contractRow = [$contractType];
            
            foreach ($targetDasCodes as $dasCode) {
                $completion = $dataMap[$contractType][$dasCode] ?? 0.0;
                $contractRow[] = number_format($completion, 2) . '%';
            }
            
            $rows[] = $contractRow;
        }
        
        return $rows;
    }
}

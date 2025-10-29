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
        
        // Si c'est un employé, afficher son dashboard personnel
        if (in_array('ROLE_EMPLOYEE', $roles)) {
            return $this->renderEmployeeDashboard($user, $contratRepository, $dossierRepository, $documentRepository, $documentRequirementService);
        }
        
        // Si c'est un administrateur RH, rediriger vers son dashboard spécifique
        if (in_array('ROLE_ADMINISTRATEUR_RH', $roles)) {
            return $this->redirectToRoute('administrateur_rh_dashboard');
        }
        
        // Si c'est un manager, rediriger vers son dashboard spécifique
        if (in_array('ROLE_MANAGER', $roles)) {
            return $this->redirectToRoute('manager_dashboard');
        }
        
        // Sinon, afficher le dashboard global pour les responsables RH
        // KPI généraux
        $kpis = [
            'total_employees' => $employeRepository->count([]),
            'total_dossiers' => $dossierRepository->count([]),
            'total_documents' => $documentRepository->count([]),
            'total_demandes' => $demandeRepository->count([]),
            'total_reclamations' => $reclamationRepository->count([]),
            'total_contrats' => $contratRepository->count([]),
        ];

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
    public function getReport(string $reportType, KpiService $kpiService): Response
    {
        try {
            switch ($reportType) {
                case 'rapport-a':
                    $data = $this->getPlaceholderReport('A. Performance par Nature de Contrat');
                    break;
                case 'rapport-b':
                    $data = $this->getPlaceholderReport('B. Performance par Organisation');
                    break;
                case 'rapport-c':
                    $data = $this->getPlaceholderReport('C. Détails par DAS');
                    break;
                case 'rapport-d':
                    $data = $this->getPlaceholderReport('D. Détails par Contrat');
                    break;
                case 'rapport-e':
                    $data = $this->getPlaceholderReport('E. Matrice Personnel');
                    break;
                case 'rapport-f':
                    $data = $this->getPlaceholderReport('F. Matrice Ayant Droits');
                    break;
                default:
                    return $this->json(['error' => 'Invalid report type'], 400);
            }
            
            return $this->json($data);
        } catch (\Exception $e) {
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
        if (empty($rawData)) {
            return [
                ['', ''],
                ['DOSSIER PERSONNEL', 'Avancement', 'N/A'],
                ['', 'Nombre de documents manquants', 'N/A'],
                ['AYANT DROITS', 'Avancement', 'N/A'],
                ['', 'Nombre de documents manquants', 'N/A']
            ];
        }
        
        // Build header
        $header = ['', ''];
        foreach ($rawData as $row) {
            $header[] = $row['contract_type'] ?? '';
        }
        
        // Build data rows
        $personalRow = ['DOSSIER PERSONNEL', 'Avancement'];
        $missingPersonal = ['', 'Nombre de documents manquants'];
        $havingRightsRow = ['AYANT DROITS', 'Avancement'];
        $missingHavingRights = ['', 'Nombre de documents manquants'];
        
        foreach ($rawData as $row) {
            $personalRow[] = ($row['personnel']['completion_percentage'] ?? 0) . '%';
            $missingPersonal[] = $row['personnel']['missing_documents'] ?? 0;
            $havingRightsRow[] = ($row['ayant_droits']['completion_percentage'] ?? 0) . '%';
            $missingHavingRights[] = $row['ayant_droits']['missing_documents'] ?? 0;
        }
        
        return [
            $header,
            $personalRow,
            $missingPersonal,
            $havingRightsRow,
            $missingHavingRights
        ];
    }
    
    private function formatReportBData(array $rawData): array
    {
        if (empty($rawData)) {
            return [
                ['', ''],
                ['DOSSIER PERSONNEL', 'Avancement', 'N/A'],
                ['AYANT DROITS', 'Avancement', 'N/A']
            ];
        }
        
        // Build header
        $header = ['', ''];
        foreach ($rawData as $row) {
            $header[] = $row['das_name'] ?? '';
        }
        
        // Build data rows
        $personalRow = ['DOSSIER PERSONNEL', 'Avancement'];
        $havingRightsRow = ['AYANT DROITS', 'Avancement'];
        
        foreach ($rawData as $row) {
            $personalRow[] = ($row['personnel']['completion_percentage'] ?? 0) . '%';
            $havingRightsRow[] = ($row['ayant_droits']['completion_percentage'] ?? 0) . '%';
        }
        
        return [
            $header,
            $personalRow,
            $havingRightsRow
        ];
    }
    
    private function formatReportCData(array $rawData): array
    {
        if (empty($rawData)) {
            return [['DAS', 'No data']];
        }
        
        // Build header row
        $header = ['DAS'];
        $docNames = [];
        if (!empty($rawData[0]['document_stats'])) {
            $docNames = array_keys($rawData[0]['document_stats']);
            $header = array_merge($header, $docNames);
        }
        
        // Build data rows
        $rows = [$header];
        foreach ($rawData as $row) {
            $dasRow = [$row['das_name'] ?? ''];
            foreach ($docNames as $docName) {
                $dasRow[] = ($row['document_stats'][$docName] ?? 0) . '%';
            }
            $rows[] = $dasRow;
        }
        
        return $rows;
    }
    
    private function formatReportDData(array $rawData): array
    {
        if (empty($rawData)) {
            return [['Type Contrat', 'No data']];
        }
        
        // Similar to formatReportCData
        $header = ['Type Contrat'];
        $docNames = [];
        if (!empty($rawData[0]['document_stats'])) {
            $docNames = array_keys($rawData[0]['document_stats']);
            $header = array_merge($header, $docNames);
        }
        
        $rows = [$header];
        foreach ($rawData as $row) {
            $contractRow = [$row['contract_type'] ?? ''];
            foreach ($docNames as $docName) {
                $contractRow[] = ($row['document_stats'][$docName] ?? 0) . '%';
            }
            $rows[] = $contractRow;
        }
        
        return $rows;
    }
    
    private function formatReportEData(array $rawData): array
    {
        // Placeholder - implement based on actual data structure
        return [['Employee', 'Document', '%'], ['N/A', 'N/A', '0%']];
    }
    
    private function formatReportFData(array $rawData): array
    {
        // Placeholder - implement based on actual data structure
        return [['Employee', 'Document', '%'], ['N/A', 'N/A', '0%']];
    }
}

<?php

namespace App\Controller;

use App\Repository\EmployeRepository;
use App\Repository\DossierRepository;
use App\Repository\DocumentRepository;
use App\Repository\DemandeRepository;
// TypeDocumentRepository supprimé car l'entité TypeDocument n'existe plus
use App\Repository\EmployeeContratRepository;
use App\Service\DocumentRequirementService;
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

        return $this->render('dashboard/index.html.twig', [
            'kpis' => $kpis,
            'dossiers_stats' => $dossiers_stats,
            'obligatory_docs_count' => $obligatory_docs_count,
            'demandes_stats' => $demandes_stats,
            'contrats_stats' => $contrats_stats,
            'recent_documents' => $recent_documents,
            'recent_demandes' => $recent_demandes,
        ]);
    }

    private function renderEmployeeDashboard($employee, EmployeeContratRepository $contratRepository, DossierRepository $dossierRepository, DocumentRepository $documentRepository, DocumentRequirementService $documentRequirementService): Response
    {
        // Récupérer les informations de l'employé connecté
        $contrats = $contratRepository->findBy(['employe' => $employee]);
        $dossiers = $dossierRepository->findBy(['employe' => $employee]);
        // Documents ne sont plus liés aux dossiers, donc on récupère tous les documents
        $documents = $documentRepository->findAll();

        // Calculer le statut réel de chaque dossier basé sur les documents requis
        $dossiersWithRealStatus = [];
        foreach ($dossiers as $dossier) {
            // Use the same method as dossierDetail - get requirements for the employee
            $documentRequirements = $documentRequirementService->getEmployeeDocumentRequirements($employee);
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

        // Calculate meaningful KPIs for the dashboard
        $documentRequirements = $documentRequirementService->getEmployeeDocumentRequirements($employee);
        $requiredDocs = array_filter($documentRequirements, function($req) { return $req['required']; });
        $uploadedRequiredDocs = array_filter($requiredDocs, function($req) { return $req['uploaded']; });
        $completionRate = count($requiredDocs) > 0 ? (count($uploadedRequiredDocs) / count($requiredDocs) * 100) : 100;
        
        // Get documents in employee's dossier (only their own documents)
        $employeeDocuments = [];
        if ($employee->getDossier()) {
            $employeeDocuments = $employee->getDossier()->getDocuments()->toArray();
        }
        
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
}

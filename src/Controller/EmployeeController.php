<?php

namespace App\Controller;

use App\Entity\Employe;
use App\Entity\Demande;
use App\Repository\EmployeRepository;
use App\Repository\EmployeeContratRepository;
use App\Repository\DossierRepository;
use App\Repository\DocumentRepository;
use App\Repository\DemandeRepository;
use App\Form\DemandeType;
use App\Service\DocumentRequirementService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Knp\Component\Pager\PaginatorInterface;

#[Route('/employee')]
#[IsGranted('ROLE_EMPLOYEE')]
class EmployeeController extends AbstractController
{
    #[Route('/dashboard', name: 'employee_dashboard')]
    public function dashboard(
        EmployeRepository $employeRepository,
        EmployeeContratRepository $contratRepository,
        DossierRepository $dossierRepository,
        DocumentRepository $documentRepository,
        DocumentRequirementService $documentRequirementService
    ): Response {
        $employee = $this->getUser();
        
        // Récupérer les informations de l'employé connecté
        $contrats = $contratRepository->findBy(['employe' => $employee]);
        $dossiers = $dossierRepository->findBy(['employe' => $employee]);
        // Get only documents for this employee's dossier
        $documents = [];
        if ($employee->getDossier()) {
            $documents = $employee->getDossier()->getDocuments()->toArray();
        }

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

    #[Route('/profile', name: 'employee_profile')]
    public function profile(): Response
    {
        $employee = $this->getUser();

        $response = $this->render('employee/profile.html.twig', [
            'employee' => $employee
        ]);
        
        $response->headers->set('Cache-Control', 'no-cache, no-store, must-revalidate, private');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', '0');
        
        return $response;
    }

    #[Route('/contrats', name: 'employee_contrats')]
    public function contrats(Request $request, EmployeeContratRepository $contratRepository, PaginatorInterface $paginator): Response
    {
        $employee = $this->getUser();
        
        // Créer une query pour les contrats de l'employé
        $contratsQuery = $contratRepository->createQueryBuilder('ec')
            ->where('ec.employe = :employee')
            ->setParameter('employee', $employee)
            ->orderBy('ec.dateDebut', 'DESC');

        // Paginer les résultats - 10 éléments par page
        $contrats = $paginator->paginate(
            $contratsQuery,
            $request->query->getInt('page', 1),
            10
        );

        $response = $this->render('employee/contrats.html.twig', [
            'contrats' => $contrats
        ]);
        
        $response->headers->set('Cache-Control', 'no-cache, no-store, must-revalidate, private');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', '0');
        
        return $response;
    }

    #[Route('/dossiers', name: 'employee_dossiers')]
    public function dossiers(DossierRepository $dossierRepository): Response
    {
        $employee = $this->getUser();
        
        // Récupérer le dossier de l'employé
        $dossier = $dossierRepository->findOneBy(['employe' => $employee]);
        
        if (!$dossier) {
            $this->addFlash('error', 'Aucun dossier trouvé pour votre compte !');
            return $this->redirectToRoute('employee_dashboard');
        }
        
        // Rediriger directement vers le détail du dossier
        return $this->redirectToRoute('employee_dossier_detail', ['id' => $dossier->getId()]);
    }

    #[Route('/documents', name: 'employee_documents')]
    public function documents(Request $request, DocumentRepository $documentRepository): Response
    {
        $employee = $this->getUser();
        
        $search = $request->query->get('search', '');
        
        if ($search) {
            $documents = $documentRepository->findBySearch($search);
        } else {
            // Get only documents for this employee's dossier (not all documents)
            if ($employee->getDossier()) {
                $documents = $employee->getDossier()->getDocuments()->toArray();
            } else {
                $documents = [];
            }
        }

        $response = $this->render('employee/documents.html.twig', [
            'documents' => $documents,
            'search' => $search
        ]);
        
        $response->headers->set('Cache-Control', 'no-cache, no-store, must-revalidate, private');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', '0');
        
        return $response;
    }


    #[Route('/demandes', name: 'employee_demandes')]
    public function mesDemandes(Request $request, DemandeRepository $demandeRepository, PaginatorInterface $paginator): Response
    {
        $employee = $this->getUser();
        
        // Créer une query pour les demandes de l'employé
        $demandesQuery = $demandeRepository->createQueryBuilder('d')
            ->where('d.employe = :employee')
            ->setParameter('employee', $employee)
            ->orderBy('d.dateCreation', 'DESC');

        // Paginer les résultats - 10 éléments par page
        $demandes = $paginator->paginate(
            $demandesQuery,
            $request->query->getInt('page', 1),
            10
        );

        return $this->render('employee/demandes.html.twig', [
            'demandes' => $demandes
        ]);
    }

    #[Route('/demandes/nouvelle', name: 'employee_nouvelle_demande')]
    public function nouvelleDemande(Request $request, EntityManagerInterface $entityManager): Response
    {
        $demande = new Demande();
        $demande->setEmploye($this->getUser());

        $form = $this->createForm(DemandeType::class, $demande);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($demande);
            $entityManager->flush();

            $this->addFlash('success', 'Votre demande/réclamation a été envoyée avec succès !');
            return $this->redirectToRoute('employee_demandes');
        }

        return $this->render('employee/nouvelle_demande.html.twig', [
            'form' => $form->createView()
        ]);
    }

    #[Route('/demandes/{id}', name: 'employee_voir_demande')]
    public function voirDemande(int $id, DemandeRepository $demandeRepository): Response
    {
        $employee = $this->getUser();
        $demande = $demandeRepository->find($id);

        if (!$demande || $demande->getEmploye() !== $employee) {
            $this->addFlash('error', 'Demande/réclamation non trouvée !');
            return $this->redirectToRoute('employee_demandes');
        }

        return $this->render('employee/voir_demande.html.twig', [
            'demande' => $demande
        ]);
    }


    #[Route('/dossiers/{id}', name: 'employee_dossier_detail')]
    public function dossierDetail(
        int $id, 
        DossierRepository $dossierRepository, 
        DocumentRequirementService $documentRequirementService
    ): Response {
        $employee = $this->getUser();
        $dossier = $dossierRepository->find($id);
        
        if (!$dossier || $dossier->getEmploye() !== $employee) {
            $this->addFlash('error', 'Dossier non trouvé ou non autorisé !');
            return $this->redirectToRoute('employee_dossiers');
        }
        
        // Get document requirements based on employee's contracts for this folder
        $documentRequirements = $documentRequirementService->getEmployeeDocumentRequirements($employee);
        $completionPercentage = $documentRequirementService->getCompletionPercentage($employee);
        
        // Debug: Add flash message to see what we get
        $this->addFlash('info', 'Found ' . count($documentRequirements) . ' document requirements for employee with ' . count($employee->getActiveContracts()) . ' active contracts.');
        
        // Show all document requirements for this folder (both existing and new)
        $folderDocumentRequirements = [];
        foreach ($documentRequirements as $requirement) {
            // Always add the requirement, whether document exists or not
            $folderDocumentRequirements[] = $requirement;
        }

        $response = $this->render('employee/dossier_detail.html.twig', [
            'dossier' => $dossier,
            'documentRequirements' => $folderDocumentRequirements,
            'completionPercentage' => $completionPercentage
        ]);
        
        $response->headers->set('Cache-Control', 'no-cache, no-store, must-revalidate, private');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', '0');
        
        return $response;
    }


}

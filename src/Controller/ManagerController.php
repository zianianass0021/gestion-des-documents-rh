<?php

namespace App\Controller;

use App\Entity\Reclamation;
use App\Entity\Employe;
use App\Form\ReclamationType;
use App\Repository\ReclamationRepository;
use App\Repository\EmployeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/manager')]
#[IsGranted('ROLE_MANAGER')]
class ManagerController extends AbstractController
{
    #[Route('/dashboard', name: 'manager_dashboard')]
    public function dashboard(ReclamationRepository $reclamationRepository): Response
    {
        $manager = $this->getUser();
        
        // Récupérer les réclamations créées par ce manager
        $mesReclamations = $reclamationRepository->findBy(['manager' => $manager], ['dateCreation' => 'DESC']);
        
        // Calculer les KPIs
        $totalReclamations = count($mesReclamations);
        $reclamationsEnAttente = count(array_filter($mesReclamations, fn($r) => $r->getStatut() === 'en_attente'));
        $reclamationsTraitees = count(array_filter($mesReclamations, fn($r) => $r->getStatut() === 'traitee'));
        $reclamationsCeMois = count(array_filter($mesReclamations, fn($r) => 
            $r->getDateCreation()->format('Y-m') === (new \DateTime())->format('Y-m')
        ));
        
        // Récupérer les 5 réclamations les plus récentes
        $reclamationsRecentes = array_slice($mesReclamations, 0, 5);
        
        return $this->render('manager/dashboard.html.twig', [
            'totalReclamations' => $totalReclamations,
            'reclamationsEnAttente' => $reclamationsEnAttente,
            'reclamationsTraitees' => $reclamationsTraitees,
            'reclamationsCeMois' => $reclamationsCeMois,
            'reclamationsRecentes' => $reclamationsRecentes,
        ]);
    }

    #[Route('/reclamations', name: 'manager_reclamations')]
    public function mesReclamations(Request $request, ReclamationRepository $reclamationRepository, PaginatorInterface $paginator): Response
    {
        $manager = $this->getUser();
        
        // Get search and filter parameters
        $search = $request->query->get('search', '');
        $typeFilter = $request->query->get('type', 'all');
        $statutFilter = $request->query->get('statut', 'all');
        
        $reclamationsQuery = $reclamationRepository->findByManagerQuery($manager, $search, $typeFilter, $statutFilter);
        $perPage = min(max($request->query->getInt('perPage', 10), 10), 100); // Between 10 and 100
        
        // Paginate the results
        $reclamations = $paginator->paginate(
            $reclamationsQuery,
            $request->query->getInt('page', 1),
            $perPage
        );
        
        return $this->render('manager/reclamations.html.twig', [
            'reclamations' => $reclamations,
            'perPage' => $perPage,
            'search' => $search,
            'typeFilter' => $typeFilter,
            'statutFilter' => $statutFilter,
        ]);
    }

    #[Route('/api/search-employees', name: 'manager_api_search_employees', methods: ['GET'])]
    public function searchEmployees(Request $request, EmployeRepository $employeRepository): Response
    {
        $search = trim($request->query->get('search', ''));
        
        // Recherche dès 1 caractère
        if (strlen($search) < 1) {
            return $this->json(['employees' => []]);
        }
        
        try {
            // Récupérer le manager connecté et ses dossiers gérés
            $manager = $this->getUser();
            $dossiersGeres = null;
            
            if ($manager instanceof \App\Entity\Employe) {
                $dossiersGeres = $manager->getDossiersGeres();
                // Migration depuis l'ancien format si nécessaire
                if (empty($dossiersGeres) && $manager->getDossierGere()) {
                    $dossiersGeres = [$manager->getDossierGere()];
                }
            }
            
            // Si le manager n'a pas de dossiers gérés, retourner une erreur
            if (empty($dossiersGeres)) {
                error_log('Manager sans dossiers gérés - Manager ID: ' . ($manager ? $manager->getId() : 'null'));
                return $this->json([
                    'employees' => [], 
                    'error' => 'Aucun dossier assigné à ce manager. Veuillez contacter l\'administrateur.'
                ], 400);
            }
            
            error_log('Recherche employés - Terme: "' . $search . '" - Dossiers gérés: ' . implode(', ', $dossiersGeres));
            
            // Filtrer par dossiers gérés si le manager en a
            $employees = $employeRepository->searchActiveEmployeesByRole('ROLE_EMPLOYEE', $search, 50, null, $dossiersGeres);
            
            error_log('Employés trouvés: ' . count($employees));
            
            $results = [];
            foreach ($employees as $employee) {
                $results[] = [
                    'id' => $employee['id'],
                    'label' => sprintf('%s %s (%s)', $employee['prenom'], $employee['nom'], $employee['email']),
                    'nom' => $employee['nom'],
                    'prenom' => $employee['prenom'],
                    'email' => $employee['email'],
                ];
            }
            
            return $this->json(['employees' => $results]);
        } catch (\Exception $e) {
            // Log l'erreur pour le débogage
            error_log('Erreur dans searchEmployees: ' . $e->getMessage() . ' | Trace: ' . $e->getTraceAsString());
            return $this->json([
                'employees' => [], 
                'error' => 'Une erreur est survenue lors de la recherche: ' . $e->getMessage()
            ], 500);
        }
    }

    #[Route('/reclamations/nouvelle', name: 'manager_add_reclamation')]
    public function addReclamation(Request $request, EntityManagerInterface $entityManager, EmployeRepository $employeRepository): Response
    {
        $reclamation = new Reclamation();
        $reclamation->setManager($this->getUser());
        
        $form = $this->createForm(ReclamationType::class, $reclamation);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Vérifier que l'employé existe et est valide
            $employee = $reclamation->getEmploye();
            if (!$employee) {
                $this->addFlash('error', 'Veuillez sélectionner un employé.');
                return $this->render('manager/add_reclamation.html.twig', [
                    'form' => $form->createView(),
                ]);
            }
            
            if (!in_array('ROLE_EMPLOYEE', $employee->getRoles())) {
                $this->addFlash('error', 'L\'employé sélectionné n\'est pas valide.');
                return $this->render('manager/add_reclamation.html.twig', [
                    'form' => $form->createView(),
                ]);
            }
            
            // Vérifier que l'employé appartient à un des dossiers gérés par le manager
            $manager = $this->getUser();
            if ($manager instanceof \App\Entity\Employe) {
                $dossiersGeres = $manager->getDossiersGeres();
                // Migration depuis l'ancien format si nécessaire
                if (empty($dossiersGeres) && $manager->getDossierGere()) {
                    $dossiersGeres = [$manager->getDossierGere()];
                }
                
                if (!empty($dossiersGeres)) {
                    // Vérifier si le dossier de l'employé correspond à un des dossiers gérés par le manager
                    $dossier = $employee->getDossier();
                    
                    if (!$dossier || !in_array($dossier->getDossierCode(), $dossiersGeres, true)) {
                        $this->addFlash('error', 'Vous ne pouvez créer des réclamations que pour les employés dont le dossier a l\'un des types suivants : ' . implode(', ', $dossiersGeres) . '.');
                        return $this->render('manager/add_reclamation.html.twig', [
                            'form' => $form->createView(),
                        ]);
                    }
                }
            }
            // Gérer l'upload du document
            $documentFile = $form->get('document')->getData();
            
            if ($documentFile) {
                // Validation manuelle du fichier
                $originalFilename = $documentFile->getClientOriginalName();
                $extension = strtolower(pathinfo($originalFilename, PATHINFO_EXTENSION));
                $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx'];
                
                // Vérifier l'extension
                if (!in_array($extension, $allowedExtensions)) {
                    $this->addFlash('error', 'Type de fichier non autorisé. Formats acceptés : JPG, PNG, GIF, PDF, DOC, DOCX');
                    return $this->render('manager/add_reclamation.html.twig', [
                        'form' => $form->createView(),
                    ]);
                }
                
                // Vérifier la taille (5MB max)
                if ($documentFile->getSize() > 5 * 1024 * 1024) {
                    $this->addFlash('error', 'Le fichier est trop volumineux. Taille maximale : 5 MB');
                    return $this->render('manager/add_reclamation.html.twig', [
                        'form' => $form->createView(),
                    ]);
                }
                
                $safeFilename = $this->sanitizeFilename(pathinfo($originalFilename, PATHINFO_FILENAME));
                $newFilename = $safeFilename.'-'.uniqid().'.'.$extension;
                
                try {
                    $documentFile->move(
                        $this->getParameter('reclamations_directory'),
                        $newFilename
                    );
                    
                    $reclamation->setDocumentPath($this->getParameter('reclamations_directory') . $newFilename);
                    $reclamation->setDocumentType($this->getMimeTypeFromExtension($extension));
                } catch (\Exception $e) {
                    $this->addFlash('error', 'Erreur lors du téléchargement du document: ' . $e->getMessage());
                    return $this->render('manager/add_reclamation.html.twig', [
                        'form' => $form->createView(),
                    ]);
                }
            }
            
            $entityManager->persist($reclamation);
            $entityManager->flush();

            $this->addFlash('success', 'Réclamation créée avec succès !');
            return $this->redirectToRoute('manager_reclamations');
        }

        return $this->render('manager/add_reclamation.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/reclamations/{id}', name: 'manager_view_reclamation')]
    public function viewReclamation(Reclamation $reclamation): Response
    {
        // Vérifier que la réclamation appartient au manager connecté
        if ($reclamation->getManager() !== $this->getUser()) {
            throw $this->createAccessDeniedException('Vous n\'avez pas accès à cette réclamation.');
        }
        
        return $this->render('manager/view_reclamation.html.twig', [
            'reclamation' => $reclamation,
        ]);
    }

    #[Route('/api/list-employees', name: 'manager_api_list_employees', methods: ['GET'])]
    public function listEmployees(Request $request, EntityManagerInterface $entityManager): Response
    {
        $manager = $this->getUser();
        $dossiersGeres = null;
        
        if ($manager instanceof \App\Entity\Employe) {
            $dossiersGeres = $manager->getDossiersGeres();
            // Migration depuis l'ancien format si nécessaire
            if (empty($dossiersGeres) && $manager->getDossierGere()) {
                $dossiersGeres = [$manager->getDossierGere()];
            }
        }
        
        if (empty($dossiersGeres)) {
            return $this->json([
                'employees' => [],
                'total' => 0,
                'error' => 'Aucun dossier assigné à ce manager.'
            ], 400);
        }
        
        $search = trim($request->query->get('search', ''));
        $page = max(1, $request->query->getInt('page', 1));
        $perPage = 10; // 10 employés par page
        
        // Récupérer les employés assignés au manager via native SQL
        $conn = $entityManager->getConnection();
        $searchLower = $search ? '%' . strtolower($search) . '%' : '';
        
        // Requête pour compter le total
        $countSql = 'SELECT COUNT(DISTINCT e.id) as total
                FROM t_user e 
                INNER JOIN t_dossier d ON e.id = d.employe_id
                WHERE e.is_active = :active 
                AND CAST(e.roles AS TEXT) LIKE :role 
                AND d.dossier_code IS NOT NULL
                AND d.dossier_code = ANY(:dossier_codes)';
        
        $params = [
            'active' => true,
            'role' => '%ROLE_EMPLOYEE%',
            'dossier_codes' => '{' . implode(',', array_map(function($code) {
                return '"' . addslashes($code) . '"';
            }, $dossiersGeres)) . '}'
        ];
        
        if ($search) {
            $countSql .= ' AND (
                        LOWER(e.nom) LIKE :search_lower 
                        OR LOWER(e.prenom) LIKE :search_lower 
                        OR LOWER(e.email) LIKE :search_lower
                        OR LOWER(e.prenom || \' \' || e.nom) LIKE :search_lower
                        OR LOWER(e.nom || \' \' || e.prenom) LIKE :search_lower
                    )';
            $params['search_lower'] = $searchLower;
        }
        
        $countStmt = $conn->prepare($countSql);
        $countResult = $countStmt->executeQuery($params);
        $total = (int) $countResult->fetchOne();
        
        // Requête pour récupérer les employés avec pagination
        $sql = 'SELECT DISTINCT e.id, e.nom, e.prenom, e.email, d.dossier_code
                FROM t_user e 
                INNER JOIN t_dossier d ON e.id = d.employe_id
                WHERE e.is_active = :active 
                AND CAST(e.roles AS TEXT) LIKE :role 
                AND d.dossier_code IS NOT NULL
                AND d.dossier_code = ANY(:dossier_codes)';
        
        if ($search) {
            $sql .= ' AND (
                        LOWER(e.nom) LIKE :search_lower 
                        OR LOWER(e.prenom) LIKE :search_lower 
                        OR LOWER(e.email) LIKE :search_lower
                        OR LOWER(e.prenom || \' \' || e.nom) LIKE :search_lower
                        OR LOWER(e.nom || \' \' || e.prenom) LIKE :search_lower
                    )';
        }
        
        $sql .= ' ORDER BY e.nom ASC, e.prenom ASC';
        $sql .= ' LIMIT ' . $perPage . ' OFFSET ' . (($page - 1) * $perPage);
        
        $stmt = $conn->prepare($sql);
        $result = $stmt->executeQuery($params);
        $employees = $result->fetchAllAssociative();
        
        $results = [];
        foreach ($employees as $employee) {
            $results[] = [
                'id' => $employee['id'],
                'nom' => $employee['nom'],
                'prenom' => $employee['prenom'],
                'email' => $employee['email'],
                'dossier_code' => $employee['dossier_code'],
            ];
        }
        
        $totalPages = ceil($total / $perPage);
        
        return $this->json([
            'employees' => $results,
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage,
            'totalPages' => $totalPages,
            'dossiersGeres' => $dossiersGeres
        ]);
    }
    
    /**
     * Obtenir le type MIME depuis l'extension du fichier
     */
    private function getMimeTypeFromExtension(string $extension): string
    {
        $mimeTypes = [
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'pdf' => 'application/pdf',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ];
        
        return $mimeTypes[strtolower($extension)] ?? 'application/octet-stream';
    }
    
    /**
     * Nettoyer le nom de fichier pour le rendre sûr
     */
    private function sanitizeFilename(string $filename): string
    {
        // Supprimer les accents et caractères spéciaux
        $filename = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $filename);
        
        // Remplacer les caractères non alphanumériques par des underscores
        $filename = preg_replace('/[^a-zA-Z0-9_-]/', '_', $filename);
        
        // Supprimer les underscores multiples
        $filename = preg_replace('/_+/', '_', $filename);
        
        // Supprimer les underscores au début et à la fin
        $filename = trim($filename, '_');
        
        // Si le nom est vide après nettoyage, utiliser un nom par défaut
        if (empty($filename)) {
            $filename = 'document';
        }
        
        // Limiter la longueur à 50 caractères
        return strtolower(substr($filename, 0, 50));
    }
}

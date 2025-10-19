<?php

namespace App\Controller;

use App\Entity\Employe;
use App\Entity\EmployeeContrat;
use App\Entity\Dossier;
use App\Entity\Document;
use App\Entity\Demande;
use App\Entity\Reclamation;
use App\Entity\Placard;
use App\Entity\NatureContrat;
use App\Entity\Organisation;
use App\Entity\OrganisationEmployeeContrat;
use App\Form\EmployeeType;
use App\Form\DossierType;
use App\Form\DocumentType;
use App\Form\ReponseDemandeType;
use App\Form\PlacardType;
use App\Repository\EmployeRepository;
use App\Repository\EmployeeContratRepository;
use App\Repository\DossierRepository;
use App\Repository\DocumentRepository;
use App\Repository\DemandeRepository;
use App\Repository\ReclamationRepository;
use App\Repository\PlacardRepository;
use App\Repository\NatureContratRepository;
use App\Repository\NatureContratTypeDocumentRepository;
use App\Repository\OrganisationRepository;
use App\Form\OrganisationType;
use App\Form\OrganisationEmployeeContratType;
use App\Service\DocumentRequirementService;
use App\Service\KpiService;
use App\Service\ExcelGeneratorService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Knp\Component\Pager\PaginatorInterface;

#[Route('/responsable-rh')]
#[IsGranted('ROLE_RESPONSABLE_RH')]
class ResponsableRhController extends AbstractController
{
    public static function getSubscribedServices(): array
    {
        return array_merge(parent::getSubscribedServices(), [
            'form.factory' => '?Symfony\Component\Form\FormFactoryInterface',
        ]);
    }
    #[Route('/dashboard', name: 'responsable_rh_dashboard')]
    public function dashboard(): Response
    {
        // Vérifier que l'utilisateur est toujours authentifié
        if (!$this->getUser()) {
            return $this->redirectToRoute('app_login');
        }
        
        // Vérifier que l'utilisateur a le bon rôle
        if (!in_array('ROLE_RESPONSABLE_RH', $this->getUser()->getRoles())) {
            return $this->redirectToRoute('app_login');
        }
        
        // Rediriger vers le dashboard du responsable RH
        return $this->redirectToRoute('app_dashboard');
    }

    #[Route('/employes', name: 'responsable_manage_employes')]
    public function manageEmployes(Request $request, EmployeRepository $employeRepository, PaginatorInterface $paginator): Response
    {
        // Vérifier que l'utilisateur est toujours authentifié
        if (!$this->getUser()) {
            return $this->redirectToRoute('app_login');
        }
        
        // Vérifier que l'utilisateur a le bon rôle
        if (!in_array('ROLE_RESPONSABLE_RH', $this->getUser()->getRoles())) {
            return $this->redirectToRoute('app_login');
        }

        // Récupérer les paramètres de recherche et filtrage
        $showAll = $request->query->getBoolean('show_all', false);
        $search = $request->query->get('search', '');
        
        // Récupérer les employés selon les filtres
        if ($showAll) {
            if ($search) {
                $allEmployees = $employeRepository->findByRoleAndSearchQuery('ROLE_EMPLOYEE', $search);
            } else {
                $allEmployees = $employeRepository->findByRoleQuery('ROLE_EMPLOYEE');
            }
        } else {
            if ($search) {
                $allEmployees = $employeRepository->findActiveByRoleAndSearchQuery('ROLE_EMPLOYEE', $search);
            } else {
                $allEmployees = $employeRepository->findActiveByRoleQuery('ROLE_EMPLOYEE');
            }
        }

        // Pagination manuelle
        $page = $request->query->getInt('page', 1);
        $perPage = 10;
        $totalCount = count($allEmployees);
        $totalPages = ceil($totalCount / $perPage);
        $offset = ($page - 1) * $perPage;
        $employeesData = array_slice($allEmployees, $offset, $perPage);

        // Créer un objet de pagination personnalisé
        $employees = (object) [
            'items' => $employeesData,
            'current' => $page,
            'pageCount' => $totalPages,
            'totalCount' => $totalCount,
            'firstItemNumber' => $offset + 1,
            'lastItemNumber' => min($offset + $perPage, $totalCount),
            'route' => 'responsable_manage_employes',
            'queryParams' => $request->query->all(),
            'pageParameterName' => 'page'
        ];

        $response = $this->render('responsable-rh/employes.html.twig', [
            'employees' => $employees,
            'showAll' => $showAll,
            'search' => $search
        ]);
        
        $response->headers->set('Cache-Control', 'no-cache, no-store, must-revalidate, private');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', '0');
        
        return $response;
    }

    #[Route('/employes/add', name: 'responsable_rh_add_employe')]
    public function addEmployee(Request $request, EntityManagerInterface $entityManager, UserPasswordHasherInterface $passwordHasher, NatureContratRepository $natureContratRepository, ExcelGeneratorService $excelGenerator): Response
    {
        if (!$this->getUser()) {
            return $this->redirectToRoute('app_login');
        }

        $employee = new Employe();
        $form = $this->createForm(EmployeeType::class, $employee);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Définir le rôle d'employé
            $employee->setRoles(['ROLE_EMPLOYEE']);
            
            // Générer le username à partir de l'email si non défini
            if (!$employee->getUsername()) {
                $username = explode('@', $employee->getEmail())[0];
                $employee->setUsername($username);
            }
            
            // Hacher le mot de passe
            $plainPassword = $form->get('password')->getData();
            if ($plainPassword) {
                $hashedPassword = $passwordHasher->hashPassword($employee, $plainPassword);
                $employee->setPassword($hashedPassword);
            }

            // Créer le contrat
            $contrat = new EmployeeContrat();
            $contrat->setEmploye($employee);
            $contrat->setNatureContrat($form->get('natureContrat')->getData());
            
            // Vérifier que la date de début n'est pas nulle
            $dateDebut = $form->get('dateDebutContrat')->getData();
            if ($dateDebut === null) {
                $this->addFlash('error', 'La date de début du contrat est obligatoire.');
                return $this->render('responsable-rh/add_employe.html.twig', [
                    'form' => $form->createView(),
                ]);
            }
            $contrat->setDateDebut($dateDebut);
            
            // La date de fin est optionnelle
            $dateFin = $form->get('dateFinContrat')->getData();
            if ($dateFin !== null) {
                $contrat->setDateFin($dateFin);
            }
            
            $contrat->setStatut('actif');

            $entityManager->persist($employee);
            $entityManager->persist($contrat);
            $entityManager->flush();

            // Régénérer automatiquement le fichier Excel
            $excelGenerator->generateModuleExcel();

            $this->addFlash('success', 'Employé créé avec succès avec son contrat.');
            return $this->redirectToRoute('responsable_manage_employes');
        }

        return $this->render('responsable-rh/add_employe.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/employes/ajouter', name: 'responsable_add_employe')]
    public function addEmploye(Request $request, EntityManagerInterface $entityManager, UserPasswordHasherInterface $passwordHasher, NatureContratRepository $natureContratRepository, OrganisationRepository $organisationRepository, ExcelGeneratorService $excelGenerator): Response
    {
        // Vérifier que l'utilisateur est toujours authentifié
        if (!$this->getUser()) {
            return $this->redirectToRoute('app_login');
        }
        
        // Vérifier que l'utilisateur a le bon rôle
        if (!in_array('ROLE_RESPONSABLE_RH', $this->getUser()->getRoles())) {
            return $this->redirectToRoute('app_login');
        }

        $employee = new Employe();
        $form = $this->createForm(EmployeeType::class, $employee);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Définir automatiquement le rôle d'employé
            $employee->setRoles(['ROLE_EMPLOYEE']);
            
            // Générer le username à partir de l'email si non défini
            if (!$employee->getUsername()) {
                $username = explode('@', $employee->getEmail())[0];
                $employee->setUsername($username);
            }
            
            // Hasher le mot de passe (obligatoire pour les nouveaux utilisateurs)
            $plainPassword = $form->get('password')->getData();
            $hashedPassword = $passwordHasher->hashPassword($employee, $plainPassword);
            $employee->setPassword($hashedPassword);
            
            $entityManager->persist($employee);
            $entityManager->flush();

               // Créer le contrat principal si les données sont fournies
               $natureContratId = $form->get('natureContrat')->getData();
               $dateDebutContrat = $form->get('dateDebutContrat')->getData();
               $dateFinContrat = $form->get('dateFinContrat')->getData();
               
               if ($natureContratId && $dateDebutContrat) {
                   $natureContrat = $natureContratRepository->find($natureContratId);
                   
                   if ($natureContrat) {
                       $contrat = new EmployeeContrat();
                       $contrat->setEmploye($employee);
                       $contrat->setNatureContrat($natureContrat->getDesignation());
                       $contrat->setDateDebut($dateDebutContrat);
                       $contrat->setDateFin($dateFinContrat);
                       $contrat->setStatut('actif');
                       
                       $entityManager->persist($contrat);
                       
                       // Assigner à l'organisation si sélectionnée
                       $organisationId = $form->get('organisation')->getData();
                       if ($organisationId) {
                           $organisation = $organisationRepository->find($organisationId);
                           
                           if ($organisation) {
                               $orgEmployeeContrat = new OrganisationEmployeeContrat();
                               $orgEmployeeContrat->setEmployeeContrat($contrat);
                               $orgEmployeeContrat->setOrganisation($organisation);
                               $orgEmployeeContrat->setDateDebut($dateDebutContrat);
                               $orgEmployeeContrat->setDateFin($dateFinContrat);
                               
                               $entityManager->persist($orgEmployeeContrat);
                           }
                       }
                   }
               }
               
               // Créer le contrat secondaire si les données sont fournies
               $natureContratId2 = $form->get('natureContrat2')->getData();
               $dateDebutContrat2 = $form->get('dateDebutContrat2')->getData();
               $dateFinContrat2 = $form->get('dateFinContrat2')->getData();
               
               if ($natureContratId2 && $dateDebutContrat2) {
                   $natureContrat2 = $natureContratRepository->find($natureContratId2);
                   
                   if ($natureContrat2) {
                       $contrat2 = new EmployeeContrat();
                       $contrat2->setEmploye($employee);
                       $contrat2->setNatureContrat($natureContrat2->getDesignation());
                       $contrat2->setDateDebut($dateDebutContrat2);
                       $contrat2->setDateFin($dateFinContrat2);
                       $contrat2->setStatut('actif');
                       
                       $entityManager->persist($contrat2);
                       
                       // Assigner à l'organisation secondaire si sélectionnée
                       $organisationId2 = $form->get('organisation2')->getData();
                       if ($organisationId2) {
                           $organisation2 = $organisationRepository->find($organisationId2);
                           
                           if ($organisation2) {
                               $orgEmployeeContrat2 = new OrganisationEmployeeContrat();
                               $orgEmployeeContrat2->setEmployeeContrat($contrat2);
                               $orgEmployeeContrat2->setOrganisation($organisation2);
                               $orgEmployeeContrat2->setDateDebut($dateDebutContrat2);
                               $orgEmployeeContrat2->setDateFin($dateFinContrat2);
                               
                               $entityManager->persist($orgEmployeeContrat2);
                           }
                       }
                   }
               }
               
               $entityManager->flush();

            // Régénérer automatiquement le fichier Excel
            $excelGenerator->generateModuleExcel();

            $this->addFlash('success', 'Employé ajouté avec succès !');
            return $this->redirectToRoute('responsable_manage_employes');
        }

        $response = $this->render('responsable-rh/add_employe.html.twig', [
            'form' => $form->createView()
        ]);
        
        $response->headers->set('Cache-Control', 'no-cache, no-store, must-revalidate, private');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', '0');
        
        return $response;
    }


    #[Route('/employes/toggle-status/{id}', name: 'responsable_toggle_employe_status')]
    public function toggleEmployeStatus(Employe $employee, EntityManagerInterface $entityManager, ExcelGeneratorService $excelGenerator): Response
    {
        // Vérifier que l'utilisateur est toujours authentifié
        if (!$this->getUser()) {
            return $this->redirectToRoute('app_login');
        }
        
        // Vérifier que l'utilisateur a le bon rôle
        if (!in_array('ROLE_RESPONSABLE_RH', $this->getUser()->getRoles())) {
            return $this->redirectToRoute('app_login');
        }

        // Vérifier que c'est bien un employé
        if (!in_array('ROLE_EMPLOYEE', $employee->getRoles())) {
            $this->addFlash('error', 'Utilisateur non trouvé ou non autorisé.');
            return $this->redirectToRoute('responsable_manage_employes');
        }

        // Toggle le statut actif/inactif
        $employee->setIsActive(!$employee->isActive());
        $entityManager->flush();

        // Régénérer automatiquement le fichier Excel
        $excelGenerator->generateModuleExcel();

        $status = $employee->isActive() ? 'activé' : 'désactivé';
        $this->addFlash('success', "Employé {$status} avec succès !");
        return $this->redirectToRoute('responsable_manage_employes');
    }

    #[Route('/employes/{id}/details', name: 'responsable_view_employe_details')]
    public function viewEmployeDetails(int $id, EmployeRepository $employeRepository): Response
    {
        if (!$this->getUser() || !in_array('ROLE_RESPONSABLE_RH', $this->getUser()->getRoles())) {
            return $this->redirectToRoute('app_login');
        }

        $employe = $employeRepository->find($id);
        if (!$employe) {
            $this->addFlash('error', 'Employé non trouvé !');
            return $this->redirectToRoute('responsable_manage_employes');
        }

        $response = $this->render('responsable-rh/employe_details.html.twig', [
            'employe' => $employe
        ]);
        
        $response->headers->set('Cache-Control', 'no-cache, no-store, must-revalidate, private');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', '0');
        
        return $response;
    }

    // Gestion des dossiers
    #[Route('/dossiers', name: 'responsable_manage_dossiers')]
    public function manageDossiers(Request $request, DossierRepository $dossierRepository, PaginatorInterface $paginator): Response
    {
        if (!$this->getUser() || !in_array('ROLE_RESPONSABLE_RH', $this->getUser()->getRoles())) {
            return $this->redirectToRoute('app_login');
        }

        $search = $request->query->get('search', '');
        
        if ($search) {
            $dossiersQuery = $dossierRepository->findBySearchQuery($search);
        } else {
            $dossiersQuery = $dossierRepository->findAllQuery();
        }

        // Paginer les résultats - 10 éléments par page
        $dossiers = $paginator->paginate(
            $dossiersQuery,
            $request->query->getInt('page', 1),
            10
        );

        $response = $this->render('responsable-rh/dossiers.html.twig', [
            'dossiers' => $dossiers,
            'search' => $search
        ]);
        
        $response->headers->set('Cache-Control', 'no-cache, no-store, must-revalidate, private');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', '0');
        
        return $response;
    }

    #[Route('/dossiers/ajouter', name: 'responsable_add_dossier')]
    public function addDossier(Request $request, EntityManagerInterface $entityManager): Response
    {
        if (!$this->getUser() || !in_array('ROLE_RESPONSABLE_RH', $this->getUser()->getRoles())) {
            return $this->redirectToRoute('app_login');
        }

        $dossier = new Dossier();
        $form = $this->createForm(DossierType::class, $dossier);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Check if employee already has a dossier
            $employee = $dossier->getEmploye();
            if ($employee && $employee->getDossier()) {
                $this->addFlash('error', 'Cet employé a déjà un dossier. Un employé ne peut avoir qu\'un seul dossier.');
                return $this->redirectToRoute('responsable_add_dossier');
            }
            
            // Check if the selected user is actually an employee (not admin or responsable RH)
            if ($employee && !in_array('ROLE_EMPLOYEE', $employee->getRoles())) {
                $this->addFlash('error', 'Seuls les employés peuvent avoir un dossier. Les responsables RH et administrateurs ne peuvent pas avoir de dossier.');
                return $this->redirectToRoute('responsable_add_dossier');
            }
            
            $entityManager->persist($dossier);
            $entityManager->flush();

            $this->addFlash('success', 'Dossier ajouté avec succès !');
            return $this->redirectToRoute('responsable_manage_dossiers');
        }

        $response = $this->render('responsable-rh/add_dossier.html.twig', [
            'form' => $form->createView()
        ]);
        
        $response->headers->set('Cache-Control', 'no-cache, no-store, must-revalidate, private');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', '0');
        
        return $response;
    }

    #[Route('/dossiers/modifier/{id}', name: 'responsable_edit_dossier')]
    public function editDossier(int $id, Request $request, EntityManagerInterface $entityManager, DossierRepository $dossierRepository): Response
    {
        if (!$this->getUser() || !in_array('ROLE_RESPONSABLE_RH', $this->getUser()->getRoles())) {
            return $this->redirectToRoute('app_login');
        }

        $dossier = $dossierRepository->find($id);
        if (!$dossier) {
            $this->addFlash('error', 'Dossier non trouvé !');
            return $this->redirectToRoute('responsable_manage_dossiers');
        }

        $form = $this->createForm(DossierType::class, $dossier);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();
            $this->addFlash('success', 'Dossier modifié avec succès !');
            return $this->redirectToRoute('responsable_manage_dossiers');
        }

        return $this->render('responsable-rh/edit_dossier.html.twig', [
            'form' => $form->createView(),
            'dossier' => $dossier
        ]);
    }

    #[Route('/dossiers/supprimer/{id}', name: 'responsable_delete_dossier')]
    public function deleteDossier(int $id, EntityManagerInterface $entityManager, DossierRepository $dossierRepository): Response
    {
        if (!$this->getUser() || !in_array('ROLE_RESPONSABLE_RH', $this->getUser()->getRoles())) {
            return $this->redirectToRoute('app_login');
        }

        $dossier = $dossierRepository->find($id);
        if (!$dossier) {
            $this->addFlash('error', 'Dossier non trouvé !');
            return $this->redirectToRoute('responsable_manage_dossiers');
        }

        $entityManager->remove($dossier);
        $entityManager->flush();

        $this->addFlash('success', 'Dossier supprimé avec succès !');
        return $this->redirectToRoute('responsable_manage_dossiers');
    }

    #[Route('/dossiers/{id}/documents', name: 'responsable_view_dossier_documents')]
    public function viewDossierDocuments(int $id, DossierRepository $dossierRepository, DocumentRequirementService $documentRequirementService): Response
    {
        if (!$this->getUser() || !in_array('ROLE_RESPONSABLE_RH', $this->getUser()->getRoles())) {
            return $this->redirectToRoute('app_login');
        }

        $dossier = $dossierRepository->find($id);
        if (!$dossier) {
            $this->addFlash('error', 'Dossier non trouvé !');
            return $this->redirectToRoute('responsable_manage_dossiers');
        }

        // Get document requirements based on employee's contracts for this folder
        $documentRequirements = $documentRequirementService->getEmployeeDocumentRequirements($dossier->getEmploye());
        $completionPercentage = $documentRequirementService->getCompletionPercentage($dossier->getEmploye());

        $response = $this->render('responsable-rh/dossier_documents.html.twig', [
            'dossier' => $dossier,
            'documents' => $dossier->getDocuments(),
            'documentRequirements' => $documentRequirements,
            'completionPercentage' => $completionPercentage
        ]);
        
        $response->headers->set('Cache-Control', 'no-cache, no-store, must-revalidate, private');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', '0');
        
        return $response;
    }

    #[Route('/documents/upload', name: 'responsable_upload_document')]
    public function uploadDocument(Request $request, EntityManagerInterface $entityManager, DossierRepository $dossierRepository, DocumentRepository $documentRepository): Response
    {
        if (!$this->getUser() || !in_array('ROLE_RESPONSABLE_RH', $this->getUser()->getRoles())) {
            return $this->redirectToRoute('app_login');
        }

        $dossierId = $request->query->get('dossier_id');
        $abbreviation = $request->query->get('abbreviation');
        
        if (!$abbreviation || !$dossierId) {
            $this->addFlash('error', 'Paramètres manquants pour l\'upload du document.');
            return $this->redirectToRoute('responsable_manage_dossiers');
        }

        return $this->createDocumentFromAbbreviation($abbreviation, $dossierId, $request, $entityManager, $dossierRepository, $documentRepository);
    }

    #[Route('/documents/modifier/{id}', name: 'responsable_edit_document')]
    public function editDocument(int $id, Request $request, EntityManagerInterface $entityManager, DocumentRepository $documentRepository, NatureContratTypeDocumentRepository $matrixRepository, NatureContratRepository $natureContratRepository): Response
    {
        if (!$this->getUser() || !in_array('ROLE_RESPONSABLE_RH', $this->getUser()->getRoles())) {
            return $this->redirectToRoute('app_login');
        }

        $document = $documentRepository->find($id);
        if (!$document) {
            $this->addFlash('error', 'Document non trouvé !');
            return $this->redirectToRoute('responsable_manage_dossiers');
        }

        // Récupérer les données de la matrice pour l'affichage
        $matrixData = $matrixRepository->findAll();
        $natureContrats = $natureContratRepository->findAll();
        
        // Préparer la matrice pour l'affichage
        $displayMatrix = [];
        foreach ($matrixData as $item) {
            $displayMatrix[$item->getDocumentAbbreviation()][$item->getContractType()] = $item->isRequired();
        }

        $form = $this->createForm(DocumentType::class, $document);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $file = $form->get('file')->getData();
            
            if ($file) {
                // Supprimer l'ancien fichier s'il existe
                if ($document->getFilePath() && file_exists($document->getFilePath())) {
                    unlink($document->getFilePath());
                }
                
                $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
                $originalExtension = strtolower(pathinfo($file->getClientOriginalName(), PATHINFO_EXTENSION));
                
                // Validation de l'extension
                $allowedExtensions = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png', 'gif'];
                if (!in_array($originalExtension, $allowedExtensions)) {
                    $this->addFlash('error', 'Type de fichier non autorisé. Formats acceptés : PDF, DOC, DOCX, JPG, PNG, GIF');
                    return $this->redirectToRoute('responsable_edit_document', ['id' => $id]);
                }
                
                $safeFilename = $this->sanitizeFilename($originalFilename);
                $newFilename = $safeFilename.'-'.uniqid().'.'.$originalExtension;
                
                try {
                    $file->move(
                        $this->getParameter('documents_directory'),
                        $newFilename
                    );
                } catch (FileException $e) {
                    $this->addFlash('error', 'Erreur lors de l\'upload du fichier.');
                    return $this->redirectToRoute('responsable_edit_document', ['id' => $id]);
                }

                $document->setFilePath($this->getParameter('documents_directory') . '/' . $newFilename);
                
                // Déterminer le type MIME basé sur l'extension
                $extension = strtolower($originalExtension);
                $mimeTypes = [
                    'pdf' => 'application/pdf',
                    'doc' => 'application/msword',
                    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                    'jpg' => 'image/jpeg',
                    'jpeg' => 'image/jpeg',
                    'png' => 'image/png',
                    'gif' => 'image/gif',
                    'bmp' => 'image/bmp',
                    'tiff' => 'image/tiff',
                    'txt' => 'text/plain',
                    'xls' => 'application/vnd.ms-excel',
                    'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    'ppt' => 'application/vnd.ms-powerpoint',
                    'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
                    'zip' => 'application/zip',
                    'rar' => 'application/x-rar-compressed',
                    'mp4' => 'video/mp4',
                    'avi' => 'video/x-msvideo',
                    'mov' => 'video/quicktime',
                    'wmv' => 'video/x-ms-wmv',
                    'mp3' => 'audio/mpeg',
                    'wav' => 'audio/wav',
                    'flac' => 'audio/flac'
                ];
                $mimeType = $mimeTypes[$extension] ?? 'application/octet-stream';
                
                $document->setFileType($mimeType);
                $document->setUploadedBy($this->getUser()->getEmail());
                
                // Marquer automatiquement comme téléchargé ET ajouté quand un fichier est uploadé
                $document->setStatutTelechargement('telecharge');
                $document->setStatutAjout('ajoute');
            }
            
            $entityManager->flush();
            $this->addFlash('success', 'Document modifié avec succès !');
            return $this->redirectToRoute('responsable_manage_dossiers');
        }

        return $this->render('responsable-rh/add_document.html.twig', [
            'form' => $form->createView(),
            'document' => $document,
            'natureContrats' => $natureContrats,
            'matrixData' => $displayMatrix
        ]);
    }

    #[Route('/documents/supprimer/{id}', name: 'responsable_delete_document')]
    public function deleteDocument(int $id, EntityManagerInterface $entityManager, DocumentRepository $documentRepository): Response
    {
        if (!$this->getUser() || !in_array('ROLE_RESPONSABLE_RH', $this->getUser()->getRoles())) {
            return $this->redirectToRoute('app_login');
        }

        $document = $documentRepository->find($id);
        if (!$document) {
            $this->addFlash('error', 'Document non trouvé !');
            return $this->redirectToRoute('responsable_manage_dossiers');
        }

        // Supprimer le fichier physique s'il existe
        if ($document->getFilePath() && file_exists($document->getFilePath())) {
            unlink($document->getFilePath());
        }

        $entityManager->remove($document);
        $entityManager->flush();

        $this->addFlash('success', 'Document supprimé avec succès !');
        return $this->redirectToRoute('responsable_manage_documents');
    }

    #[Route('/documents/telecharger/{id}', name: 'responsable_download_document')]
    public function downloadDocument(int $id, DocumentRepository $documentRepository): Response
    {
        if (!$this->getUser() || !in_array('ROLE_RESPONSABLE_RH', $this->getUser()->getRoles())) {
            return $this->redirectToRoute('app_login');
        }

        $document = $documentRepository->find($id);
        if (!$document || !$document->getFilePath() || !file_exists($document->getFilePath())) {
            $this->addFlash('error', 'Document non trouvé !');
            return $this->redirectToRoute('responsable_manage_dossiers');
        }

        $response = new BinaryFileResponse($document->getFilePath());
        $filename = basename($document->getFilePath());
        $response->setContentDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            $filename
        );
        
        // Définir manuellement le type MIME pour éviter l'erreur fileinfo
        if ($document->getFileType()) {
            $response->headers->set('Content-Type', $document->getFileType());
        } else {
            // Fallback basé sur l'extension si pas de type MIME stocké
            $extension = strtolower(pathinfo($document->getFilePath(), PATHINFO_EXTENSION));
            $mimeTypes = [
                'pdf' => 'application/pdf',
                'doc' => 'application/msword',
                'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'jpg' => 'image/jpeg',
                'jpeg' => 'image/jpeg',
                'png' => 'image/png',
                'gif' => 'image/gif',
                'bmp' => 'image/bmp',
                'tiff' => 'image/tiff',
                'txt' => 'text/plain',
                'xls' => 'application/vnd.ms-excel',
                'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'ppt' => 'application/vnd.ms-powerpoint',
                'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
                'zip' => 'application/zip',
                'rar' => 'application/x-rar-compressed',
                'mp4' => 'video/mp4',
                'avi' => 'video/x-msvideo',
                'mov' => 'video/quicktime',
                'wmv' => 'video/x-ms-wmv',
                'mp3' => 'audio/mpeg',
                'wav' => 'audio/wav',
                'flac' => 'audio/flac'
            ];
            $mimeType = $mimeTypes[$extension] ?? 'application/octet-stream';
            $response->headers->set('Content-Type', $mimeType);
        }

        return $response;
    }

    #[Route('/documents/{id}/toggle-statut-ajout', name: 'responsable_toggle_statut_ajout')]
    public function toggleStatutAjout(int $id, DocumentRepository $documentRepository, EntityManagerInterface $entityManager): Response
    {
        if (!$this->getUser() || !in_array('ROLE_RESPONSABLE_RH', $this->getUser()->getRoles())) {
            return $this->redirectToRoute('app_login');
        }

        $document = $documentRepository->find($id);
        if (!$document) {
            $this->addFlash('error', 'Document non trouvé !');
            return $this->redirectToRoute('responsable_manage_dossiers');
        }

        // Toggle le statut d'ajout
        if ($document->getStatutAjout() === 'ajoute') {
            $document->setStatutAjout('non_ajoute');
            $this->addFlash('success', 'Le document a été marqué comme "Non ajouté".');
        } else {
            $document->setStatutAjout('ajoute');
            $this->addFlash('success', 'Le document a été marqué comme "Ajouté".');
        }

        $entityManager->flush();

        // Rediriger vers la page du dossier
        $dossierId = $document->getDossier()->getId();
        return $this->redirectToRoute('responsable_view_dossier_documents', ['id' => $dossierId]);
    }

    #[Route('/dossiers/{dossier_id}/documents/{abbreviation}/toggle-statut-ajout', name: 'responsable_toggle_statut_ajout_new')]
    public function toggleStatutAjoutNew(int $dossier_id, string $abbreviation, DossierRepository $dossierRepository, EntityManagerInterface $entityManager): Response
    {
        if (!$this->getUser() || !in_array('ROLE_RESPONSABLE_RH', $this->getUser()->getRoles())) {
            return $this->redirectToRoute('app_login');
        }

        $dossier = $dossierRepository->find($dossier_id);
        if (!$dossier) {
            $this->addFlash('error', 'Dossier non trouvé !');
            return $this->redirectToRoute('responsable_manage_dossiers');
        }

        // Créer un nouveau document avec le statut "ajouté"
        $document = new Document();
        $document->setAbbreviation($abbreviation);
        $document->setLibelleComplet($abbreviation); // Utiliser l'abréviation comme libellé temporaire
        $document->setTypeDocument('À définir');
        $document->setUsage('Document ajouté manuellement');
        $document->setDossier($dossier);
        $document->setStatutAjout('ajoute');
        $document->setStatutTelechargement('non_telecharge');

        $entityManager->persist($document);
        $entityManager->flush();

        $this->addFlash('success', 'Le document ' . $abbreviation . ' a été marqué comme "Ajouté".');

        return $this->redirectToRoute('responsable_view_dossier_documents', ['id' => $dossier_id]);
    }


    #[Route('/demandes', name: 'responsable_manage_demandes')]
    public function manageDemandes(Request $request, DemandeRepository $demandeRepository, PaginatorInterface $paginator): Response
    {
        if (!$this->getUser() || !in_array('ROLE_RESPONSABLE_RH', $this->getUser()->getRoles())) {
            return $this->redirectToRoute('app_login');
        }

        $demandesEnAttenteQuery = $demandeRepository->findEnAttenteQuery();
        $demandesTraiteesQuery = $demandeRepository->findTraiteesParResponsableQuery($this->getUser());

        // Paginer les résultats - 10 éléments par page
        $demandesEnAttente = $paginator->paginate(
            $demandesEnAttenteQuery,
            $request->query->getInt('page_en_attente', 1),
            10
        );

        $demandesTraitees = $paginator->paginate(
            $demandesTraiteesQuery,
            $request->query->getInt('page_traitees', 1),
            10
        );

        return $this->render('responsable-rh/demandes.html.twig', [
            'demandesEnAttente' => $demandesEnAttente,
            'demandesTraitees' => $demandesTraitees
        ]);
    }

    #[Route('/demandes/{id}', name: 'responsable_voir_demande')]
    public function voirDemande(int $id, DemandeRepository $demandeRepository): Response
    {
        if (!$this->getUser() || !in_array('ROLE_RESPONSABLE_RH', $this->getUser()->getRoles())) {
            return $this->redirectToRoute('app_login');
        }

        $demande = $demandeRepository->find($id);
        if (!$demande) {
            $this->addFlash('error', 'Demande/réclamation non trouvée !');
            return $this->redirectToRoute('responsable_manage_demandes');
        }

        return $this->render('responsable-rh/voir_demande.html.twig', [
            'demande' => $demande
        ]);
    }

    #[Route('/demandes/{id}/repondre', name: 'responsable_repondre_demande')]
    public function repondreDemande(int $id, Request $request, DemandeRepository $demandeRepository, EntityManagerInterface $entityManager): Response
    {
        if (!$this->getUser() || !in_array('ROLE_RESPONSABLE_RH', $this->getUser()->getRoles())) {
            return $this->redirectToRoute('app_login');
        }

        $demande = $demandeRepository->find($id);
        if (!$demande) {
            $this->addFlash('error', 'Demande/réclamation non trouvée !');
            return $this->redirectToRoute('responsable_manage_demandes');
        }

        if ($demande->getStatut() !== 'en_attente') {
            $this->addFlash('error', 'Cette demande/réclamation a déjà été traitée !');
            return $this->redirectToRoute('responsable_manage_demandes');
        }

        $form = $this->createForm(ReponseDemandeType::class, $demande);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $demande->setResponsableRh($this->getUser());
            $demande->setDateReponse(new \DateTimeImmutable());
            
            $entityManager->flush();

            $statutLibelle = $demande->getStatut() === 'acceptee' ? 'acceptée' : 'refusée';
            $this->addFlash('success', "Demande/réclamation {$statutLibelle} avec succès !");
            return $this->redirectToRoute('responsable_manage_demandes');
        }

        return $this->render('responsable-rh/repondre_demande.html.twig', [
            'demande' => $demande,
            'form' => $form->createView()
        ]);
    }

    // ===== GESTION DES PLACARDS =====

    #[Route('/placards', name: 'responsable_manage_placards')]
    public function managePlacards(Request $request, PlacardRepository $placardRepository, PaginatorInterface $paginator): Response
    {
        if (!$this->getUser() || !in_array('ROLE_RESPONSABLE_RH', $this->getUser()->getRoles())) {
            return $this->redirectToRoute('app_login');
        }

        $placardsQuery = $placardRepository->findAllQuery();

        // Paginer les résultats - 10 éléments par page
        $placards = $paginator->paginate(
            $placardsQuery,
            $request->query->getInt('page', 1),
            10
        );

        return $this->render('responsable-rh/placards.html.twig', [
            'placards' => $placards
        ]);
    }

    #[Route('/placards/ajouter', name: 'responsable_add_placard')]
    public function addPlacard(Request $request, EntityManagerInterface $entityManager): Response
    {
        if (!$this->getUser() || !in_array('ROLE_RESPONSABLE_RH', $this->getUser()->getRoles())) {
            return $this->redirectToRoute('app_login');
        }

        $placard = new Placard();
        $form = $this->createForm(PlacardType::class, $placard);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($placard);
            $entityManager->flush();

            $this->addFlash('success', 'Placard créé avec succès !');
            return $this->redirectToRoute('responsable_manage_placards');
        }

        return $this->render('responsable-rh/add_placard.html.twig', [
            'form' => $form->createView()
        ]);
    }

    #[Route('/placards/modifier/{id}', name: 'responsable_edit_placard')]
    public function editPlacard(int $id, Request $request, EntityManagerInterface $entityManager, PlacardRepository $placardRepository): Response
    {
        if (!$this->getUser() || !in_array('ROLE_RESPONSABLE_RH', $this->getUser()->getRoles())) {
            return $this->redirectToRoute('app_login');
        }

        $placard = $placardRepository->find($id);
        if (!$placard) {
            $this->addFlash('error', 'Placard non trouvé !');
            return $this->redirectToRoute('responsable_manage_placards');
        }

        $form = $this->createForm(PlacardType::class, $placard);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            $this->addFlash('success', 'Placard modifié avec succès !');
            return $this->redirectToRoute('responsable_manage_placards');
        }

        return $this->render('responsable-rh/edit_placard.html.twig', [
            'form' => $form->createView(),
            'placard' => $placard
        ]);
    }

    #[Route('/placards/voir/{id}', name: 'responsable_view_placard')]
    public function viewPlacard(int $id, PlacardRepository $placardRepository): Response
    {
        if (!$this->getUser() || !in_array('ROLE_RESPONSABLE_RH', $this->getUser()->getRoles())) {
            return $this->redirectToRoute('app_login');
        }

        $placard = $placardRepository->find($id);
        if (!$placard) {
            $this->addFlash('error', 'Placard non trouvé !');
            return $this->redirectToRoute('responsable_manage_placards');
        }

        return $this->render('responsable-rh/view_placard.html.twig', [
            'placard' => $placard
        ]);
    }

    #[Route('/placards/supprimer/{id}', name: 'responsable_delete_placard')]
    public function deletePlacard(int $id, EntityManagerInterface $entityManager, PlacardRepository $placardRepository): Response
    {
        if (!$this->getUser() || !in_array('ROLE_RESPONSABLE_RH', $this->getUser()->getRoles())) {
            return $this->redirectToRoute('app_login');
        }

        $placard = $placardRepository->find($id);
        if (!$placard) {
            $this->addFlash('error', 'Placard non trouvé !');
            return $this->redirectToRoute('responsable_manage_placards');
        }

        // Vérifier s'il y a des dossiers dans ce placard
        if ($placard->getDossiers()->count() > 0) {
            $this->addFlash('error', 'Impossible de supprimer ce placard car il contient des dossiers !');
            return $this->redirectToRoute('responsable_manage_placards');
        }

        $entityManager->remove($placard);
        $entityManager->flush();

        $this->addFlash('success', 'Placard supprimé avec succès !');
        return $this->redirectToRoute('responsable_manage_placards');
    }

    /**
     * Sanitize filename by removing special characters and converting to lowercase
     */
    private function sanitizeFilename(string $filename): string
    {
        // Remove special characters and keep only alphanumeric, dots, hyphens, and underscores
        $filename = preg_replace('/[^a-zA-Z0-9._-]/', '', $filename);
        
        // Convert to lowercase
        $filename = strtolower($filename);
        
        // Remove multiple consecutive dots, hyphens, or underscores
        $filename = preg_replace('/[._-]{2,}/', '_', $filename);
        
        // Remove leading/trailing dots, hyphens, or underscores
        $filename = trim($filename, '._-');
        
        // If filename is empty after sanitization, use a default name
        if (empty($filename)) {
            $filename = 'document';
        }
        
        return $filename;
    }

    private function generateUniqueAbbreviation(EntityManagerInterface $entityManager): string
    {
        $year = date('Y');
        $attempts = 0;
        $maxAttempts = 100;
        
        do {
            $attempts++;
            $number = str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
            $abbreviation = "DOC-{$year}-{$number}";
            
            // Vérifier si cette abréviation existe déjà
            $existingDocument = $entityManager->getRepository(\App\Entity\Document::class)
                ->findOneBy(['abbreviation' => $abbreviation]);
                
        } while ($existingDocument && $attempts < $maxAttempts);
        
        if ($attempts >= $maxAttempts) {
            // Si on n'arrive pas à générer une abréviation unique, utiliser un timestamp court
            $timestamp = substr(time(), -6); // Prendre les 6 derniers chiffres du timestamp
            $abbreviation = "DOC-{$year}-{$timestamp}";
        }
        
        return $abbreviation;
    }

    private function abbreviationExists(EntityManagerInterface $entityManager, string $abbreviation): bool
    {
        $existingDocument = $entityManager->getRepository(\App\Entity\Document::class)
            ->findOneBy(['abbreviation' => $abbreviation]);
        
        return $existingDocument !== null;
    }

    #[Route('/contrats', name: 'responsable_manage_contrats')]
    public function manageContrats(Request $request, EmployeeContratRepository $contratRepository, PaginatorInterface $paginator): Response
    {
        $contratsQuery = $contratRepository->findAllQuery();

        // Paginer les résultats - 10 éléments par page
        $contrats = $paginator->paginate(
            $contratsQuery,
            $request->query->getInt('page', 1),
            10
        );
        
        return $this->render('responsable-rh/contrats.html.twig', [
            'contrats' => $contrats,
        ]);
    }


    #[Route('/organisations', name: 'responsable_manage_organisations')]
    public function manageOrganisations(Request $request, OrganisationRepository $organisationRepository, PaginatorInterface $paginator): Response
    {
        $organisationsQuery = $organisationRepository->findAllQuery();

        // Paginer les résultats - 10 éléments par page
        $organisations = $paginator->paginate(
            $organisationsQuery,
            $request->query->getInt('page', 1),
            10
        );
        
        return $this->render('responsable-rh/organisations.html.twig', [
            'organisations' => $organisations,
        ]);
    }

    #[Route('/organisations/{id}', name: 'responsable_view_organisation', requirements: ['id' => '\d+'])]
    public function viewOrganisation(int $id, OrganisationRepository $organisationRepository): Response
    {
        $organisation = $organisationRepository->find($id);
        
        if (!$organisation) {
            $this->addFlash('error', 'Organisation non trouvée !');
            return $this->redirectToRoute('responsable_manage_organisations');
        }
        
        return $this->render('responsable-rh/organisation_details.html.twig', [
            'organisation' => $organisation,
        ]);
    }

    #[Route('/organisations/{id}/edit', name: 'responsable_edit_organisation', requirements: ['id' => '\d+'])]
    public function editOrganisation(int $id, Request $request, OrganisationRepository $organisationRepository, EntityManagerInterface $entityManager): Response
    {
        $organisation = $organisationRepository->find($id);
        
        if (!$organisation) {
            $this->addFlash('error', 'Organisation non trouvée !');
            return $this->redirectToRoute('responsable_manage_organisations');
        }
        
        $form = $this->createForm(OrganisationType::class, $organisation);
        $form->handleRequest($request);
        
        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();
            $this->addFlash('success', 'Organisation mise à jour avec succès !');
            return $this->redirectToRoute('responsable_view_organisation', ['id' => $organisation->getId()]);
        }
        
        return $this->render('responsable-rh/edit_organisation.html.twig', [
            'organisation' => $organisation,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/organisations/new', name: 'responsable_add_organisation')]
    public function addOrganisation(Request $request, EntityManagerInterface $entityManager): Response
    {
        $organisation = new Organisation();
        $form = $this->createForm(OrganisationType::class, $organisation);
        $form->handleRequest($request);
        
        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($organisation);
            $entityManager->flush();
            $this->addFlash('success', 'Organisation créée avec succès !');
            return $this->redirectToRoute('responsable_view_organisation', ['id' => $organisation->getId()]);
        }
        
        return $this->render('responsable-rh/add_organisation.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/organisations/assign', name: 'responsable_assign_organisation')]
    public function assignOrganisation(Request $request, EntityManagerInterface $entityManager, OrganisationRepository $organisationRepository, EmployeeContratRepository $employeeContratRepository, ExcelGeneratorService $excelGenerator): Response
    {
        $organisationEmployeeContrat = new \App\Entity\OrganisationEmployeeContrat();
        $form = $this->createForm(OrganisationEmployeeContratType::class, $organisationEmployeeContrat);
        $form->handleRequest($request);
        
        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($organisationEmployeeContrat);
            $entityManager->flush();
            
            // Régénérer automatiquement le fichier Excel
            $excelGenerator->generateModuleExcel();
            
            $this->addFlash('success', 'Employé assigné à l\'organisation avec succès !');
            return $this->redirectToRoute('responsable_manage_organisations');
        }
        
        return $this->render('responsable-rh/assign_organisation.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    /**
     * Create a document directly from abbreviation using p_document template
     */
    private function createDocumentFromAbbreviation(string $abbreviation, int $dossierId, Request $request, EntityManagerInterface $entityManager, DossierRepository $dossierRepository, DocumentRepository $documentRepository): Response
    {
        // Get the dossier
        $dossier = $dossierRepository->find($dossierId);
        if (!$dossier) {
            $this->addFlash('error', 'Dossier non trouvé !');
            return $this->redirectToRoute('responsable_manage_dossiers');
        }

        // Get template document from p_document
        $templateDocument = $documentRepository->findOneBy(['abbreviation' => $abbreviation]);
        
        // If no template document exists, create a basic one
        if (!$templateDocument) {
            $templateDocument = new Document();
            $templateDocument->setAbbreviation($abbreviation);
            $templateDocument->setLibelleComplet($abbreviation);
            $templateDocument->setTypeDocument('À définir');
            $templateDocument->setUsage('Document créé automatiquement');
        }

        // Check if document already exists in this dossier
        $existingDocument = $dossier->getDocuments()->filter(function($doc) use ($abbreviation) {
            return $doc->getAbbreviation() === $abbreviation;
        })->first();

        // Handle file upload if POST request
        if ($request->isMethod('POST')) {
            $uploadedFile = $request->files->get('document_file');
            
            if ($uploadedFile) {
                // Create upload directory if it doesn't exist
                $uploadDir = $this->getParameter('kernel.project_dir') . '/public/uploads/documents/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }
                
                // Generate unique filename
                $originalFilename = pathinfo($uploadedFile->getClientOriginalName(), PATHINFO_FILENAME);
                $originalExtension = strtolower(pathinfo($uploadedFile->getClientOriginalName(), PATHINFO_EXTENSION));
                $safeFilename = preg_replace('/[^A-Za-z0-9_-]/', '', $originalFilename);
                if (empty($safeFilename)) {
                    $safeFilename = 'document';
                }
                $newFilename = strtolower($safeFilename) . '-' . uniqid() . '.' . $originalExtension;
                
                try {
                    $uploadedFile->move($uploadDir, $newFilename);
                    
                    if ($existingDocument) {
                        // Update existing document
                        // Delete old file if it exists
                        if ($existingDocument->getFilePath() && file_exists($existingDocument->getFilePath())) {
                            unlink($existingDocument->getFilePath());
                        }
                        
                        $existingDocument->setFilePath($uploadDir . $newFilename);
                        
                        // Set MIME type manually since fileinfo extension is not available
                        $mimeType = $this->getMimeTypeFromExtension($originalExtension);
                        $existingDocument->setFileType($mimeType);
                        $existingDocument->setUploadedBy($this->getUser()->getFullName());
                        
                        // Marquer automatiquement comme téléchargé ET ajouté
                        $existingDocument->setStatutTelechargement('telecharge');
                        $existingDocument->setStatutAjout('ajoute');
                        
                        $entityManager->flush();
                        
                        $this->addFlash('success', 'Document mis à jour avec succès !');
                    } else {
                        // Create new document using template information
                        $newDocument = new Document();
                        $newDocument->setAbbreviation($templateDocument->getAbbreviation());
                        $newDocument->setLibelleComplet($templateDocument->getLibelleComplet());
                        $newDocument->setTypeDocument($templateDocument->getTypeDocument());
                        $newDocument->setUsage($templateDocument->getUsage());
                        $newDocument->setFilePath($uploadDir . $newFilename);
                        
                        // Set MIME type manually since fileinfo extension is not available
                        $mimeType = $this->getMimeTypeFromExtension($originalExtension);
                        $newDocument->setFileType($mimeType);
                        $newDocument->setUploadedBy($this->getUser()->getFullName());
                        $newDocument->setDossier($dossier);
                        
                        // Marquer automatiquement comme téléchargé ET ajouté
                        $newDocument->setStatutTelechargement('telecharge');
                        $newDocument->setStatutAjout('ajoute');
                        
                        $dossier->addDocument($newDocument);
                        $entityManager->persist($newDocument);
                        $entityManager->flush();
                        
                        $this->addFlash('success', 'Document téléchargé avec succès !');
                    }
                    
                } catch (\Exception $e) {
                    $this->addFlash('error', 'Erreur lors du téléchargement : ' . $e->getMessage());
                }
            } else {
                $this->addFlash('error', 'Veuillez sélectionner un fichier !');
            }
            
            return $this->redirectToRoute('responsable_view_dossier_documents', ['id' => $dossierId]);
        }

        // Show upload form
        // Use existing document if available, otherwise use template
        $documentToDisplay = $existingDocument ?: $templateDocument;
        
        return $this->render('responsable-rh/upload_document.html.twig', [
            'abbreviation' => $abbreviation,
            'templateDocument' => $documentToDisplay,
            'dossier' => $dossier,
            'dossierId' => $dossierId,
            'existingDocument' => $existingDocument
        ]);
    }

    /**
     * Get MIME type from file extension (manual mapping since fileinfo extension is not available)
     */
    private function getMimeTypeFromExtension(string $extension): string
    {
        $mimeTypes = [
            'pdf' => 'application/pdf',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'bmp' => 'image/bmp',
            'tiff' => 'image/tiff',
            'txt' => 'text/plain',
            'xls' => 'application/vnd.ms-excel',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'ppt' => 'application/vnd.ms-powerpoint',
            'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'zip' => 'application/zip',
            'rar' => 'application/x-rar-compressed',
            'mp4' => 'video/mp4',
            'avi' => 'video/x-msvideo',
            'mov' => 'video/quicktime',
            'wmv' => 'video/x-ms-wmv',
            'mp3' => 'audio/mpeg',
            'wav' => 'audio/wav',
            'flac' => 'audio/flac'
        ];
        
        return $mimeTypes[$extension] ?? 'application/octet-stream';
    }

    // ==================== KPI ROUTES ====================

    #[Route('/kpi', name: 'responsable_kpi_index')]
    public function kpiIndex(): Response
    {
        return $this->render('responsable-rh/kpi/index.html.twig');
    }

    #[Route('/kpi/contrat-type', name: 'responsable_kpi_by_contract')]
    public function kpiByContractType(KpiService $kpiService): Response
    {
        $data = $kpiService->getDocumentReliabilityByContractType();
        
        // Calculate percentages
        $formattedData = [];
        foreach ($data as $row) {
            $personnelTotal = (int)$row['personnel_expected'];
            $ayantDroitsTotal = (int)$row['ayant_droits_expected'];
            
            $formattedData[] = [
                'contract_type' => $row['contract_type'],
                'total_employees' => $row['total_employees'],
                'personnel_uploaded' => $row['personnel_uploaded'],
                'personnel_expected' => $personnelTotal,
                'personnel_percentage' => $personnelTotal > 0 ? round(($row['personnel_uploaded'] / $personnelTotal) * 100, 2) : 0,
                'ayant_droits_uploaded' => $row['ayant_droits_uploaded'],
                'ayant_droits_expected' => $ayantDroitsTotal,
                'ayant_droits_percentage' => $ayantDroitsTotal > 0 ? round(($row['ayant_droits_uploaded'] / $ayantDroitsTotal) * 100, 2) : 0,
            ];
        }
        
        return $this->render('responsable-rh/kpi/by_contract_type.html.twig', [
            'data' => $formattedData
        ]);
    }

    #[Route('/kpi/das', name: 'responsable_kpi_by_das')]
    public function kpiByDAS(KpiService $kpiService): Response
    {
        $data = $kpiService->getDocumentReliabilityByDAS();
        
        // Calculate percentages
        $formattedData = [];
        foreach ($data as $row) {
            $personnelTotal = (int)$row['personnel_expected'];
            $ayantDroitsTotal = (int)$row['ayant_droits_expected'];
            
            $formattedData[] = [
                'das' => $row['das'],
                'division' => $row['division_activites_strategiques'],
                'total_employees' => $row['total_employees'],
                'personnel_uploaded' => $row['personnel_uploaded'],
                'personnel_expected' => $personnelTotal,
                'personnel_percentage' => $personnelTotal > 0 ? round(($row['personnel_uploaded'] / $personnelTotal) * 100, 2) : 0,
                'ayant_droits_uploaded' => $row['ayant_droits_uploaded'],
                'ayant_droits_expected' => $ayantDroitsTotal,
                'ayant_droits_percentage' => $ayantDroitsTotal > 0 ? round(($row['ayant_droits_uploaded'] / $ayantDroitsTotal) * 100, 2) : 0,
            ];
        }
        
        return $this->render('responsable-rh/kpi/by_das.html.twig', [
            'data' => $formattedData
        ]);
    }

    #[Route('/kpi/details/das', name: 'responsable_kpi_details_das')]
    public function kpiDetailsByDAS(KpiService $kpiService): Response
    {
        $data = $kpiService->getDetailedDocumentReliabilityByDAS();
        
        // Reorganize data by DAS and document
        $organized = [];
        foreach ($data as $row) {
            $das = $row['das'];
            $docAbbr = $row['document_abbreviation'];
            
            if (!isset($organized[$das])) {
                $organized[$das] = [];
            }
            
            $total = (int)$row['total_employees'];
            $uploaded = (int)$row['uploaded_employees'];
            
            $organized[$das][$docAbbr] = [
                'document_name' => $row['document_name'],
                'total' => $total,
                'uploaded' => $uploaded,
                'percentage' => $total > 0 ? round(($uploaded / $total) * 100, 2) : 0
            ];
        }
        
        return $this->render('responsable-rh/kpi/details_das.html.twig', [
            'data' => $organized
        ]);
    }

    #[Route('/kpi/details/contrat-type', name: 'responsable_kpi_details_contract')]
    public function kpiDetailsByContractType(KpiService $kpiService): Response
    {
        $data = $kpiService->getDetailedDocumentReliabilityByContractType();
        
        // Reorganize data by contract type and document
        $organized = [];
        foreach ($data as $row) {
            $contractType = $row['contract_type'];
            $docAbbr = $row['document_abbreviation'];
            
            if (!isset($organized[$contractType])) {
                $organized[$contractType] = [];
            }
            
            $total = (int)$row['total_employees'];
            $uploaded = (int)$row['uploaded_employees'];
            
            $organized[$contractType][$docAbbr] = [
                'document_name' => $row['document_name'],
                'total' => $total,
                'uploaded' => $uploaded,
                'percentage' => $total > 0 ? round(($uploaded / $total) * 100, 2) : 0
            ];
        }
        
        return $this->render('responsable-rh/kpi/details_contract_type.html.twig', [
            'data' => $organized
        ]);
    }

    #[Route('/reclamations', name: 'responsable_manage_reclamations')]
    public function manageReclamations(Request $request, ReclamationRepository $reclamationRepository, PaginatorInterface $paginator): Response
    {
        // Récupérer le filtre depuis les paramètres de requête
        $filter = $request->query->get('filter', 'all');
        
        // Récupérer les réclamations selon le filtre
        if ($filter === 'en_attente') {
            $reclamationsQuery = $reclamationRepository->findByStatutQuery('en_attente');
        } elseif ($filter === 'traitees') {
            $reclamationsQuery = $reclamationRepository->findByStatutQuery('traitee');
        } else {
            // Par défaut, afficher toutes les réclamations
            $reclamationsQuery = $reclamationRepository->findAllQuery();
        }

        // Paginer les résultats - 10 éléments par page
        $reclamations = $paginator->paginate(
            $reclamationsQuery,
            $request->query->getInt('page', 1),
            10
        );
        
        return $this->render('responsable-rh/reclamations.html.twig', [
            'reclamations' => $reclamations,
            'currentFilter' => $filter,
        ]);
    }

    #[Route('/reclamations/{id}/traiter', name: 'responsable_traiter_reclamation')]
    public function traiterReclamation(Request $request, Reclamation $reclamation, EntityManagerInterface $entityManager): Response
    {
        if ($request->isMethod('POST')) {
            $reponseRh = $request->request->get('reponse_rh');
            $statut = $request->request->get('statut');
            
            if ($reponseRh && $statut) {
                $reclamation->setReponseRh($reponseRh);
                $reclamation->setStatut($statut);
                $reclamation->setTraitePar($this->getUser());
                $reclamation->setDateTraitement(new \DateTime());
                
                $entityManager->flush();
                
                $this->addFlash('success', 'Réclamation traitée avec succès !');
                return $this->redirectToRoute('responsable_manage_reclamations');
            }
        }
        
        return $this->render('responsable-rh/traiter_reclamation.html.twig', [
            'reclamation' => $reclamation,
        ]);
    }

    #[Route('/download-excel', name: 'responsable_download_module_excel')]
    public function downloadModuleExcel(): Response
    {
        $filePath = $this->getParameter('kernel.project_dir') . '/module.xlsx';
        
        if (!file_exists($filePath)) {
            $this->addFlash('error', 'Le fichier Excel n\'existe pas.');
            return $this->redirectToRoute('responsable_rh_dashboard');
        }
        
        // Lire le contenu du fichier
        $content = file_get_contents($filePath);
        
        // Créer une réponse avec le contenu et le type MIME manuel
        $response = new Response($content);
        $response->headers->set('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $response->headers->set('Content-Disposition', 'attachment; filename="module_employes_' . date('Y-m-d') . '.xlsx"');
        $response->headers->set('Content-Length', strlen($content));
        
        return $response;
    }
}

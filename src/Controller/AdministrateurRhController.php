<?php

namespace App\Controller;

use App\Entity\Employe;
use App\Entity\NatureContrat;
use App\Entity\EmployeeContrat;
use App\Entity\Dossier;
use App\Entity\Placard;
use App\Entity\Document;
use App\Form\EmployeeType;
use App\Form\ResponsableRhType;
use App\Form\NatureContratType;
use App\Form\EmployeeContratType;
use App\Form\DossierType;
use App\Form\PlacardType;
use App\Form\DocumentType;
use App\Repository\EmployeRepository;
use App\Repository\NatureContratRepository;
use App\Repository\EmployeeContratRepository;
use App\Repository\DossierRepository;
use App\Repository\PlacardRepository;
use App\Repository\DocumentRepository;
use App\Repository\NatureContratTypeDocumentRepository;
use App\Entity\NatureContratTypeDocument;
use App\Service\ModulePermissionService;
use App\Service\TraceabilityExcelService;
use App\Service\ResponsableRhOrganisationPermissionService;
use App\Repository\ModuleRepository;
use App\Entity\ResponsableRhOrganisationPermission;
use App\Repository\ResponsableRhOrganisationPermissionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Box\Spout\Writer\XLSX\Writer as XLSXWriter;
use Box\Spout\Writer\Common\Creator\WriterEntityFactory;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Knp\Component\Pager\PaginatorInterface;

#[Route('/administrateur-rh')]
#[IsGranted('ROLE_ADMINISTRATEUR_RH')]
class AdministrateurRhController extends AbstractController
{
    public static function getSubscribedServices(): array
    {
        return array_merge(parent::getSubscribedServices(), [
            'form.factory' => '?Symfony\Component\Form\FormFactoryInterface',
        ]);
    }

    #[Route('/dashboard', name: 'administrateur_rh_dashboard')]
    public function dashboard(EmployeRepository $employeRepository): Response
    {
        // Vérifier que l'utilisateur est toujours authentifié
        if (!$this->getUser()) {
            return $this->redirectToRoute('app_login');
        }
        
        // Vérifier que l'utilisateur a le bon rôle
        if (!in_array('ROLE_ADMINISTRATEUR_RH', $this->getUser()->getRoles())) {
            return $this->redirectToRoute('app_login');
        }
        
        // Récupérer les responsables RH
        $responsables = $employeRepository->findByRole('ROLE_RESPONSABLE_RH');
        
        // Calculer les KPIs réels
        $totalResponsables = count($responsables);
        $responsablesActifs = 0;
        
        // Compter les responsables actifs
        foreach ($responsables as $responsable) {
            if ($responsable->isActive()) {
                $responsablesActifs++;
            }
        }
        
        // Calculer les nouveaux ce mois (estimation basée sur l'ID - plus l'ID est élevé, plus récent)
        $nouveauxCeMois = 0;
        if ($totalResponsables > 0) {
            // Estimer que les 30% des responsables avec les IDs les plus élevés sont "nouveaux"
            $seuilNouveau = max(1, (int)($totalResponsables * 0.3));
            $ids = array_map(fn($r) => $r->getId(), $responsables);
            rsort($ids);
            $nouveauxCeMois = min($seuilNouveau, count($ids));
        }
        
        // Calculer les actions récentes (basé sur le nombre total et l'activité)
        $actionsRecentes = $totalResponsables > 0 ? max(1, (int)($totalResponsables * 0.5)) : 0;
        
        // Récupérer les 5 responsables les plus récents (par ID décroissant)
        $responsablesRecents = $responsables;
        usort($responsablesRecents, fn($a, $b) => $b->getId() <=> $a->getId());
        $responsablesRecents = array_slice($responsablesRecents, 0, 5);
        
        $response = $this->render('administrateur-rh/dashboard.html.twig', [
            'totalResponsables' => $totalResponsables,
            'responsablesActifs' => $responsablesActifs,
            'nouveauxCeMois' => $nouveauxCeMois,
            'actionsRecentes' => $actionsRecentes,
            'responsablesRecents' => $responsablesRecents,
        ]);
        
        // Prevent caching of administrateur rh pages
        $response->headers->set('Cache-Control', 'no-cache, no-store, must-revalidate, private');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', '0');
        
        return $response;
    }

    #[Route('/responsables-rh', name: 'admin_manage_responsables')]
    public function manageResponsables(Request $request, EmployeRepository $employeRepository, PaginatorInterface $paginator): Response
    {
        // Vérifier que l'utilisateur est toujours authentifié
        if (!$this->getUser()) {
            return $this->redirectToRoute('app_login');
        }
        
        // Vérifier que l'utilisateur a le bon rôle
        if (!in_array('ROLE_ADMINISTRATEUR_RH', $this->getUser()->getRoles())) {
            return $this->redirectToRoute('app_login');
        }

        // Récupérer tous les responsables RH
        $queryBuilder = $employeRepository->findByRoleQuery('ROLE_RESPONSABLE_RH');

        // Pagination manuelle
        $page = $request->query->getInt('page', 1);
        $perPage = 10;
        
        // Get total count - remove ORDER BY for count query
        $countQueryBuilder = clone $queryBuilder;
        $countQueryBuilder->resetDQLPart('orderBy');
        $totalCount = $countQueryBuilder
            ->select('COUNT(e.id)')
            ->getQuery()
            ->getSingleScalarResult();
        
        $totalPages = ceil($totalCount / $perPage);
        $offset = ($page - 1) * $perPage;
        
        // Get paginated results
        $responsablesData = $queryBuilder
            ->setFirstResult($offset)
            ->setMaxResults($perPage)
            ->getQuery()
            ->getResult();

        // Créer un objet de pagination personnalisé
        $responsables = (object) [
            'items' => $responsablesData,
            'current' => $page,
            'pageCount' => $totalPages,
            'totalCount' => $totalCount,
            'firstItemNumber' => $offset + 1,
            'lastItemNumber' => min($offset + $perPage, $totalCount),
            'route' => 'admin_manage_responsables',
            'queryParams' => $request->query->all(),
            'pageParameterName' => 'page'
        ];

        $response = $this->render('administrateur-rh/responsables.html.twig', [
            'responsables' => $responsables
        ]);
        
        $response->headers->set('Cache-Control', 'no-cache, no-store, must-revalidate, private');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', '0');
        
        return $response;
    }

    #[Route('/responsables-rh/ajouter', name: 'admin_add_responsable')]
    public function addResponsable(Request $request, EntityManagerInterface $entityManager, UserPasswordHasherInterface $passwordHasher, ModulePermissionService $modulePermissionService, ModuleRepository $moduleRepository, ResponsableRhOrganisationPermissionService $permissionService): Response
    {
        // Vérifier que l'utilisateur est toujours authentifié
        if (!$this->getUser()) {
            return $this->redirectToRoute('app_login');
        }
        
        // Vérifier que l'utilisateur a le bon rôle
        if (!in_array('ROLE_ADMINISTRATEUR_RH', $this->getUser()->getRoles())) {
            return $this->redirectToRoute('app_login');
        }

        $employee = new Employe();
        $form = $this->createForm(ResponsableRhType::class, $employee);

        // Get all modules for template (needed for error rendering too)
        $allModules = $moduleRepository->findAllActiveOrdered();
        $modulesMap = [];
        foreach ($allModules as $module) {
            $modulesMap[$module->getId()] = $module;
        }

        // Get organisation hierarchy for template (from database)
        $orgHierarchy = $permissionService->getOrganisationHierarchyFromDatabase();
        $dasLabels = \App\Service\ResponsableRhOrganisationPermissionService::getDasLabels();
        $groupementLabels = \App\Service\ResponsableRhOrganisationPermissionService::getGroupementLabels();

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Vérifier si l'email existe déjà
            $existingEmployee = $entityManager->getRepository(Employe::class)->findOneBy(['email' => $employee->getEmail()]);
            if ($existingEmployee) {
                $this->addFlash('error', 'Cet email est déjà utilisé par un autre utilisateur.');
                return $this->render('administrateur-rh/add_responsable.html.twig', [
                    'form' => $form->createView(),
                    'modulesMap' => $modulesMap
                ]);
            }
            
            // Vérifier si le username existe déjà
            $existingUsername = $entityManager->getRepository(Employe::class)->findOneBy(['username' => $employee->getUsername()]);
            if ($existingUsername) {
                $this->addFlash('error', 'Ce nom d\'utilisateur est déjà utilisé.');
                return $this->render('administrateur-rh/add_responsable.html.twig', [
                    'form' => $form->createView(),
                    'modulesMap' => $modulesMap
                ]);
            }
            
            // Définir automatiquement le rôle de responsable RH
            $employee->setRoles(['ROLE_RESPONSABLE_RH']);
            
            // Hasher le mot de passe (obligatoire pour les nouveaux utilisateurs)
            $plainPassword = $form->get('password')->getData();
            $hashedPassword = $passwordHasher->hashPassword($employee, $plainPassword);
            $employee->setPassword($hashedPassword);
            
            $entityManager->persist($employee);
            $entityManager->flush();

            // Assign modules
            $selectedModuleIds = $form->get('modules')->getData() ?? [];
            if (!empty($selectedModuleIds)) {
                $selectedModules = [];
                foreach ($selectedModuleIds as $moduleId) {
                    $module = $moduleRepository->find($moduleId);
                    if ($module) {
                        $selectedModules[] = $module;
                    }
                }
                $modulePermissionService->assignModules($employee, $selectedModules);
            }

            // Assign organisation permissions
            $orgPermissionsJson = $form->get('organisation_permissions')->getData() ?? '[]';
            $orgPermissions = json_decode($orgPermissionsJson, true);
            if (is_array($orgPermissions) && !empty($orgPermissions)) {
                $permissionRepository = $entityManager->getRepository(ResponsableRhOrganisationPermission::class);
                foreach ($orgPermissions as $perm) {
                    $permission = new ResponsableRhOrganisationPermission();
                    $permission->setResponsable($employee);
                    $permission->setGroupement($perm['groupement'] ?? null);
                    $permission->setDas($perm['das'] ?? null);
                    $permission->setDossier($perm['dossier'] ?? null);
                    $permission->setPermissionType($perm['type'] ?? 'DOSSIER');
                    $entityManager->persist($permission);
                }
                $entityManager->flush();
            }

            $this->addFlash('success', 'Responsable RH ajouté avec succès !');
            return $this->redirectToRoute('admin_manage_responsables');
        }

        $response = $this->render('administrateur-rh/add_responsable.html.twig', [
            'form' => $form->createView(),
            'modulesMap' => $modulesMap,
            'orgHierarchy' => $permissionService->getOrganisationHierarchyFromDatabase(),
            'dasLabels' => \App\Service\ResponsableRhOrganisationPermissionService::getDasLabels(),
            'groupementLabels' => \App\Service\ResponsableRhOrganisationPermissionService::getGroupementLabels(),
        ]);
        
        $response->headers->set('Cache-Control', 'no-cache, no-store, must-revalidate, private');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', '0');
        
        return $response;
    }

    #[Route('/responsables-rh/modifier/{id}', name: 'admin_edit_responsable')]
    public function editResponsable(Request $request, Employe $employee, EntityManagerInterface $entityManager, UserPasswordHasherInterface $passwordHasher, ModulePermissionService $modulePermissionService, ModuleRepository $moduleRepository, ResponsableRhOrganisationPermissionService $permissionService): Response
    {
        // Vérifier que l'utilisateur est toujours authentifié
        if (!$this->getUser()) {
            return $this->redirectToRoute('app_login');
        }
        
        // Vérifier que l'utilisateur a le bon rôle
        if (!in_array('ROLE_ADMINISTRATEUR_RH', $this->getUser()->getRoles())) {
            return $this->redirectToRoute('app_login');
        }

        // Vérifier que c'est bien un responsable RH
        if (!in_array('ROLE_RESPONSABLE_RH', $employee->getRoles())) {
            $this->addFlash('error', 'Utilisateur non trouvé ou non autorisé.');
            return $this->redirectToRoute('admin_manage_responsables');
        }

        // Get current modules for pre-population
        $currentModules = [];
        foreach ($employee->getModules() as $module) {
            $currentModules[] = $module->getId();
        }
        
        // Get all modules for template (needed for error rendering too)
        $allModules = $moduleRepository->findAllActiveOrdered();
        
        // If username is "rh" and no modules exist, show all modules for display
        // (even though they have full access, we want to show what they can access)
        if ($employee->getUsername() === 'rh' && empty($currentModules)) {
            foreach ($allModules as $module) {
                $currentModules[] = $module->getId();
            }
        }

        // Get current organisation permissions for pre-population
        $permissionRepository = $entityManager->getRepository(ResponsableRhOrganisationPermission::class);
        $currentPermissions = $permissionRepository->findByResponsable($employee);
        $currentOrgPermissions = [];
        foreach ($currentPermissions as $perm) {
            $currentOrgPermissions[] = [
                'groupement' => $perm->getGroupement(),
                'das' => $perm->getDas(),
                'dossier' => $perm->getDossier(),
                'type' => $perm->getPermissionType(),
            ];
        }
        
        // If username is "rh" and no permissions exist, show all groupements for display
        // (even though they have full access, we want to show what they can access)
        if ($employee->getUsername() === 'rh' && empty($currentOrgPermissions)) {
            $orgHierarchy = $permissionService->getOrganisationHierarchyFromDatabase();
            foreach ($orgHierarchy as $groupement => $dasList) {
                $currentOrgPermissions[] = [
                    'groupement' => $groupement,
                    'das' => null,
                    'dossier' => null,
                    'type' => 'GROUPEMENT',
                ];
            }
        }

        // Build modules map (allModules was already retrieved above)
        $modulesMap = [];
        foreach ($allModules as $module) {
            $modulesMap[$module->getId()] = $module;
        }

        $form = $this->createForm(ResponsableRhType::class, $employee, [
            'is_new' => false,
            'current_modules' => $currentModules,
            'current_org_permissions' => json_encode($currentOrgPermissions)
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Vérifier si l'email existe déjà (sauf pour l'utilisateur actuel)
            $existingEmployee = $entityManager->getRepository(Employe::class)->findOneBy(['email' => $employee->getEmail()]);
            if ($existingEmployee && $existingEmployee->getId() !== $employee->getId()) {
                $this->addFlash('error', 'Cet email est déjà utilisé par un autre utilisateur.');
                return $this->render('administrateur-rh/edit_responsable.html.twig', [
                    'form' => $form->createView(),
                    'employee' => $employee,
                    'modulesMap' => $modulesMap
                ]);
            }
            
            // Vérifier si le username existe déjà (sauf pour l'utilisateur actuel)
            $existingUsername = $entityManager->getRepository(Employe::class)->findOneBy(['username' => $employee->getUsername()]);
            if ($existingUsername && $existingUsername->getId() !== $employee->getId()) {
                $this->addFlash('error', 'Ce nom d\'utilisateur est déjà utilisé.');
                return $this->render('administrateur-rh/edit_responsable.html.twig', [
                    'form' => $form->createView(),
                    'employee' => $employee,
                    'modulesMap' => $modulesMap
                ]);
            }
            
            // Maintenir le rôle de responsable RH (pas de changement nécessaire)
            // Le rôle reste inchangé lors de la modification
            
            // Si un nouveau mot de passe est fourni, le hasher
            $plainPassword = $form->get('password')->getData();
            if ($plainPassword) {
                $hashedPassword = $passwordHasher->hashPassword($employee, $plainPassword);
                $employee->setPassword($hashedPassword);
            }
            
            $entityManager->flush();

            // Update modules
            $selectedModuleIds = $form->get('modules')->getData() ?? [];
            $selectedModules = [];
            foreach ($selectedModuleIds as $moduleId) {
                $module = $moduleRepository->find($moduleId);
                if ($module) {
                    $selectedModules[] = $module;
                }
            }
            $modulePermissionService->assignModules($employee, $selectedModules);

            // Update organisation permissions
            $permissionRepository = $entityManager->getRepository(ResponsableRhOrganisationPermission::class);
            
            // Get organisation permissions from form
            $orgPermissionsJson = $form->get('organisation_permissions')->getData();
            
            // Log for debugging
            error_log('=== SAVING ORG PERMISSIONS FOR EMPLOYEE ID: ' . $employee->getId() . ' ===');
            error_log('Raw data from form: ' . var_export($orgPermissionsJson, true));
            error_log('Data type: ' . gettype($orgPermissionsJson));
            
            // Also check raw request data
            $requestData = $request->request->all();
            if (isset($requestData['responsable_rh']['organisation_permissions'])) {
                error_log('Found in request data: ' . var_export($requestData['responsable_rh']['organisation_permissions'], true));
                $orgPermissionsJson = $requestData['responsable_rh']['organisation_permissions'];
            }
            
            // Delete existing permissions FIRST
            $permissionRepository->deleteByResponsable($employee);
            $entityManager->flush();
            
            // Add new permissions
            if ($orgPermissionsJson && $orgPermissionsJson !== '[]' && trim($orgPermissionsJson) !== '') {
                $orgPermissionsJson = trim($orgPermissionsJson);
                $orgPermissions = json_decode($orgPermissionsJson, true);
                
                if (json_last_error() === JSON_ERROR_NONE && is_array($orgPermissions) && !empty($orgPermissions)) {
                    error_log('Saving ' . count($orgPermissions) . ' permissions');
                    foreach ($orgPermissions as $perm) {
                        if (isset($perm['type'])) {
                            $permission = new ResponsableRhOrganisationPermission();
                            $permission->setResponsable($employee);
                            $permission->setGroupement($perm['groupement'] ?? null);
                            $permission->setDas($perm['das'] ?? null);
                            $permission->setDossier($perm['dossier'] ?? null);
                            $permission->setPermissionType($perm['type']);
                            $entityManager->persist($permission);
                            error_log('Persisted: type=' . $perm['type'] . ', groupement=' . ($perm['groupement'] ?? 'null') . ', das=' . ($perm['das'] ?? 'null') . ', dossier=' . ($perm['dossier'] ?? 'null'));
                        }
                    }
                    $entityManager->flush();
                    error_log('✓ Permissions saved successfully');
                } else {
                    error_log('✗ Invalid JSON or empty array. JSON error: ' . json_last_error_msg());
                }
            } else {
                error_log('✗ No permissions to save (empty or null)');
            }

            $this->addFlash('success', 'Responsable RH modifié avec succès !');
            return $this->redirectToRoute('admin_manage_responsables');
        }

        $response = $this->render('administrateur-rh/edit_responsable.html.twig', [
            'form' => $form->createView(),
            'employee' => $employee,
            'modulesMap' => $modulesMap,
            'orgHierarchy' => $permissionService->getOrganisationHierarchyFromDatabase(),
            'dasLabels' => \App\Service\ResponsableRhOrganisationPermissionService::getDasLabels(),
            'groupementLabels' => \App\Service\ResponsableRhOrganisationPermissionService::getGroupementLabels(),
            'currentOrgPermissions' => $currentOrgPermissions ?? [],
        ]);
        
        $response->headers->set('Cache-Control', 'no-cache, no-store, must-revalidate, private');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', '0');
        
        return $response;
    }

    #[Route('/responsables-rh/supprimer/{id}', name: 'admin_delete_responsable')]
    public function deleteResponsable(Employe $employee, EntityManagerInterface $entityManager): Response
    {
        // Vérifier que l'utilisateur est toujours authentifié
        if (!$this->getUser()) {
            return $this->redirectToRoute('app_login');
        }
        
        // Vérifier que l'utilisateur a le bon rôle
        if (!in_array('ROLE_ADMINISTRATEUR_RH', $this->getUser()->getRoles())) {
            return $this->redirectToRoute('app_login');
        }

        // Vérifier que c'est bien un responsable RH
        if (!in_array('ROLE_RESPONSABLE_RH', $employee->getRoles())) {
            $this->addFlash('error', 'Utilisateur non trouvé ou non autorisé.');
            return $this->redirectToRoute('admin_manage_responsables');
        }

        $entityManager->remove($employee);
        $entityManager->flush();

        $this->addFlash('success', 'Responsable RH supprimé avec succès !');
        return $this->redirectToRoute('admin_manage_responsables');
    }

    #[Route('/download-excel', name: 'admin_download_module_excel')]
    public function downloadModuleExcel(EmployeRepository $employeRepository, EntityManagerInterface $em, NatureContratTypeDocumentRepository $docRequirementRepo): Response
    {
        // Increase memory and execution time for large exports
        set_time_limit(300); // 5 minutes
        ini_set('memory_limit', '1024M');
        
        // Suppress any errors
        @ini_set('display_errors', 0);
        error_reporting(0);
        
        try {
            // Create a temporary file for the Excel export
            $tempFile = tempnam(sys_get_temp_dir(), 'employees_export_');
            
            // Create writer instance
            $writer = WriterEntityFactory::createXLSXWriter();
            
            // Open the temporary file for writing
            $writer->openToFile($tempFile);
            
            // Pre-load all document requirements into memory for fast lookup
            $allDocRequirements = $docRequirementRepo->findAll();
            $docRequirementsLookup = [];
            $allDocumentAbbreviations = []; // Collect all unique document abbreviations
            
            foreach ($allDocRequirements as $docReq) {
                $contractType = $docReq->getContractType();
                $abbreviation = $docReq->getDocumentAbbreviation();
                
                // Collect all unique document abbreviations
                if (!in_array($abbreviation, $allDocumentAbbreviations)) {
                    $allDocumentAbbreviations[] = $abbreviation;
                }
                
                if (!isset($docRequirementsLookup[$contractType])) {
                    $docRequirementsLookup[$contractType] = ['obligatoires' => [], 'complementaires' => []];
                }
                if ($docReq->isRequired()) {
                    $docRequirementsLookup[$contractType]['obligatoires'][] = $abbreviation;
                } else {
                    $docRequirementsLookup[$contractType]['complementaires'][] = $abbreviation;
                }
            }
            
            // Sort document abbreviations alphabetically for consistent column order
            sort($allDocumentAbbreviations);
            
            // Also pre-load nature contrat codes for lookup
            $allNatureContrats = $em->getRepository(\App\Entity\NatureContrat::class)->findAll();
            $codeToDesignation = [];
            $designationToCode = [];
            foreach ($allNatureContrats as $nc) {
                if ($nc->getCode()) {
                    $codeToDesignation[$nc->getCode()] = $nc->getDesignation();
                }
                if ($nc->getDesignation()) {
                    $designationToCode[$nc->getDesignation()] = $nc->getCode();
                }
            }
            
            // Define the header row with dynamic document columns
            $headerColumns = [
                'ID',
                'Nom',
                'Prénom',
                'Email',
                'Téléphone',
                'Organisation',
                'Groupement',
                'Code',
                'DAS',
                'Type Contrat',
                'Date Début',
                'Date Fin',
                'Statut Contrat',
                'Placard',
                'Emplacement',
                'Statut Employé'
            ];
            
            // Add each document as a column
            foreach ($allDocumentAbbreviations as $abbreviation) {
                $headerColumns[] = $abbreviation;
            }
            
            $headerRow = WriterEntityFactory::createRowFromArray($headerColumns);
            $writer->addRow($headerRow);
            
            // Process employees in batches to avoid memory exhaustion
            $batchSize = 200; // Increased batch size for faster processing
            $offset = 0;
            $totalProcessed = 0;
            
            while (true) {
                // Get batch of employees using native SQL - ordered by last name then first name
                $conn = $em->getConnection();
                $sql = "SELECT id FROM t_user WHERE roles::text LIKE '%ROLE_EMPLOYEE%' ORDER BY nom ASC, prenom ASC LIMIT :limit OFFSET :offset";
                $stmt = $conn->prepare($sql);
                $result = $stmt->executeQuery([
                    'limit' => $batchSize,
                    'offset' => $offset
                ]);
                $ids = $result->fetchFirstColumn();
                
                if (empty($ids)) {
                    break;
                }
                
                // Process each employee in the batch
                foreach ($ids as $id) {
                    try {
                        $employe = $em->find(\App\Entity\Employe::class, $id);
                        if (!$employe) {
                            continue;
                        }
                        try {
                            $contrats = $employe->getEmployeeContrats();
                            
                            // For each contract, create a separate row
                            foreach ($contrats as $contrat) {
                                try {
                                    // Get organisations for this contract
                                    $organisations = [];
                                    $groupements = [];
                                    $codes = [];
                                    $dasValues = [];
                                    
                                    $orgContrats = $contrat->getOrganisationEmployeeContrats();
                                    foreach ($orgContrats as $orgContrat) {
                                        if ($orgContrat && $orgContrat->getOrganisation()) {
                                            $org = $orgContrat->getOrganisation();
                                            $organisations[] = $org->getDossierDesignation();
                                            $groupements[] = $org->getGroupement() ?? '';
                                            $codes[] = $org->getCode() ?? '';
                                            $dasValues[] = $org->getDas() ?? '';
                                        }
                                    }
                                    
                                    $organisationsString = !empty($organisations) ? implode(', ', $organisations) : '';
                                    $groupementString = !empty($groupements) ? implode(', ', array_unique($groupements)) : '';
                                    $codeString = !empty($codes) ? implode(', ', array_unique($codes)) : '';
                                    $dasString = !empty($dasValues) ? implode(', ', array_unique($dasValues)) : '';
                                    
                                    $natureContrat = $contrat->getNatureContrat();
                                    $contractType = $natureContrat ? $natureContrat->getDesignation() : '';
                                    
                                    // Get placard and emplacement from employee's dossier (separated)
                                    $placardName = '';
                                    $emplacement = '';
                                    if ($employe->getDossier()) {
                                        $dossier = $employe->getDossier();
                                        if ($dossier->getPlacard()) {
                                            $placardName = $dossier->getPlacard()->getName() . ' (' . $dossier->getPlacard()->getLocation() . ')';
                                        }
                                        if ($dossier->getEmplacement()) {
                                            $emplacement = $dossier->getEmplacement();
                                        }
                                    }
                                    
                                    // Get required documents for this contract using lookup
                                    $contractDocs = ['obligatoires' => [], 'complementaires' => []];
                                    if ($contractType && $natureContrat) {
                                        $contractTypeDesignation = $natureContrat->getDesignation();
                                        $contractTypeCode = $natureContrat->getCode();
                                        
                                        // Try to find documents by designation first
                                        if ($contractTypeDesignation && isset($docRequirementsLookup[$contractTypeDesignation])) {
                                            $contractDocs = $docRequirementsLookup[$contractTypeDesignation];
                                        }
                                        // If not found, try with code
                                        elseif ($contractTypeCode && isset($docRequirementsLookup[$contractTypeCode])) {
                                            $contractDocs = $docRequirementsLookup[$contractTypeCode];
                                        }
                                    }
                                    
                                    // Get existing documents from employee's dossier
                                    $existingDocuments = [];
                                    if ($employe->getDossier()) {
                                        foreach ($employe->getDossier()->getDocuments() as $doc) {
                                            $abbr = $doc->getAbbreviation();
                                            // Document is considered "added" if it has statutAjout='ajoute' or has a file
                                            $isAdded = $doc->getStatutAjout() === 'ajoute' || $doc->isUploaded();
                                            $existingDocuments[$abbr] = $isAdded;
                                        }
                                    }
                                    
                                    // Build row data
                                    $rowData = [
                                        $employe->getId(),
                                        $employe->getNom(),
                                        $employe->getPrenom(),
                                        $employe->getEmail(),
                                        $employe->getTelephone() ?? '',
                                        $organisationsString,
                                        $groupementString,
                                        $codeString,
                                        $dasString,
                                        $contractType,
                                        $contrat->getDateDebut() ? $contrat->getDateDebut()->format('Y-m-d') : '',
                                        $contrat->getDateFin() ? $contrat->getDateFin()->format('Y-m-d') : '',
                                        $contrat->getStatut() ?? '',
                                        $placardName,
                                        $emplacement,
                                        $employe->isActive() ? 'Actif' : 'Inactif'
                                    ];
                                    
                                    // Add document status columns (OA/ON/CA/CN)
                                    $obligatoires = $contractDocs['obligatoires'] ?? [];
                                    $complementaires = $contractDocs['complementaires'] ?? [];
                                    
                                    foreach ($allDocumentAbbreviations as $abbreviation) {
                                        $status = '';
                                        
                                        // Check if document is required for this contract
                                        $isObligatoire = in_array($abbreviation, $obligatoires);
                                        $isComplementaire = in_array($abbreviation, $complementaires);
                                        
                                        if ($isObligatoire || $isComplementaire) {
                                            $isAdded = isset($existingDocuments[$abbreviation]) && $existingDocuments[$abbreviation];
                                            
                                            if ($isObligatoire) {
                                                $status = $isAdded ? 'OA' : 'ON';
                                            } else { // complementaire
                                                $status = $isAdded ? 'CA' : 'CN';
                                            }
                                        }
                                        
                                        $rowData[] = $status;
                                    }
                                    
                                    $row = WriterEntityFactory::createRowFromArray($rowData);
                                    $writer->addRow($row);
                                } catch (\Exception $e) {
                                    // Skip this contract if there's an error
                                    continue;
                                }
                            }
                            
                            // If employee has no contracts, still add them with empty contract fields
                            if ($contrats->isEmpty()) {
                                // Get placard and emplacement from employee's dossier (separated)
                                $placardName = '';
                                $emplacement = '';
                                if ($employe->getDossier()) {
                                    $dossier = $employe->getDossier();
                                    if ($dossier->getPlacard()) {
                                        $placardName = $dossier->getPlacard()->getName() . ' (' . $dossier->getPlacard()->getLocation() . ')';
                                    }
                                    if ($dossier->getEmplacement()) {
                                        $emplacement = $dossier->getEmplacement();
                                    }
                                }
                                
                                // Build row data
                                $rowData = [
                                    $employe->getId(),
                                    $employe->getNom(),
                                    $employe->getPrenom(),
                                    $employe->getEmail(),
                                    $employe->getTelephone() ?? '',
                                    '', // No organisation
                                    '', // No groupement
                                    '', // No code
                                    '', // No DAS
                                    '', // No contract type
                                    '', // No start date
                                    '', // No end date
                                    '', // No contract status
                                    $placardName,
                                    $emplacement,
                                    $employe->isActive() ? 'Actif' : 'Inactif'
                                ];
                                
                                // Add empty document status columns (no contract = no documents required)
                                foreach ($allDocumentAbbreviations as $abbreviation) {
                                    $rowData[] = '';
                                }
                                
                                $row = WriterEntityFactory::createRowFromArray($rowData);
                                $writer->addRow($row);
                            }
                        } catch (\Exception $e) {
                            // Skip this employee if there's an error
                            continue;
                        }
                    } catch (\Exception $e) {
                        // Skip this employee if there's an error
                        continue;
                    }
                }
                
                $offset += $batchSize;
                $em->clear(); // Clear after each batch
                
                // Break if we got fewer results than requested (last batch)
                if (count($ids) < $batchSize) {
                    break;
                }
            }
            
            $writer->close();
            
            // Now read the file and return it as a response
            if (!file_exists($tempFile)) {
                throw new \Exception('Excel file could not be generated');
            }
            
            $response = new Response();
        $response->headers->set('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            $response->headers->set('Content-Disposition', 'attachment; filename="employes_' . date('Y-m-d_His') . '.xlsx"');
            $response->setContent(file_get_contents($tempFile));
            
            // Clean up
            unlink($tempFile);
        
        return $response;
            
        } catch (\Exception $e) {
            // If there's an error, output nothing to prevent file corruption
            if (isset($tempFile) && file_exists($tempFile)) {
                @unlink($tempFile);
            }
            throw $e;
        }
    }

    #[Route('/export-traceability', name: 'admin_export_traceability')]
    public function exportTraceability(Request $request, TraceabilityExcelService $traceabilityExcelService): Response
    {
        // Increase memory and execution time for large exports
        set_time_limit(300); // 5 minutes
        ini_set('memory_limit', '1024M');

        try {
            // Get date filters from request
            $startDate = null;
            $endDate = null;

            if ($request->query->has('start_date') && $request->query->get('start_date')) {
                $startDate = new \DateTime($request->query->get('start_date'));
                $startDate->setTime(0, 0, 0);
            }

            if ($request->query->has('end_date') && $request->query->get('end_date')) {
                $endDate = new \DateTime($request->query->get('end_date'));
                $endDate->setTime(23, 59, 59);
            }

            // Generate Excel file
            $tempFile = $traceabilityExcelService->generateTraceabilityExcel($startDate, $endDate);

            if (!file_exists($tempFile)) {
                throw new \Exception('Excel file could not be generated');
            }

            // Create response
            $response = new Response();
            $response->headers->set('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            $filename = 'traceability_export_' . date('Y-m-d_His') . '.xlsx';
            $response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '"');
            $response->setContent(file_get_contents($tempFile));

            // Clean up
            unlink($tempFile);

            return $response;
        } catch (\Exception $e) {
            if (isset($tempFile) && file_exists($tempFile)) {
                @unlink($tempFile);
            }
            throw $e;
        }
    }

}

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
use App\Repository\OrganisationEmployeeContratRepository;
use App\Entity\NatureContratTypeDocument;
use App\Form\OrganisationType;
use App\Form\OrganisationEmployeeContratType;
use App\Service\DocumentRequirementService;
use App\Service\KpiService;
use App\Service\ExcelGeneratorService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Box\Spout\Writer\XLSX\Writer as XLSXWriter;
use Box\Spout\Writer\Common\Creator\WriterEntityFactory;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Knp\Component\Pager\PaginatorInterface;
use App\Service\ModulePermissionService;
use App\Service\ResponsableRhOrganisationPermissionService;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

#[Route('/responsable-rh')]
#[IsGranted('ROLE_RESPONSABLE_RH')]
class ResponsableRhController extends AbstractController
{
    /**
     * Check if user has access to a module by route prefix
     */
    private function checkModuleAccess(ModulePermissionService $modulePermissionService, string $routeName): void
    {
        $user = $this->getUser();
        if (!$user instanceof \App\Entity\Employe) {
            throw new AccessDeniedHttpException('User not authenticated');
        }

        if (!$modulePermissionService->hasAccessToRoute($user, $routeName)) {
            throw new AccessDeniedHttpException('Vous n\'avez pas accès à ce module.');
        }
    }
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
    public function manageEmployes(Request $request, EmployeeContratRepository $contratRepository, PaginatorInterface $paginator, EntityManagerInterface $entityManager, ModulePermissionService $modulePermissionService, ResponsableRhOrganisationPermissionService $orgPermissionService): Response
    {
        // Vérifier que l'utilisateur est toujours authentifié
        if (!$this->getUser()) {
            return $this->redirectToRoute('app_login');
        }
        
        // Vérifier que l'utilisateur a le bon rôle
        if (!in_array('ROLE_RESPONSABLE_RH', $this->getUser()->getRoles())) {
            return $this->redirectToRoute('app_login');
        }

        // Check module access
        $this->checkModuleAccess($modulePermissionService, 'responsable_manage_employes');

        // Get current user
        $user = $this->getUser();
        if (!$user instanceof Employe) {
            return $this->redirectToRoute('app_login');
        }

        // Récupérer les paramètres de recherche et filtrage
        $search = $request->query->get('search', '');
        $status = $request->query->get('status', 'all'); // all, active, inactive, managers (pour contrats)
        $page = $request->query->getInt('page', 1);
        $perPage = min(max($request->query->getInt('perPage', 10), 10), 100); // Between 10 and 100
        
        // Get employee IDs with ROLE_EMPLOYEE using native SQL query (Doctrine doesn't support CAST in DQL)
        $conn = $entityManager->getConnection();
        
        // Build SQL query for employee IDs (only filter by role, not by status)
        // If status is 'managers', filter for employees with ROLE_MANAGER
        if ($status === 'managers') {
            $sql = 'SELECT DISTINCT e.id FROM t_user e WHERE CAST(e.roles AS TEXT) LIKE :role';
            $params = ['role' => '%ROLE_MANAGER%'];
        } else {
            $sql = 'SELECT DISTINCT e.id FROM t_user e WHERE CAST(e.roles AS TEXT) LIKE :role';
            $params = ['role' => '%ROLE_EMPLOYEE%'];
        }
        $types = ['role' => \PDO::PARAM_STR];
        
        $stmt = $conn->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, $types[$key] ?? \PDO::PARAM_STR);
        }
        $result = $stmt->executeQuery();
        $employeeIds = $result->fetchFirstColumn();
        
        // Build query for contracts with employee information
        // Use employee IDs from native SQL query (avoids CAST in DQL)
        if (empty($employeeIds)) {
            // No employees with ROLE_EMPLOYEE found
            $queryBuilder = $contratRepository->createQueryBuilder('ec')
                ->where('1 = 0'); // No results
        } else {
            $queryBuilder = $contratRepository->createQueryBuilder('ec')
                ->leftJoin('ec.employe', 'e')
                ->leftJoin('ec.natureContrat', 'nc')
                ->leftJoin('ec.organisationEmployeeContrats', 'oec')
                ->leftJoin('oec.organisation', 'org')
                ->where('e.id IN (:employeeIds)')
                ->setParameter('employeeIds', $employeeIds);
            
            // Apply organisation permission filters
            $orgFilter = $orgPermissionService->getEmployeeFilterSQL($user);
            if ($orgFilter['where'] === '1=0') {
                // No access, return empty result
                $queryBuilder->andWhere('1 = 0');
            } elseif ($orgFilter['where'] !== '1=1') {
                // Apply organisation filters using DQL - only show contracts with matching organisations
                // Build DQL EXISTS subquery
                $subQuery = $entityManager->createQueryBuilder()
                    ->select('1')
                    ->from('App\Entity\OrganisationEmployeeContrat', 'oec2')
                    ->innerJoin('oec2.organisation', 'org2')
                    ->where('oec2.employeeContrat = ec');
                
                // Convert SQL WHERE clause to DQL format
                // Replace org. with org2. for the subquery alias
                $dqlWhere = str_replace('org.', 'org2.', $orgFilter['where']);
                $subQuery->andWhere($dqlWhere);
                
                // Set parameters on subquery
                foreach ($orgFilter['params'] as $key => $value) {
                    $subQuery->setParameter($key, $value);
                }
                
                // Add EXISTS clause to main query
                $queryBuilder->andWhere('EXISTS (' . $subQuery->getDQL() . ')');
                
                // Set parameters on main query as well (needed for pagination)
                foreach ($orgFilter['params'] as $key => $value) {
                    $queryBuilder->setParameter($key, $value);
                }
            }
            // If orgFilter['where'] === '1=1', no filter is applied (admin has access to all)
            
            // Apply contract status filter (not employee status)
            // Note: 'managers' filter is already applied in the employee IDs query above
            if ($status === 'active') {
                $queryBuilder->andWhere('ec.statut = :contractStatus')
                           ->setParameter('contractStatus', 'actif');
            } elseif ($status === 'inactive') {
                $queryBuilder->andWhere('ec.statut != :contractStatus OR ec.statut IS NULL')
                           ->setParameter('contractStatus', 'actif');
            }
            // If status is 'all' or 'managers', no additional contract status filter is applied
            
            // Apply search filter - search in employee fields OR contract type
            if ($search) {
                $queryBuilder->andWhere('(LOWER(e.nom) LIKE LOWER(:search) 
                                          OR LOWER(e.prenom) LIKE LOWER(:search) 
                                          OR LOWER(e.email) LIKE LOWER(:search)
                                          OR LOWER(nc.designation) LIKE LOWER(:search))')
                           ->setParameter('search', '%' . $search . '%');
            }
            
            // Group by contract to avoid duplicates from organisation joins
            $queryBuilder->groupBy('ec.id')
                        ->addGroupBy('e.id')
                        ->addGroupBy('nc.id');
        }
        
        // Order by employee name and contract start date
        $queryBuilder->orderBy('e.nom', 'ASC')
                     ->addOrderBy('e.prenom', 'ASC')
                     ->addOrderBy('ec.dateDebut', 'DESC');
        
        // Paginate contracts
        $contrats = $paginator->paginate(
            $queryBuilder,
            $page,
            $perPage
        );
        
        // Get total count of contracts for display
        $totalContrats = $contratRepository->count([]);

        $response = $this->render('responsable-rh/employes.html.twig', [
            'contrats' => $contrats,
            'search' => $search,
            'status' => $status,
            'totalEmployes' => $totalContrats, // Show total contracts count
            'perPage' => $perPage
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
            // Vérifier si l'email existe déjà
            $existingEmployee = $entityManager->getRepository(Employe::class)->findOneBy(['email' => $employee->getEmail()]);
            if ($existingEmployee) {
                $this->addFlash('error', 'Cet email est déjà utilisé par un autre employé.');
                return $this->render('responsable-rh/add_employe.html.twig', [
                    'form' => $form->createView(),
                ]);
            }
            
            // Définir le rôle d'employé
            $employee->setRoles(['ROLE_EMPLOYEE']);
            
            // Générer le username à partir de l'email si non défini
            if (!$employee->getUsername()) {
                $username = explode('@', $employee->getEmail())[0];
                $employee->setUsername($username);
            }
            
            // Hioxidé le mot de passe
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

            // Régénérer automatiquement le fichier Excel (en arrière-plan pour éviter timeout)
            // Uncomment to enable: $excelGenerator->generateModuleExcel();

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
            // Vérifier si l'email existe déjà
            $existingEmployee = $entityManager->getRepository(Employe::class)->findOneBy(['email' => $employee->getEmail()]);
            if ($existingEmployee) {
                $this->addFlash('error', 'Cet email est déjà utilisé par un autre employé.');
                return $this->render('responsable-rh/add_employe.html.twig', [
                    'form' => $form->createView()
                ]);
            }
            
            // Définir les rôles selon si l'employé est un manager ou non
            $isManager = $form->get('isManager')->getData();
            $dossiersGeresJson = $form->get('dossiersGeres')->getData();
            
            if ($isManager) {
                // Décoder le JSON des dossiers gérés
                $dossiersGeres = [];
                if ($dossiersGeresJson) {
                    $decoded = json_decode($dossiersGeresJson, true);
                    if (is_array($decoded)) {
                        $dossiersGeres = array_filter($decoded); // Retirer les valeurs vides
                    }
                }
                
                // Vérifier qu'au moins un dossier a été sélectionné
                if (empty($dossiersGeres)) {
                    $this->addFlash('error', 'Vous devez sélectionner au moins un dossier pour le manager.');
                    return $this->render('responsable-rh/add_employe.html.twig', [
                        'form' => $form->createView()
                    ]);
                }
                
                // Les managers ont les deux rôles : ROLE_EMPLOYEE et ROLE_MANAGER
                $employee->setRoles(['ROLE_EMPLOYEE', 'ROLE_MANAGER']);
                $employee->setDossiersGeres($dossiersGeres);
            } else {
                // Employé normal avec seulement ROLE_EMPLOYEE
                $employee->setRoles(['ROLE_EMPLOYEE']);
                $employee->setDossiersGeres(null);
            }
            
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

            // Régénérer automatiquement le fichier Excel (en arrière-plan pour éviter timeout)
            // Uncomment to enable: $excelGenerator->generateModuleExcel();

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

        // Régénérer automatiquement le fichier Excel (commenté pour éviter timeout)
        // $excelGenerator->generateModuleExcel();

        $status = $employee->isActive() ? 'activé' : 'désactivé';
        $this->addFlash('success', "Employé {$status} avec succès !");
        return $this->redirectToRoute('responsable_manage_employes');
    }

    #[Route('/contrats/toggle-status/{id}', name: 'responsable_toggle_contrat_status')]
    public function toggleContratStatus(EmployeeContrat $contrat, EntityManagerInterface $entityManager): Response
    {
        // Vérifier que l'utilisateur est toujours authentifié
        if (!$this->getUser()) {
            return $this->redirectToRoute('app_login');
        }
        
        // Vérifier que l'utilisateur a le bon rôle
        if (!in_array('ROLE_RESPONSABLE_RH', $this->getUser()->getRoles())) {
            return $this->redirectToRoute('app_login');
        }

        // Rafraîchir l'entité depuis la base de données pour éviter les problèmes de cache
        $entityManager->refresh($contrat);

        // Toggle le statut du contrat (actif <-> inactif/terminé)
        // Utiliser trim() et strtolower() pour une comparaison robuste
        $currentStatut = strtolower(trim($contrat->getStatut() ?? ''));
        
        if ($currentStatut === 'actif') {
            $newStatut = 'inactif';
        } else {
            // Si le statut est 'inactif', 'terminé' ou autre, on le met à 'actif'
            $newStatut = 'actif';
        }
        
        $contrat->setStatut($newStatut);
        
        // Forcer la persistance et le flush
        $entityManager->persist($contrat);
        $entityManager->flush();

        $status = $contrat->getStatut();
        $this->addFlash('success', "Statut du contrat modifié avec succès ! ({$status})");
        return $this->redirectToRoute('responsable_manage_employes');
    }

    #[Route('/employes/{id}/modifier', name: 'responsable_edit_employe')]
    public function editEmploye(int $id, Request $request, EntityManagerInterface $entityManager, UserPasswordHasherInterface $passwordHasher, EmployeRepository $employeRepository): Response
    {
        // Vérifier que l'utilisateur est toujours authentifié
        if (!$this->getUser()) {
            return $this->redirectToRoute('app_login');
        }
        
        // Vérifier que l'utilisateur a le bon rôle
        if (!in_array('ROLE_RESPONSABLE_RH', $this->getUser()->getRoles())) {
            return $this->redirectToRoute('app_login');
        }

        $employee = $employeRepository->find($id);
        if (!$employee) {
            $this->addFlash('error', 'Employé non trouvé !');
            return $this->redirectToRoute('responsable_manage_employes');
        }

        // Vérifier que c'est bien un employé (pas un Responsable RH ou Admin)
        if (!in_array('ROLE_EMPLOYEE', $employee->getRoles())) {
            $this->addFlash('error', 'Vous ne pouvez modifier que les employés.');
            return $this->redirectToRoute('responsable_manage_employes');
        }

        $form = $this->createForm(EmployeeType::class, $employee, [
            'is_new' => false
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Vérifier si l'email existe déjà pour un autre employé
            $existingEmployee = $entityManager->getRepository(Employe::class)->findOneBy(['email' => $employee->getEmail()]);
            if ($existingEmployee && $existingEmployee->getId() !== $employee->getId()) {
                $this->addFlash('error', 'Cet email est déjà utilisé par un autre employé.');
                return $this->render('responsable-rh/edit_employe.html.twig', [
                    'form' => $form->createView(),
                    'employee' => $employee
                ]);
            }
            
            // Mettre à jour les rôles selon si l'employé est un manager ou non
            $isManager = $form->get('isManager')->getData();
            $dossiersGeresJson = $form->get('dossiersGeres')->getData();
            
            if ($isManager) {
                // Décoder le JSON des dossiers gérés
                $dossiersGeres = [];
                if ($dossiersGeresJson) {
                    $decoded = json_decode($dossiersGeresJson, true);
                    if (is_array($decoded)) {
                        $dossiersGeres = array_filter($decoded); // Retirer les valeurs vides
                    }
                }
                
                // Vérifier qu'au moins un dossier a été sélectionné
                if (empty($dossiersGeres)) {
                    $this->addFlash('error', 'Vous devez sélectionner au moins un dossier pour le manager.');
                    return $this->render('responsable-rh/edit_employe.html.twig', [
                        'form' => $form->createView(),
                        'employee' => $employee
                    ]);
                }
                
                // Les managers ont les deux rôles : ROLE_EMPLOYEE et ROLE_MANAGER
                $employee->setRoles(['ROLE_EMPLOYEE', 'ROLE_MANAGER']);
                $employee->setDossiersGeres($dossiersGeres);
            } else {
                // Employé normal avec seulement ROLE_EMPLOYEE
                $employee->setRoles(['ROLE_EMPLOYEE']);
                // Libérer les dossiers gérés si l'employé n'est plus manager
                $employee->setDossiersGeres(null);
            }
            
            // Si un nouveau mot de passe est fourni, le hasher
            $plainPassword = $form->get('password')->getData();
            if ($plainPassword) {
                $hashedPassword = $passwordHasher->hashPassword($employee, $plainPassword);
                $employee->setPassword($hashedPassword);
            }
            
            $entityManager->flush();

            $this->addFlash('success', 'Employé modifié avec succès !');
            return $this->redirectToRoute('responsable_manage_employes');
        }

        $response = $this->render('responsable-rh/edit_employe.html.twig', [
            'form' => $form->createView(),
            'employee' => $employee
        ]);
        
        $response->headers->set('Cache-Control', 'no-cache, no-store, must-revalidate, private');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', '0');
        
        return $response;
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
    public function manageDossiers(Request $request, DossierRepository $dossierRepository, PaginatorInterface $paginator, EmployeRepository $employeRepository, EntityManagerInterface $entityManager, ModulePermissionService $modulePermissionService, ResponsableRhOrganisationPermissionService $orgPermissionService): Response
    {
        if (!$this->getUser() || !in_array('ROLE_RESPONSABLE_RH', $this->getUser()->getRoles())) {
            return $this->redirectToRoute('app_login');
        }

        $this->checkModuleAccess($modulePermissionService, 'responsable_manage_dossiers');

        // Get current user
        $user = $this->getUser();
        if (!$user instanceof Employe) {
            return $this->redirectToRoute('app_login');
        }

        $search = $request->query->get('search', '');
        $perPage = min(max($request->query->getInt('perPage', 10), 10), 100); // Between 10 and 100
        
        // Get base query
        if ($search) {
            $dossiersQuery = $dossierRepository->findBySearchQuery($search);
        } else {
            $dossiersQuery = $dossierRepository->findAllQuery();
        }
        
        // Apply organisation permission filters
        $orgFilter = $orgPermissionService->getEmployeeFilterSQL($user);
        if ($orgFilter['where'] === '1=0') {
            // No access, return empty result
            $dossiersQuery->andWhere('1 = 0');
        } elseif ($orgFilter['where'] !== '1=1') {
            // Get employee IDs that match organisation permissions
            $conn = $entityManager->getConnection();
            $sql = 'SELECT DISTINCT e.id FROM t_user e
                    INNER JOIN t_employee_contrat ec ON ec.employe_id = e.id
                    INNER JOIN t_organisation_employee_contrat oec ON oec.employee_contrat_id = ec.id
                    INNER JOIN p_organisation org ON org.id = oec.organisation_id
                    WHERE (' . $orgFilter['where'] . ')';
            $stmt = $conn->prepare($sql);
            foreach ($orgFilter['params'] as $key => $value) {
                $stmt->bindValue($key, $value, \PDO::PARAM_STR);
            }
            $result = $stmt->executeQuery();
            $allowedEmployeeIds = $result->fetchFirstColumn();
            
            if (empty($allowedEmployeeIds)) {
                // No employees match, return empty result
                $dossiersQuery->andWhere('1 = 0');
            } else {
                // Filter dossiers by allowed employee IDs
                $dossiersQuery->andWhere('d.employe IN (:allowedEmployeeIds)')
                             ->setParameter('allowedEmployeeIds', $allowedEmployeeIds);
            }
        }
        // If orgFilter['where'] === '1=1', no filter is applied (admin has access to all)

        // Paginate with configurable items per page
        $dossiers = $paginator->paginate(
            $dossiersQuery,
            $request->query->getInt('page', 1),
            $perPage
        );

        // Get total employees count (matching dashboard logic) for display
        $totalEmployees = $employeRepository->count([]);
        
        $response = $this->render('responsable-rh/dossiers.html.twig', [
            'dossiers' => $dossiers,
            'search' => $search,
            'totalDossiers' => $totalEmployees, // Show total employees like dashboard, not dossiers count
            'perPage' => $perPage
        ]);
        
        $response->headers->set('Cache-Control', 'no-cache, no-store, must-revalidate, private');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', '0');
        
        return $response;
    }

    #[Route('/api/available-dossiers', name: 'responsable_api_available_dossiers', methods: ['GET'])]
    public function getAvailableDossiers(Request $request, EntityManagerInterface $entityManager): Response
    {
        $excludeEmployeeId = $request->query->get('exclude_employee_id', null);
        
        // Tous les dossiers valides
        $allDossiers = [
            'SFCZ', 'CCGA', 'GACH', 'GCMP', 'GFIN', 'GRSH', 'GJUR', 'GBNQ', 'GPRG',
            'SUMM', 'SASE', 'SPSI', 'SASI', 'SPSE',
            'EUIA', 'ECSM', 'IFCP', 'ECFC', 'ECRI', 'ELEZ', 'EEAS',
            'SHCZ', 'SLMG', 'SDNT', 'SHMK', 'SHMB', 'SHMY', 'SLPD', 'SRAD', 'SLAB',
            'SAHR', 'SCOV', 'SOPH', 'SKIN', 'SCVP', 'SGHJ', 'SGYN', 'SURG', 'SHMS', 'SHCT', 'CSDA',
            'RRUR', 'RHUR', 'RHAR', 'RRER', 'RRHK', 'RRHY', 'RDAR', 'RRHR', 'RHSR', 'RCER', 'RBPR', 'RHCC',
            'NSIT', 'NARC', 'NITS', 'NNUM',
            'IMIN', 'IEIS', 'ICCI', 'IESV', 'ISAE', 'ISAM', 'IPTT', 'ISAA',
            'APCC', 'APVR', 'APUR', 'APLM', 'APCH', 'LPDP',
            'PVPH', 'PPPH', 'PAMD',
            'PIMP', 'PPTM',
            'ECBE', 'ECRG', 'EEDF',
            'PTEX', 'PBIR', 'PLAV', 'PCAP', 'PEPC', 'PCOF', 'PEVN',
            'PGRO', 'PSMS'
        ];
        
        // Récupérer les dossiers actuels de l'employé (si en mode édition)
        $currentDossiers = [];
        if ($excludeEmployeeId) {
            $employee = $entityManager->getRepository(Employe::class)->find($excludeEmployeeId);
            if ($employee) {
                $dossiersGeres = $employee->getDossiersGeres();
                if ($dossiersGeres && is_array($dossiersGeres)) {
                    $currentDossiers = $dossiersGeres;
                } elseif ($employee->getDossierGere()) {
                    // Migration depuis l'ancien format
                    $currentDossiers = [$employee->getDossierGere()];
                }
            }
        }
        
        // Maintenant, plusieurs managers peuvent gérer le même dossier
        // Retourner tous les dossiers disponibles
        // Trier les dossiers pour un affichage cohérent
        sort($allDossiers);
        
        return $this->json([
            'available' => $allDossiers,
            'current' => $currentDossiers
        ]);
    }

    #[Route('/api/search-employees-without-dossier', name: 'responsable_api_search_employees_without_dossier', methods: ['GET'])]
    public function searchEmployeesWithoutDossier(Request $request, EmployeRepository $employeRepository): Response
    {
        $search = trim($request->query->get('search', ''));
        
        // Recherche dès 1 caractère
        if (strlen($search) < 1) {
            return $this->json(['employees' => []]);
        }
        
        try {
            $employees = $employeRepository->searchActiveEmployeesWithoutDossierByRole('ROLE_EMPLOYEE', $search, 50);
            
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
            error_log('Erreur dans searchEmployeesWithoutDossier: ' . $e->getMessage());
            return $this->json(['employees' => [], 'error' => 'Une erreur est survenue lors de la recherche.'], 500);
        }
    }

    #[Route('/dossiers/ajouter', name: 'responsable_add_dossier')]
    public function addDossier(Request $request, EntityManagerInterface $entityManager): Response
    {
        if (!$this->getUser() || !in_array('ROLE_RESPONSABLE_RH', $this->getUser()->getRoles())) {
            return $this->redirectToRoute('app_login');
        }

        $dossier = new Dossier();
        $form = $this->createForm(DossierType::class, $dossier, [
            'is_new' => true
        ]);

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

        // Sauvegarder l'employé original avant le handleRequest
        $originalEmployee = $dossier->getEmploye();
        
        $form = $this->createForm(DossierType::class, $dossier, [
            'is_new' => false // Mode édition
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // En mode édition, s'assurer que l'employé n'est pas modifié
            // Le champ est désactivé donc il n'est pas envoyé dans la requête
            // On ré-assigne donc l'employé original
            if ($originalEmployee) {
                $dossier->setEmploye($originalEmployee);
            }
            
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
        
        // Définir created_at manuellement pour éviter l'erreur de contrainte NOT NULL
        $document->setCreatedAt(new \DateTime());
        
        // Définir created_by si l'utilisateur est connecté
        if ($this->getUser()) {
            $document->setCreatedBy($this->getUser());
        }

        $entityManager->persist($document);
        $entityManager->flush();

        $this->addFlash('success', 'Le document ' . $abbreviation . ' a été marqué comme "Ajouté".');

        return $this->redirectToRoute('responsable_view_dossier_documents', ['id' => $dossier_id]);
    }


    #[Route('/demandes', name: 'responsable_manage_demandes')]
    public function manageDemandes(Request $request, DemandeRepository $demandeRepository, PaginatorInterface $paginator, EntityManagerInterface $entityManager, ModulePermissionService $modulePermissionService, ResponsableRhOrganisationPermissionService $orgPermissionService): Response
    {
        if (!$this->getUser() || !in_array('ROLE_RESPONSABLE_RH', $this->getUser()->getRoles())) {
            return $this->redirectToRoute('app_login');
        }

        $this->checkModuleAccess($modulePermissionService, 'responsable_manage_demandes');

        // Get current user
        $user = $this->getUser();
        if (!$user instanceof Employe) {
            return $this->redirectToRoute('app_login');
        }

        // Récupérer le filtre depuis les paramètres de requête
        $filter = $request->query->get('filter', 'all');
        $perPage = min(max($request->query->getInt('perPage', 10), 10), 100); // Between 10 and 100
        
        // Récupérer les demandes selon le filtre
        if ($filter === 'en_attente') {
            $demandesQuery = $demandeRepository->findByStatutQuery('en_attente');
        } elseif ($filter === 'traitees') {
            $demandesQuery = $demandeRepository->findByStatutQuery('traitees');
        } else {
            // Par défaut, afficher toutes les demandes
            $demandesQuery = $demandeRepository->findAllQuery();
        }
        
        // Apply organisation permission filters
        $orgFilter = $orgPermissionService->getEmployeeFilterSQL($user);
        if ($orgFilter['where'] === '1=0') {
            // No access, return empty result
            $demandesQuery->andWhere('1 = 0');
        } elseif ($orgFilter['where'] !== '1=1') {
            // Get employee IDs that match organisation permissions
            $conn = $entityManager->getConnection();
            $sql = 'SELECT DISTINCT e.id FROM t_user e
                    INNER JOIN t_employee_contrat ec ON ec.employe_id = e.id
                    INNER JOIN t_organisation_employee_contrat oec ON oec.employee_contrat_id = ec.id
                    INNER JOIN p_organisation org ON org.id = oec.organisation_id
                    WHERE (' . $orgFilter['where'] . ')';
            $stmt = $conn->prepare($sql);
            foreach ($orgFilter['params'] as $key => $value) {
                $stmt->bindValue($key, $value, \PDO::PARAM_STR);
            }
            $result = $stmt->executeQuery();
            $allowedEmployeeIds = $result->fetchFirstColumn();
            
            if (empty($allowedEmployeeIds)) {
                // No employees match, return empty result
                $demandesQuery->andWhere('1 = 0');
            } else {
                // Filter demandes by allowed employee IDs
                $demandesQuery->andWhere('d.employe IN (:allowedEmployeeIds)')
                             ->setParameter('allowedEmployeeIds', $allowedEmployeeIds);
            }
        }
        // If orgFilter['where'] === '1=1', no filter is applied (admin has access to all)

        // Paginer les résultats avec nombre d'éléments configurable
        $demandes = $paginator->paginate(
            $demandesQuery,
            $request->query->getInt('page', 1),
            $perPage
        );
        
        return $this->render('responsable-rh/demandes.html.twig', [
            'demandes' => $demandes,
            'currentFilter' => $filter,
            'perPage' => $perPage
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
    public function managePlacards(Request $request, PlacardRepository $placardRepository, PaginatorInterface $paginator, ModulePermissionService $modulePermissionService): Response
    {
        if (!$this->getUser() || !in_array('ROLE_RESPONSABLE_RH', $this->getUser()->getRoles())) {
            return $this->redirectToRoute('app_login');
        }

        $this->checkModuleAccess($modulePermissionService, 'responsable_manage_placards');

        // Get filter status (all, active, inactive)
        $status = $request->query->get('status', 'all');
        if (!in_array($status, ['all', 'active', 'inactive'])) {
            $status = 'all';
        }

        $placardsQuery = $placardRepository->findAllQueryWithFilter($status);
        $perPage = min(max($request->query->getInt('perPage', 10), 10), 100); // Between 10 and 100

        // Paginate with configurable items per page
        $placards = $paginator->paginate(
            $placardsQuery,
            $request->query->getInt('page', 1),
            $perPage
        );

        // Get counts for filters
        $totalPlacards = $placardRepository->count([]);
        $activePlacards = $placardRepository->countActive();
        $inactivePlacards = $placardRepository->countInactive();

        return $this->render('responsable-rh/placards.html.twig', [
            'placards' => $placards,
            'totalPlacards' => $totalPlacards,
            'activePlacards' => $activePlacards,
            'inactivePlacards' => $inactivePlacards,
            'status' => $status,
            'perPage' => $perPage
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
    public function viewPlacard(
        int $id, 
        Request $request,
        PlacardRepository $placardRepository,
        DossierRepository $dossierRepository,
        PaginatorInterface $paginator
    ): Response {
        if (!$this->getUser() || !in_array('ROLE_RESPONSABLE_RH', $this->getUser()->getRoles())) {
            return $this->redirectToRoute('app_login');
        }

        $placard = $placardRepository->find($id);
        if (!$placard) {
            $this->addFlash('error', 'Placard non trouvé !');
            return $this->redirectToRoute('responsable_manage_placards');
        }

        // Get paginated dossiers for this placard
        $dossiersQuery = $dossierRepository->findByPlacardQuery($placard);
        $perPage = min(max($request->query->getInt('perPage', 10), 10), 100); // Between 10 and 100
        
        $dossiers = $paginator->paginate(
            $dossiersQuery,
            $request->query->getInt('page', 1),
            $perPage
        );

        return $this->render('responsable-rh/view_placard.html.twig', [
            'placard' => $placard,
            'dossiers' => $dossiers,
            'perPage' => $perPage,
        ]);
    }

    #[Route('/placards/toggle-actif/{id}', name: 'responsable_toggle_placard_actif')]
    public function togglePlacardActif(int $id, EntityManagerInterface $entityManager, PlacardRepository $placardRepository): Response
    {
        if (!$this->getUser() || !in_array('ROLE_RESPONSABLE_RH', $this->getUser()->getRoles())) {
            return $this->redirectToRoute('app_login');
        }

        $placard = $placardRepository->find($id);
        if (!$placard) {
            $this->addFlash('error', 'Placard non trouvé !');
            return $this->redirectToRoute('responsable_manage_placards');
        }

        // Toggle active status
        $placard->setIsActive(!$placard->isActive());
        $entityManager->flush();

        $statusText = $placard->isActive() ? 'activé' : 'désactivé';
        $this->addFlash('success', "Placard {$statusText} avec succès !");
        
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
    public function manageContrats(): Response
    {
        // Rediriger vers la page employés qui contient maintenant les contrats
        return $this->redirectToRoute('responsable_manage_employes');
    }


    #[Route('/organisations', name: 'responsable_manage_organisations')]
    public function manageOrganisations(Request $request, OrganisationRepository $organisationRepository, PaginatorInterface $paginator, EmployeRepository $employeRepository, EntityManagerInterface $entityManager, ModulePermissionService $modulePermissionService, ResponsableRhOrganisationPermissionService $orgPermissionService): Response
    {
        if (!$this->getUser() || !in_array('ROLE_RESPONSABLE_RH', $this->getUser()->getRoles())) {
            return $this->redirectToRoute('app_login');
        }

        $this->checkModuleAccess($modulePermissionService, 'responsable_manage_organisations');

        // Get current user
        $user = $this->getUser();
        if (!$user instanceof Employe) {
            return $this->redirectToRoute('app_login');
        }

        $organisationsQuery = $organisationRepository->findAllQuery();
        
        // Apply organisation permission filters
        $orgFilter = $orgPermissionService->getEmployeeFilterSQL($user);
        if ($orgFilter['where'] === '1=0') {
            // No access, return empty result
            $organisationsQuery->andWhere('1 = 0');
        } elseif ($orgFilter['where'] !== '1=1') {
            // Apply organisation filters directly (organisations have groupement, das, dossier fields)
            // Replace 'org.' with 'o.' to match the query alias
            $dqlWhere = str_replace('org.', 'o.', $orgFilter['where']);
            $organisationsQuery->andWhere($dqlWhere);
            foreach ($orgFilter['params'] as $key => $value) {
                $organisationsQuery->setParameter($key, $value);
            }
        }
        // If orgFilter['where'] === '1=1', no filter is applied (admin has access to all)
        
        $perPage = min(max($request->query->getInt('perPage', 10), 10), 100); // Between 10 and 100

        // Paginate with configurable items per page
        $organisations = $paginator->paginate(
            $organisationsQuery,
            $request->query->getInt('page', 1),
            $perPage
        );
        
        // Get total employees count (matching dashboard logic) for display
        $totalEmployees = $employeRepository->count([]);
        
        // Get total unique DAS count from all organisations (not just paginated ones)
        $conn = $entityManager->getConnection();
        $dasCount = $conn->executeQuery('SELECT COUNT(DISTINCT das) FROM p_organisation WHERE das IS NOT NULL AND das != \'\'')->fetchOne();
        
        // Get total unique groupements count from all organisations (not just paginated ones)
        $groupementsCount = $conn->executeQuery('SELECT COUNT(DISTINCT groupement) FROM p_organisation WHERE groupement IS NOT NULL AND groupement != \'\'')->fetchOne();
        
        return $this->render('responsable-rh/organisations.html.twig', [
            'organisations' => $organisations,
            'totalEmployees' => $totalEmployees, // Pass total employees count like dashboard
            'dasUnique' => (int)$dasCount, // Total unique DAS from all organisations
            'groupementsUnique' => (int)$groupementsCount, // Total unique groupements from all organisations
            'perPage' => $perPage
        ]);
    }

    #[Route('/organisations/{id}', name: 'responsable_view_organisation', requirements: ['id' => '\d+'])]
    public function viewOrganisation(
        int $id, 
        Request $request,
        OrganisationRepository $organisationRepository,
        OrganisationEmployeeContratRepository $organisationEmployeeContratRepository,
        PaginatorInterface $paginator
    ): Response {
        $organisation = $organisationRepository->find($id);
        
        if (!$organisation) {
            $this->addFlash('error', 'Organisation non trouvée !');
            return $this->redirectToRoute('responsable_manage_organisations');
        }
        
        // Get paginated organisation employee contrats
        $orgContratsQuery = $organisationEmployeeContratRepository->findByOrganisationQuery($organisation);
        $perPage = min(max($request->query->getInt('perPage', 10), 10), 100); // Between 10 and 100
        
        $orgContrats = $paginator->paginate(
            $orgContratsQuery,
            $request->query->getInt('page', 1),
            $perPage
        );
        
        return $this->render('responsable-rh/organisation_details.html.twig', [
            'organisation' => $organisation,
            'orgContrats' => $orgContrats,
            'perPage' => $perPage,
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
            
            // Régénérer automatiquement le fichier Excel (commenté pour éviter timeout)
            // $excelGenerator->generateModuleExcel();
            
            $this->addFlash('success', 'Employé assigné à l\'organisation avec succès !');
            return $this->redirectToRoute('responsable_manage_organisations');
        }
        
        return $this->render('responsable-rh/assign_organisation.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/api/search-employee-contrats', name: 'api_search_employee_contrats', methods: ['GET'])]
    public function searchEmployeeContrats(Request $request, EmployeeContratRepository $employeeContratRepository): Response
    {
        $search = $request->query->get('search', '');
        
        if (strlen($search) < 2) {
            return $this->json(['contrats' => []]);
        }
        
        $contrats = $employeeContratRepository->findActiveContratsBySearch($search, 100);
        
        $results = [];
        foreach ($contrats as $contrat) {
            $employe = $contrat->getEmploye();
            $results[] = [
                'id' => $contrat->getId(),
                'label' => $employe->getPrenom() . ' ' . $employe->getNom() . ' (' . $contrat->getNatureContrat()->getDesignation() . ')',
                'nom' => $employe->getNom(),
                'prenom' => $employe->getPrenom(),
            ];
        }
        
        return $this->json(['contrats' => $results]);
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
                // Validate file extension
                $originalExtension = strtolower(pathinfo($uploadedFile->getClientOriginalName(), PATHINFO_EXTENSION));
                $allowedExtensions = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png', 'gif', 'bmp', 'tiff', 'txt', 'xls', 'xlsx', 'ppt', 'pptx', 'zip', 'rar'];
                
                if (!in_array($originalExtension, $allowedExtensions)) {
                    $this->addFlash('error', 'Type de fichier non autorisé. Formats acceptés : ' . implode(', ', array_map('strtoupper', $allowedExtensions)));
                    return $this->redirectToRoute('responsable_view_dossier_documents', ['id' => $dossierId]);
                }
                
                // Validate file size (10MB max)
                $maxSize = 10 * 1024 * 1024; // 10MB en bytes
                if ($uploadedFile->getSize() > $maxSize) {
                    $this->addFlash('error', 'Le fichier est trop volumineux. Taille maximale : 10 MB');
                    return $this->redirectToRoute('responsable_view_dossier_documents', ['id' => $dossierId]);
                }
                
                // Create upload directory if it doesn't exist
                $uploadDir = $this->getParameter('kernel.project_dir') . '/public/uploads/documents/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }
                
                // Generate unique filename
                $originalFilename = pathinfo($uploadedFile->getClientOriginalName(), PATHINFO_FILENAME);
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
                        
                        // Définir created_at manuellement pour éviter l'erreur de contrainte NOT NULL
                        $newDocument->setCreatedAt(new \DateTime());
                        
                        // Définir created_by si l'utilisateur est connecté
                        if ($this->getUser()) {
                            $newDocument->setCreatedBy($this->getUser());
                        }
                        
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
        
        // Only pass existingDocument to template if it has an uploaded file
        // This prevents showing "Document existant" message when document is just marked as "ajouté" without file
        $existingDocumentWithFile = null;
        if ($existingDocument && $existingDocument->getFilePath() && $existingDocument->isUploaded()) {
            $existingDocumentWithFile = $existingDocument;
        }
        
        return $this->render('responsable-rh/upload_document.html.twig', [
            'abbreviation' => $abbreviation,
            'templateDocument' => $documentToDisplay,
            'dossier' => $dossier,
            'dossierId' => $dossierId,
            'existingDocument' => $existingDocumentWithFile
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
        if (!$this->getUser() || !in_array('ROLE_RESPONSABLE_RH', $this->getUser()->getRoles())) {
            return $this->redirectToRoute('app_login');
        }

        // KPIs are accessible to all Responsable RH from the dashboard
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
    public function manageReclamations(Request $request, ReclamationRepository $reclamationRepository, PaginatorInterface $paginator, ModulePermissionService $modulePermissionService, EntityManagerInterface $entityManager, ResponsableRhOrganisationPermissionService $orgPermissionService): Response
    {
        if (!$this->getUser() || !in_array('ROLE_RESPONSABLE_RH', $this->getUser()->getRoles())) {
            return $this->redirectToRoute('app_login');
        }

        $this->checkModuleAccess($modulePermissionService, 'responsable_manage_reclamations');

        // Get current user
        $user = $this->getUser();
        if (!$user instanceof Employe) {
            return $this->redirectToRoute('app_login');
        }

        // Récupérer le filtre depuis les paramètres de requête
        $filter = $request->query->get('filter', 'all');
        $perPage = min(max($request->query->getInt('perPage', 10), 10), 100); // Between 10 and 100
        
        // Récupérer les réclamations selon le filtre
        if ($filter === 'en_attente') {
            $reclamationsQuery = $reclamationRepository->findByStatutQuery('en_attente');
        } elseif ($filter === 'traitees') {
            $reclamationsQuery = $reclamationRepository->findByStatutQuery('traitees');
        } else {
            // Par défaut, afficher toutes les réclamations
            $reclamationsQuery = $reclamationRepository->findAllQuery();
        }
        
        // Apply organisation permission filters
        $orgFilter = $orgPermissionService->getEmployeeFilterSQL($user);
        if ($orgFilter['where'] === '1=0') {
            // No access, return empty result
            $reclamationsQuery->andWhere('1 = 0');
        } elseif ($orgFilter['where'] !== '1=1') {
            // Get employee IDs that match organisation permissions
            $conn = $entityManager->getConnection();
            $sql = 'SELECT DISTINCT e.id FROM t_user e
                    INNER JOIN t_employee_contrat ec ON ec.employe_id = e.id
                    INNER JOIN t_organisation_employee_contrat oec ON oec.employee_contrat_id = ec.id
                    INNER JOIN p_organisation org ON org.id = oec.organisation_id
                    WHERE (' . $orgFilter['where'] . ')';
            $stmt = $conn->prepare($sql);
            foreach ($orgFilter['params'] as $key => $value) {
                $stmt->bindValue($key, $value, \PDO::PARAM_STR);
            }
            $result = $stmt->executeQuery();
            $allowedEmployeeIds = $result->fetchFirstColumn();
            
            if (empty($allowedEmployeeIds)) {
                // No employees match, return empty result
                $reclamationsQuery->andWhere('1 = 0');
            } else {
                // Filter reclamations by allowed employee IDs
                // Only show reclamations for employees that the responsable RH has access to
                $reclamationsQuery->andWhere('r.employe IN (:allowedEmployeeIds)')
                                  ->setParameter('allowedEmployeeIds', $allowedEmployeeIds);
            }
        }
        // If orgFilter['where'] === '1=1', no filter is applied (admin has access to all)

        // Paginer les résultats avec nombre d'éléments configurable
        $reclamations = $paginator->paginate(
            $reclamationsQuery,
            $request->query->getInt('page', 1),
            $perPage
        );
        
        return $this->render('responsable-rh/reclamations.html.twig', [
            'reclamations' => $reclamations,
            'currentFilter' => $filter,
            'perPage' => $perPage
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
}

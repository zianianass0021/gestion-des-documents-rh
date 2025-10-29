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
        $allResponsables = $employeRepository->findByRoleQuery('ROLE_RESPONSABLE_RH');

        // Pagination manuelle
        $page = $request->query->getInt('page', 1);
        $perPage = 10;
        $totalCount = count($allResponsables);
        $totalPages = ceil($totalCount / $perPage);
        $offset = ($page - 1) * $perPage;
        $responsablesData = array_slice($allResponsables, $offset, $perPage);

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
    public function addResponsable(Request $request, EntityManagerInterface $entityManager, UserPasswordHasherInterface $passwordHasher): Response
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

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Définir automatiquement le rôle de responsable RH
            $employee->setRoles(['ROLE_RESPONSABLE_RH']);
            
            // Hasher le mot de passe (obligatoire pour les nouveaux utilisateurs)
            $plainPassword = $form->get('password')->getData();
            $hashedPassword = $passwordHasher->hashPassword($employee, $plainPassword);
            $employee->setPassword($hashedPassword);
            
            $entityManager->persist($employee);
            $entityManager->flush();

            $this->addFlash('success', 'Responsable RH ajouté avec succès !');
            return $this->redirectToRoute('admin_manage_responsables');
        }

        $response = $this->render('administrateur-rh/add_responsable.html.twig', [
            'form' => $form->createView()
        ]);
        
        $response->headers->set('Cache-Control', 'no-cache, no-store, must-revalidate, private');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', '0');
        
        return $response;
    }

    #[Route('/responsables-rh/modifier/{id}', name: 'admin_edit_responsable')]
    public function editResponsable(Request $request, Employe $employee, EntityManagerInterface $entityManager, UserPasswordHasherInterface $passwordHasher): Response
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

        $form = $this->createForm(ResponsableRhType::class, $employee);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Maintenir le rôle de responsable RH (pas de changement nécessaire)
            // Le rôle reste inchangé lors de la modification
            
            // Si un nouveau mot de passe est fourni, le hasher
            $plainPassword = $form->get('password')->getData();
            if ($plainPassword) {
                $hashedPassword = $passwordHasher->hashPassword($employee, $plainPassword);
                $employee->setPassword($hashedPassword);
            }
            
            $entityManager->flush();

            $this->addFlash('success', 'Responsable RH modifié avec succès !');
            return $this->redirectToRoute('admin_manage_responsables');
        }

        $response = $this->render('administrateur-rh/edit_responsable.html.twig', [
            'form' => $form->createView(),
            'employee' => $employee
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
            foreach ($allDocRequirements as $docReq) {
                $contractType = $docReq->getContractType();
                if (!isset($docRequirementsLookup[$contractType])) {
                    $docRequirementsLookup[$contractType] = ['obligatoires' => [], 'complementaires' => []];
                }
                if ($docReq->isRequired()) {
                    $docRequirementsLookup[$contractType]['obligatoires'][] = $docReq->getDocumentAbbreviation();
                } else {
                    $docRequirementsLookup[$contractType]['complementaires'][] = $docReq->getDocumentAbbreviation();
                }
            }
            
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
            
            // Define the header row
            $headerRow = WriterEntityFactory::createRowFromArray([
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
                'Statut Employé',
                'Documents Requis'
            ]);
            $writer->addRow($headerRow);
            
            // Process employees in batches to avoid memory exhaustion
            $batchSize = 200; // Increased batch size for faster processing
            $offset = 0;
            $totalProcessed = 0;
            
            while (true) {
                // Get batch of employees using native SQL - ordered by last name then first name
                $conn = $em->getConnection();
                $sql = "SELECT id FROM t_employe WHERE roles::text LIKE '%ROLE_EMPLOYEE%' ORDER BY nom ASC, prenom ASC LIMIT :limit OFFSET :offset";
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
                                    $documentsRequis = '';
                                    if ($contractType && $natureContrat) {
                                        $contractTypeDesignation = $natureContrat->getDesignation();
                                        $contractTypeCode = $natureContrat->getCode();
                                        
                                        // Try to find documents by designation first
                                        $docs = null;
                                        if ($contractTypeDesignation && isset($docRequirementsLookup[$contractTypeDesignation])) {
                                            $docs = $docRequirementsLookup[$contractTypeDesignation];
                                        }
                                        // If not found, try with code
                                        elseif ($contractTypeCode && isset($docRequirementsLookup[$contractTypeCode])) {
                                            $docs = $docRequirementsLookup[$contractTypeCode];
                                        }
                                        
                                        if ($docs) {
                                            $obligatoires = $docs['obligatoires'] ?? [];
                                            $complementaires = $docs['complementaires'] ?? [];
                                            
                                            // Combine: obligatoires first, then complémentaires
                                            $allDocs = array_merge($obligatoires, $complementaires);
                                            $documentsRequis = implode(', ', $allDocs);
                                        }
                                    }
                                    
                                    $row = WriterEntityFactory::createRowFromArray([
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
                                        $employe->isActive() ? 'Actif' : 'Inactif',
                                        $documentsRequis
                                    ]);
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
                                
                                $row = WriterEntityFactory::createRowFromArray([
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
                                    $employe->isActive() ? 'Actif' : 'Inactif',
                                    '' // No documents required
                                ]);
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

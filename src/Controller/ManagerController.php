<?php

namespace App\Controller;

use App\Entity\Reclamation;
use App\Entity\Employe;
use App\Form\ReclamationType;
use App\Repository\ReclamationRepository;
use App\Repository\EmployeRepository;
use Doctrine\ORM\EntityManagerInterface;
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
    public function mesReclamations(ReclamationRepository $reclamationRepository): Response
    {
        $manager = $this->getUser();
        $reclamations = $reclamationRepository->findBy(['manager' => $manager], ['dateCreation' => 'DESC']);
        
        return $this->render('manager/reclamations.html.twig', [
            'reclamations' => $reclamations,
        ]);
    }

    #[Route('/reclamations/nouvelle', name: 'manager_add_reclamation')]
    public function addReclamation(Request $request, EntityManagerInterface $entityManager, EmployeRepository $employeRepository): Response
    {
        $reclamation = new Reclamation();
        $reclamation->setManager($this->getUser());
        
        // Récupérer uniquement les employés avec le rôle ROLE_EMPLOYEE
        $employees = $employeRepository->findByRole('ROLE_EMPLOYEE');
        
        $form = $this->createForm(ReclamationType::class, $reclamation, [
            'employees' => $employees
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
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

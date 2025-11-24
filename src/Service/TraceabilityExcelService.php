<?php

namespace App\Service;

use App\Entity\Employe;
use App\Entity\EmployeeContrat;
use App\Entity\Document;
use App\Entity\Dossier;
use App\Entity\Organisation;
use App\Entity\Demande;
use App\Entity\Reclamation;
use App\Entity\Module;
use Doctrine\ORM\EntityManagerInterface;
use Box\Spout\Writer\Common\Creator\WriterEntityFactory;

class TraceabilityExcelService
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {
    }

    public function generateTraceabilityExcel(?\DateTimeInterface $startDate = null, ?\DateTimeInterface $endDate = null): string
    {
        // Create temporary file
        $tempFile = tempnam(sys_get_temp_dir(), 'traceability_export_');
        $tempFile .= '.xlsx';

        // Create writer
        $writer = WriterEntityFactory::createXLSXWriter();
        $writer->openToFile($tempFile);

        // Generate each entity type on a separate sheet
        $this->generateEmployeesSheet($writer, $startDate, $endDate);
        $this->generateContratsSheet($writer, $startDate, $endDate);
        $this->generateDocumentsSheet($writer, $startDate, $endDate);
        $this->generateDossiersSheet($writer, $startDate, $endDate);
        $this->generateOrganisationsSheet($writer, $startDate, $endDate);
        $this->generateDemandesSheet($writer, $startDate, $endDate);
        $this->generateReclamationsSheet($writer, $startDate, $endDate);
        $this->generateModulesSheet($writer, $startDate, $endDate);
        $this->generateSummarySheet($writer, $startDate, $endDate);

        $writer->close();

        return $tempFile;
    }

    private function generateEmployeesSheet($writer, ?\DateTimeInterface $startDate, ?\DateTimeInterface $endDate): void
    {
        // Set sheet name (first sheet is already created)
        if (method_exists($writer, 'getCurrentSheet')) {
            try {
                $sheet = $writer->getCurrentSheet();
                if (method_exists($sheet, 'setName')) {
                    $sheet->setName('Employés');
                }
            } catch (\Exception $e) {
                // Ignore if sheet name cannot be set
            }
        }

        // Header
        $header = WriterEntityFactory::createRowFromArray([
            'ID', 'Nom', 'Prénom', 'Email', 'Téléphone', 'Username', 'Rôles',
            'Créé le', 'Créé par', 'Modifié le', 'Modifié par', 'Désactivé le', 'Désactivé par', 'Statut'
        ]);
        $writer->addRow($header);

        // Query - exclude Responsable RH and Admin RH
        // First, get employee IDs using native SQL to filter roles
        $conn = $this->entityManager->getConnection();
        $sql = "SELECT id FROM t_user WHERE CAST(roles AS TEXT) NOT LIKE '%ROLE_RESPONSABLE_RH%' AND CAST(roles AS TEXT) NOT LIKE '%ROLE_ADMINISTRATEUR_RH%'";
        $params = [];
        
        if ($startDate) {
            $sql .= " AND (created_at >= :startDate OR updated_at >= :startDate)";
            $params['startDate'] = $startDate->format('Y-m-d H:i:s');
        }
        if ($endDate) {
            $sql .= " AND (created_at <= :endDate OR updated_at <= :endDate OR (created_at IS NULL AND updated_at IS NULL))";
            $params['endDate'] = $endDate->format('Y-m-d H:i:s');
        }
        
        $sql .= " ORDER BY created_at DESC";
        
        $stmt = $conn->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $result = $stmt->executeQuery();
        $employeeIds = $result->fetchFirstColumn();
        
        // Now fetch employees by IDs using Doctrine
        if (empty($employeeIds)) {
            $employees = [];
        } else {
            $qb = $this->entityManager->createQueryBuilder();
            $qb->select('e')
                ->from(Employe::class, 'e')
                ->leftJoin('e.createdBy', 'cb')
                ->leftJoin('e.updatedBy', 'ub')
                ->leftJoin('e.disabledBy', 'db')
                ->where('e.id IN (:ids)')
                ->setParameter('ids', $employeeIds)
                ->orderBy('e.createdAt', 'DESC');
            
            $employees = $qb->getQuery()->getResult();
        }

        foreach ($employees as $employee) {
            $row = WriterEntityFactory::createRowFromArray([
                $employee->getId(),
                $employee->getNom(),
                $employee->getPrenom(),
                $employee->getEmail(),
                $employee->getTelephone() ?? '',
                $employee->getUsername() ?? '',
                implode(', ', $employee->getRoles()),
                $employee->getCreatedAt() ? $employee->getCreatedAt()->format('Y-m-d H:i:s') : '',
                $employee->getCreatedBy() ? $employee->getCreatedBy()->getPrenom() . ' ' . $employee->getCreatedBy()->getNom() : '',
                $employee->getUpdatedAt() ? $employee->getUpdatedAt()->format('Y-m-d H:i:s') : '',
                $employee->getUpdatedBy() ? $employee->getUpdatedBy()->getPrenom() . ' ' . $employee->getUpdatedBy()->getNom() : '',
                $employee->getDisabledAt() ? $employee->getDisabledAt()->format('Y-m-d H:i:s') : '',
                $employee->getDisabledBy() ? $employee->getDisabledBy()->getPrenom() . ' ' . $employee->getDisabledBy()->getNom() : '',
                $employee->isActive() ? 'Actif' : 'Inactif'
            ]);
            $writer->addRow($row);
        }
    }

    private function generateContratsSheet($writer, ?\DateTimeInterface $startDate, ?\DateTimeInterface $endDate): void
    {
        // Create new sheet
        if (method_exists($writer, 'addNewSheetAndMakeItCurrent')) {
            $writer->addNewSheetAndMakeItCurrent();
            try {
                $sheet = $writer->getCurrentSheet();
                if (method_exists($sheet, 'setName')) {
                    $sheet->setName('Contrats');
                }
            } catch (\Exception $e) {
                // Ignore if sheet name cannot be set
            }
        }

        // Header
        $header = WriterEntityFactory::createRowFromArray([
            'ID', 'Employé', 'Type Contrat', 'Date Début', 'Date Fin', 'Statut', 'Salaire',
            'Créé le', 'Créé par', 'Modifié le', 'Modifié par', 'Désactivé le', 'Désactivé par'
        ]);
        $writer->addRow($header);

        // Query
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('ec')
            ->from(EmployeeContrat::class, 'ec')
            ->leftJoin('ec.employe', 'e')
            ->leftJoin('ec.natureContrat', 'nc')
            ->leftJoin('ec.createdBy', 'cb')
            ->leftJoin('ec.updatedBy', 'ub')
            ->leftJoin('ec.disabledBy', 'db')
            ->orderBy('ec.createdAt', 'DESC');

        if ($startDate) {
            $qb->andWhere('ec.createdAt >= :startDate OR ec.updatedAt >= :startDate')
                ->setParameter('startDate', $startDate);
        }
        if ($endDate) {
            $qb->andWhere('ec.createdAt <= :endDate OR ec.updatedAt <= :endDate OR (ec.createdAt IS NULL AND ec.updatedAt IS NULL)')
                ->setParameter('endDate', $endDate);
        }

        $contrats = $qb->getQuery()->getResult();

        foreach ($contrats as $contrat) {
            $row = WriterEntityFactory::createRowFromArray([
                $contrat->getId(),
                $contrat->getEmploye() ? $contrat->getEmploye()->getPrenom() . ' ' . $contrat->getEmploye()->getNom() : '',
                $contrat->getNatureContrat() ? $contrat->getNatureContrat()->getDesignation() : '',
                $contrat->getDateDebut() ? $contrat->getDateDebut()->format('Y-m-d') : '',
                $contrat->getDateFin() ? $contrat->getDateFin()->format('Y-m-d') : '',
                $contrat->getStatut() ?? '',
                $contrat->getSalaire() ?? '',
                $contrat->getCreatedAt() ? $contrat->getCreatedAt()->format('Y-m-d H:i:s') : '',
                $contrat->getCreatedBy() ? $contrat->getCreatedBy()->getPrenom() . ' ' . $contrat->getCreatedBy()->getNom() : '',
                $contrat->getUpdatedAt() ? $contrat->getUpdatedAt()->format('Y-m-d H:i:s') : '',
                $contrat->getUpdatedBy() ? $contrat->getUpdatedBy()->getPrenom() . ' ' . $contrat->getUpdatedBy()->getNom() : '',
                $contrat->getDisabledAt() ? $contrat->getDisabledAt()->format('Y-m-d H:i:s') : '',
                $contrat->getDisabledBy() ? $contrat->getDisabledBy()->getPrenom() . ' ' . $contrat->getDisabledBy()->getNom() : ''
            ]);
            $writer->addRow($row);
        }
    }

    private function generateDocumentsSheet($writer, ?\DateTimeInterface $startDate, ?\DateTimeInterface $endDate): void
    {
        // Create new sheet
        if (method_exists($writer, 'addNewSheetAndMakeItCurrent')) {
            $writer->addNewSheetAndMakeItCurrent();
            try {
                $sheet = $writer->getCurrentSheet();
                if (method_exists($sheet, 'setName')) {
                    $sheet->setName('Documents');
                }
            } catch (\Exception $e) {
                // Ignore if sheet name cannot be set
            }
        }

        // Header
        $header = WriterEntityFactory::createRowFromArray([
            'ID', 'Abréviation', 'Libellé Complet', 'Type Document', 'Dossier', 'Employé',
            'Statut Ajout', 'Statut Téléchargement',
            'Créé le', 'Créé par', 'Modifié le', 'Modifié par', 'Désactivé le', 'Désactivé par'
        ]);
        $writer->addRow($header);

        // Query
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('d')
            ->from(Document::class, 'd')
            ->leftJoin('d.dossier', 'dos')
            ->leftJoin('dos.employe', 'e')
            ->leftJoin('d.createdBy', 'cb')
            ->leftJoin('d.updatedBy', 'ub')
            ->leftJoin('d.disabledBy', 'db')
            ->orderBy('d.createdAt', 'DESC');

        if ($startDate) {
            $qb->andWhere('d.createdAt >= :startDate OR d.updatedAt >= :startDate')
                ->setParameter('startDate', $startDate);
        }
        if ($endDate) {
            $qb->andWhere('d.createdAt <= :endDate OR d.updatedAt <= :endDate OR (d.createdAt IS NULL AND d.updatedAt IS NULL)')
                ->setParameter('endDate', $endDate);
        }

        $documents = $qb->getQuery()->getResult();

        foreach ($documents as $document) {
            // Fix typeDocument: replace "à définir" or empty with "Personnel" or "Ayant droit"
            $typeDocument = $document->getTypeDocument() ?? '';
            if (empty($typeDocument) || strtolower($typeDocument) === 'à définir' || strtolower($typeDocument) === 'a definir') {
                // Determine based on usage if available, otherwise default to "Personnel"
                $usage = $document->getUsage() ?? '';
                if (strtolower($usage) === 'ayant droit' || strtolower($usage) === 'ayant_droit') {
                    $typeDocument = 'Ayant droit';
                } else {
                    $typeDocument = 'Personnel';
                }
            }
            
            $row = WriterEntityFactory::createRowFromArray([
                $document->getId(),
                $document->getAbbreviation(),
                $document->getLibelleComplet(),
                $typeDocument,
                $document->getDossier() ? $document->getDossier()->getNom() : '',
                $document->getDossier() && $document->getDossier()->getEmploye() ? $document->getDossier()->getEmploye()->getPrenom() . ' ' . $document->getDossier()->getEmploye()->getNom() : '',
                $document->getStatutAjout() ?? '',
                $document->getStatutTelechargement() ?? '',
                $document->getCreatedAt() ? $document->getCreatedAt()->format('Y-m-d H:i:s') : '',
                $document->getCreatedBy() ? $document->getCreatedBy()->getPrenom() . ' ' . $document->getCreatedBy()->getNom() : '',
                $document->getUpdatedAt() ? $document->getUpdatedAt()->format('Y-m-d H:i:s') : '',
                $document->getUpdatedBy() ? $document->getUpdatedBy()->getPrenom() . ' ' . $document->getUpdatedBy()->getNom() : '',
                $document->getDisabledAt() ? $document->getDisabledAt()->format('Y-m-d H:i:s') : '',
                $document->getDisabledBy() ? $document->getDisabledBy()->getPrenom() . ' ' . $document->getDisabledBy()->getNom() : ''
            ]);
            $writer->addRow($row);
        }
    }

    private function generateDossiersSheet($writer, ?\DateTimeInterface $startDate, ?\DateTimeInterface $endDate): void
    {
        // Create new sheet
        if (method_exists($writer, 'addNewSheetAndMakeItCurrent')) {
            $writer->addNewSheetAndMakeItCurrent();
            try {
                $sheet = $writer->getCurrentSheet();
                if (method_exists($sheet, 'setName')) {
                    $sheet->setName('Dossiers');
                }
            } catch (\Exception $e) {
                // Ignore if sheet name cannot be set
            }
        }

        // Header
        $header = WriterEntityFactory::createRowFromArray([
            'ID', 'Nom', 'Description', 'Employé', 'Placard', 'Emplacement', 'Statut',
            'Créé le', 'Créé par', 'Modifié le', 'Modifié par', 'Désactivé le', 'Désactivé par'
        ]);
        $writer->addRow($header);

        // Query
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('d')
            ->from(Dossier::class, 'd')
            ->leftJoin('d.employe', 'e')
            ->leftJoin('d.placard', 'p')
            ->leftJoin('d.createdBy', 'cb')
            ->leftJoin('d.updatedBy', 'ub')
            ->leftJoin('d.disabledBy', 'db')
            ->orderBy('d.createdAt', 'DESC');

        if ($startDate) {
            $qb->andWhere('d.createdAt >= :startDate OR d.updatedAt >= :startDate')
                ->setParameter('startDate', $startDate);
        }
        if ($endDate) {
            $qb->andWhere('d.createdAt <= :endDate OR d.updatedAt <= :endDate OR (d.createdAt IS NULL AND d.updatedAt IS NULL)')
                ->setParameter('endDate', $endDate);
        }

        $dossiers = $qb->getQuery()->getResult();

        foreach ($dossiers as $dossier) {
            $row = WriterEntityFactory::createRowFromArray([
                $dossier->getId(),
                $dossier->getNom(),
                $dossier->getDescription() ?? '',
                $dossier->getEmploye() ? $dossier->getEmploye()->getPrenom() . ' ' . $dossier->getEmploye()->getNom() : '',
                $dossier->getPlacard() ? $dossier->getPlacard()->getName() : '',
                $dossier->getEmplacement() ?? '',
                $dossier->getStatus() ?? '',
                $dossier->getCreatedAt() ? $dossier->getCreatedAt()->format('Y-m-d H:i:s') : '',
                $dossier->getCreatedBy() ? $dossier->getCreatedBy()->getPrenom() . ' ' . $dossier->getCreatedBy()->getNom() : '',
                $dossier->getUpdatedAt() ? $dossier->getUpdatedAt()->format('Y-m-d H:i:s') : '',
                $dossier->getUpdatedBy() ? $dossier->getUpdatedBy()->getPrenom() . ' ' . $dossier->getUpdatedBy()->getNom() : '',
                $dossier->getDisabledAt() ? $dossier->getDisabledAt()->format('Y-m-d H:i:s') : '',
                $dossier->getDisabledBy() ? $dossier->getDisabledBy()->getPrenom() . ' ' . $dossier->getDisabledBy()->getNom() : ''
            ]);
            $writer->addRow($row);
        }
    }

    private function generateOrganisationsSheet($writer, ?\DateTimeInterface $startDate, ?\DateTimeInterface $endDate): void
    {
        // Create new sheet
        if (method_exists($writer, 'addNewSheetAndMakeItCurrent')) {
            $writer->addNewSheetAndMakeItCurrent();
            try {
                $sheet = $writer->getCurrentSheet();
                if (method_exists($sheet, 'setName')) {
                    $sheet->setName('Organisations');
                }
            } catch (\Exception $e) {
                // Ignore if sheet name cannot be set
            }
        }

        // Header
        $header = WriterEntityFactory::createRowFromArray([
            'ID', 'Code', 'Division Activités Stratégiques', 'DAS', 'Groupement', 'Dossier', 'Dossier Designation',
            'Créé le', 'Créé par', 'Modifié le', 'Modifié par', 'Désactivé le', 'Désactivé par'
        ]);
        $writer->addRow($header);

        // Query
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('o')
            ->from(Organisation::class, 'o')
            ->leftJoin('o.createdBy', 'cb')
            ->leftJoin('o.updatedBy', 'ub')
            ->leftJoin('o.disabledBy', 'db')
            ->orderBy('o.createdAt', 'DESC');

        if ($startDate) {
            $qb->andWhere('o.createdAt >= :startDate OR o.updatedAt >= :startDate')
                ->setParameter('startDate', $startDate);
        }
        if ($endDate) {
            $qb->andWhere('o.createdAt <= :endDate OR o.updatedAt <= :endDate OR (o.createdAt IS NULL AND o.updatedAt IS NULL)')
                ->setParameter('endDate', $endDate);
        }

        $organisations = $qb->getQuery()->getResult();

        foreach ($organisations as $organisation) {
            $row = WriterEntityFactory::createRowFromArray([
                $organisation->getId(),
                $organisation->getCode(),
                $organisation->getDivisionActivitesStrategiques(),
                $organisation->getDas(),
                $organisation->getGroupement(),
                $organisation->getDossier(),
                $organisation->getDossierDesignation(),
                $organisation->getCreatedAt() ? $organisation->getCreatedAt()->format('Y-m-d H:i:s') : '',
                $organisation->getCreatedBy() ? $organisation->getCreatedBy()->getPrenom() . ' ' . $organisation->getCreatedBy()->getNom() : '',
                $organisation->getUpdatedAt() ? $organisation->getUpdatedAt()->format('Y-m-d H:i:s') : '',
                $organisation->getUpdatedBy() ? $organisation->getUpdatedBy()->getPrenom() . ' ' . $organisation->getUpdatedBy()->getNom() : '',
                $organisation->getDisabledAt() ? $organisation->getDisabledAt()->format('Y-m-d H:i:s') : '',
                $organisation->getDisabledBy() ? $organisation->getDisabledBy()->getPrenom() . ' ' . $organisation->getDisabledBy()->getNom() : ''
            ]);
            $writer->addRow($row);
        }
    }

    private function generateDemandesSheet($writer, ?\DateTimeInterface $startDate, ?\DateTimeInterface $endDate): void
    {
        // Create new sheet
        if (method_exists($writer, 'addNewSheetAndMakeItCurrent')) {
            $writer->addNewSheetAndMakeItCurrent();
            try {
                $sheet = $writer->getCurrentSheet();
                if (method_exists($sheet, 'setName')) {
                    $sheet->setName('Demandes');
                }
            } catch (\Exception $e) {
                // Ignore if sheet name cannot be set
            }
        }

        // Header
        $header = WriterEntityFactory::createRowFromArray([
            'ID', 'Titre', 'Contenu', 'Employé', 'Statut', 'Réponse', 'Date Création', 'Date Réponse',
            'Responsable RH', 'Créé par', 'Désactivé par'
        ]);
        $writer->addRow($header);

        // Query
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('d')
            ->from(Demande::class, 'd')
            ->leftJoin('d.employe', 'e')
            ->leftJoin('d.responsableRh', 'rh')
            ->leftJoin('d.createdBy', 'cb')
            ->leftJoin('d.updatedBy', 'ub')
            ->leftJoin('d.disabledBy', 'db')
            ->orderBy('d.dateCreation', 'DESC');

        if ($startDate) {
            $qb->andWhere('d.dateCreation >= :startDate OR d.dateReponse >= :startDate')
                ->setParameter('startDate', $startDate);
        }
        if ($endDate) {
            $qb->andWhere('d.dateCreation <= :endDate OR d.dateReponse <= :endDate OR (d.dateCreation IS NULL AND d.dateReponse IS NULL)')
                ->setParameter('endDate', $endDate);
        }

        $demandes = $qb->getQuery()->getResult();

        foreach ($demandes as $demande) {
            $row = WriterEntityFactory::createRowFromArray([
                $demande->getId(),
                $demande->getTitre(),
                $demande->getContenu(),
                $demande->getEmploye() ? $demande->getEmploye()->getPrenom() . ' ' . $demande->getEmploye()->getNom() : '',
                $demande->getStatut(),
                $demande->getReponse() ?? '',
                $demande->getDateCreation() ? $demande->getDateCreation()->format('Y-m-d H:i:s') : '',
                $demande->getDateReponse() ? $demande->getDateReponse()->format('Y-m-d H:i:s') : '',
                $demande->getResponsableRh() ? $demande->getResponsableRh()->getPrenom() . ' ' . $demande->getResponsableRh()->getNom() : '',
                $demande->getCreatedBy() ? $demande->getCreatedBy()->getPrenom() . ' ' . $demande->getCreatedBy()->getNom() : '',
                $demande->getDisabledBy() ? $demande->getDisabledBy()->getPrenom() . ' ' . $demande->getDisabledBy()->getNom() : ''
            ]);
            $writer->addRow($row);
        }
    }

    private function generateReclamationsSheet($writer, ?\DateTimeInterface $startDate, ?\DateTimeInterface $endDate): void
    {
        // Create new sheet
        if (method_exists($writer, 'addNewSheetAndMakeItCurrent')) {
            $writer->addNewSheetAndMakeItCurrent();
            try {
                $sheet = $writer->getCurrentSheet();
                if (method_exists($sheet, 'setName')) {
                    $sheet->setName('Réclamations');
                }
            } catch (\Exception $e) {
                // Ignore if sheet name cannot be set
            }
        }

        // Header
        $header = WriterEntityFactory::createRowFromArray([
            'ID', 'Type', 'Commentaire', 'Employé', 'Manager', 'Statut', 'Réponse RH',
            'Date Création', 'Date Traitement', 'Traité par', 'Créé par', 'Désactivé par'
        ]);
        $writer->addRow($header);

        // Query
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('r')
            ->from(Reclamation::class, 'r')
            ->leftJoin('r.employe', 'e')
            ->leftJoin('r.manager', 'm')
            ->leftJoin('r.traitePar', 'tp')
            ->leftJoin('r.createdBy', 'cb')
            ->leftJoin('r.updatedBy', 'ub')
            ->leftJoin('r.disabledBy', 'db')
            ->orderBy('r.dateCreation', 'DESC');

        if ($startDate) {
            $qb->andWhere('r.dateCreation >= :startDate OR r.dateTraitement >= :startDate')
                ->setParameter('startDate', $startDate);
        }
        if ($endDate) {
            $qb->andWhere('r.dateCreation <= :endDate OR r.dateTraitement <= :endDate OR (r.dateCreation IS NULL AND r.dateTraitement IS NULL)')
                ->setParameter('endDate', $endDate);
        }

        $reclamations = $qb->getQuery()->getResult();

        foreach ($reclamations as $reclamation) {
            $row = WriterEntityFactory::createRowFromArray([
                $reclamation->getId(),
                $reclamation->getTypeReclamation(),
                $reclamation->getCommentaire(),
                $reclamation->getEmploye() ? $reclamation->getEmploye()->getPrenom() . ' ' . $reclamation->getEmploye()->getNom() : '',
                $reclamation->getManager() ? $reclamation->getManager()->getPrenom() . ' ' . $reclamation->getManager()->getNom() : '',
                $reclamation->getStatut(),
                $reclamation->getReponseRh() ?? '',
                $reclamation->getDateCreation() ? $reclamation->getDateCreation()->format('Y-m-d H:i:s') : '',
                $reclamation->getDateTraitement() ? $reclamation->getDateTraitement()->format('Y-m-d H:i:s') : '',
                $reclamation->getTraitePar() ? $reclamation->getTraitePar()->getPrenom() . ' ' . $reclamation->getTraitePar()->getNom() : '',
                $reclamation->getCreatedBy() ? $reclamation->getCreatedBy()->getPrenom() . ' ' . $reclamation->getCreatedBy()->getNom() : '',
                $reclamation->getDisabledBy() ? $reclamation->getDisabledBy()->getPrenom() . ' ' . $reclamation->getDisabledBy()->getNom() : ''
            ]);
            $writer->addRow($row);
        }
    }

    private function generateModulesSheet($writer, ?\DateTimeInterface $startDate, ?\DateTimeInterface $endDate): void
    {
        // Create new sheet
        if (method_exists($writer, 'addNewSheetAndMakeItCurrent')) {
            $writer->addNewSheetAndMakeItCurrent();
            try {
                $sheet = $writer->getCurrentSheet();
                if (method_exists($sheet, 'setName')) {
                    $sheet->setName('Modules');
                }
            } catch (\Exception $e) {
                // Ignore if sheet name cannot be set
            }
        }

        // Header
        $header = WriterEntityFactory::createRowFromArray([
            'ID', 'Code', 'Label', 'Description', 'Icon', 'Route Prefix', 'Sort Order', 'Actif',
            'Créé le', 'Modifié le'
        ]);
        $writer->addRow($header);

        // Query
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('m')
            ->from(Module::class, 'm')
            ->orderBy('m.createdAt', 'DESC');

        if ($startDate) {
            $qb->andWhere('m.createdAt >= :startDate OR m.updatedAt >= :startDate')
                ->setParameter('startDate', $startDate);
        }
        if ($endDate) {
            $qb->andWhere('m.createdAt <= :endDate OR m.updatedAt <= :endDate OR (m.createdAt IS NULL AND m.updatedAt IS NULL)')
                ->setParameter('endDate', $endDate);
        }

        $modules = $qb->getQuery()->getResult();

        foreach ($modules as $module) {
            $row = WriterEntityFactory::createRowFromArray([
                $module->getId(),
                $module->getCode(),
                $module->getLabel(),
                $module->getDescription() ?? '',
                $module->getIcon() ?? '',
                $module->getRoutePrefix() ?? '',
                $module->getSortOrder(),
                $module->isActive() ? 'Oui' : 'Non',
                $module->getCreatedAt() ? $module->getCreatedAt()->format('Y-m-d H:i:s') : '',
                $module->getUpdatedAt() ? $module->getUpdatedAt()->format('Y-m-d H:i:s') : ''
            ]);
            $writer->addRow($row);
        }
    }

    private function generateSummarySheet($writer, ?\DateTimeInterface $startDate, ?\DateTimeInterface $endDate): void
    {
        // Create new sheet
        if (method_exists($writer, 'addNewSheetAndMakeItCurrent')) {
            $writer->addNewSheetAndMakeItCurrent();
            try {
                $sheet = $writer->getCurrentSheet();
                if (method_exists($sheet, 'setName')) {
                    $sheet->setName('Résumé');
                }
            } catch (\Exception $e) {
                // Ignore if sheet name cannot be set
            }
        }

        // Calculate statistics
        $stats = $this->calculateStatistics($startDate, $endDate);

        // Header
        $header = WriterEntityFactory::createRowFromArray(['Statistique', 'Valeur']);
        $writer->addRow($header);

        // Add statistics
        $writer->addRow(WriterEntityFactory::createRowFromArray(['Période', $startDate && $endDate ? $startDate->format('Y-m-d') . ' au ' . $endDate->format('Y-m-d') : 'Toutes les données']));
        $writer->addRow(WriterEntityFactory::createRowFromArray(['', '']));
        
        $writer->addRow(WriterEntityFactory::createRowFromArray(['EMPLOYÉS', '']));
        $writer->addRow(WriterEntityFactory::createRowFromArray(['Total employés', $stats['employees']['total']]));
        $writer->addRow(WriterEntityFactory::createRowFromArray(['Employés créés', $stats['employees']['created']]));
        $writer->addRow(WriterEntityFactory::createRowFromArray(['Employés modifiés', $stats['employees']['updated']]));
        $writer->addRow(WriterEntityFactory::createRowFromArray(['Employés désactivés', $stats['employees']['disabled']]));
        $writer->addRow(WriterEntityFactory::createRowFromArray(['', '']));

        $writer->addRow(WriterEntityFactory::createRowFromArray(['CONTRATS', '']));
        $writer->addRow(WriterEntityFactory::createRowFromArray(['Total contrats', $stats['contrats']['total']]));
        $writer->addRow(WriterEntityFactory::createRowFromArray(['Contrats créés', $stats['contrats']['created']]));
        $writer->addRow(WriterEntityFactory::createRowFromArray(['Contrats modifiés', $stats['contrats']['updated']]));
        $writer->addRow(WriterEntityFactory::createRowFromArray(['', '']));

        $writer->addRow(WriterEntityFactory::createRowFromArray(['DOCUMENTS', '']));
        $writer->addRow(WriterEntityFactory::createRowFromArray(['Total documents', $stats['documents']['total']]));
        $writer->addRow(WriterEntityFactory::createRowFromArray(['Documents créés', $stats['documents']['created']]));
        $writer->addRow(WriterEntityFactory::createRowFromArray(['Documents modifiés', $stats['documents']['updated']]));
        $writer->addRow(WriterEntityFactory::createRowFromArray(['', '']));

        $writer->addRow(WriterEntityFactory::createRowFromArray(['DOSSIERS', '']));
        $writer->addRow(WriterEntityFactory::createRowFromArray(['Total dossiers', $stats['dossiers']['total']]));
        $writer->addRow(WriterEntityFactory::createRowFromArray(['Dossiers créés', $stats['dossiers']['created']]));
        $writer->addRow(WriterEntityFactory::createRowFromArray(['Dossiers modifiés', $stats['dossiers']['updated']]));
        $writer->addRow(WriterEntityFactory::createRowFromArray(['', '']));

        $writer->addRow(WriterEntityFactory::createRowFromArray(['ORGANISATIONS', '']));
        $writer->addRow(WriterEntityFactory::createRowFromArray(['Total organisations', $stats['organisations']['total']]));
        $writer->addRow(WriterEntityFactory::createRowFromArray(['Organisations créées', $stats['organisations']['created']]));
        $writer->addRow(WriterEntityFactory::createRowFromArray(['Organisations modifiées', $stats['organisations']['updated']]));
        $writer->addRow(WriterEntityFactory::createRowFromArray(['', '']));

        $writer->addRow(WriterEntityFactory::createRowFromArray(['DEMANDES', '']));
        $writer->addRow(WriterEntityFactory::createRowFromArray(['Total demandes', $stats['demandes']['total']]));
        $writer->addRow(WriterEntityFactory::createRowFromArray(['Demandes créées', $stats['demandes']['created']]));
        $writer->addRow(WriterEntityFactory::createRowFromArray(['', '']));

        $writer->addRow(WriterEntityFactory::createRowFromArray(['RÉCLAMATIONS', '']));
        $writer->addRow(WriterEntityFactory::createRowFromArray(['Total réclamations', $stats['reclamations']['total']]));
        $writer->addRow(WriterEntityFactory::createRowFromArray(['Réclamations créées', $stats['reclamations']['created']]));
        $writer->addRow(WriterEntityFactory::createRowFromArray(['', '']));

        // Top users
        $writer->addRow(WriterEntityFactory::createRowFromArray(['TOP 10 UTILISATEURS LES PLUS ACTIFS', '']));
        foreach ($stats['topUsers'] as $userStat) {
            $writer->addRow(WriterEntityFactory::createRowFromArray([
                $userStat['user'],
                $userStat['count'] . ' actions'
            ]));
        }
    }

    private function calculateStatistics(?\DateTimeInterface $startDate, ?\DateTimeInterface $endDate): array
    {
        $stats = [
            'employees' => ['total' => 0, 'created' => 0, 'updated' => 0, 'disabled' => 0],
            'contrats' => ['total' => 0, 'created' => 0, 'updated' => 0],
            'documents' => ['total' => 0, 'created' => 0, 'updated' => 0],
            'dossiers' => ['total' => 0, 'created' => 0, 'updated' => 0],
            'organisations' => ['total' => 0, 'created' => 0, 'updated' => 0],
            'demandes' => ['total' => 0, 'created' => 0],
            'reclamations' => ['total' => 0, 'created' => 0],
            'topUsers' => []
        ];

        // Employees
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('COUNT(e.id)')
            ->from(Employe::class, 'e');
        if ($startDate) {
            $qb->andWhere('e.createdAt >= :startDate OR e.updatedAt >= :startDate')
                ->setParameter('startDate', $startDate);
        }
        if ($endDate) {
            $qb->andWhere('e.createdAt <= :endDate OR e.updatedAt <= :endDate OR (e.createdAt IS NULL AND e.updatedAt IS NULL)')
                ->setParameter('endDate', $endDate);
        }
        $stats['employees']['total'] = (int)$qb->getQuery()->getSingleScalarResult();

        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('COUNT(e.id)')
            ->from(Employe::class, 'e')
            ->where('e.createdAt IS NOT NULL');
        if ($startDate) {
            $qb->andWhere('e.createdAt >= :startDate')
                ->setParameter('startDate', $startDate);
        }
        if ($endDate) {
            $qb->andWhere('e.createdAt <= :endDate')
                ->setParameter('endDate', $endDate);
        }
        $stats['employees']['created'] = (int)$qb->getQuery()->getSingleScalarResult();

        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('COUNT(e.id)')
            ->from(Employe::class, 'e')
            ->where('e.updatedAt IS NOT NULL');
        if ($startDate) {
            $qb->andWhere('e.updatedAt >= :startDate')
                ->setParameter('startDate', $startDate);
        }
        if ($endDate) {
            $qb->andWhere('e.updatedAt <= :endDate')
                ->setParameter('endDate', $endDate);
        }
        $stats['employees']['updated'] = (int)$qb->getQuery()->getSingleScalarResult();

        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('COUNT(e.id)')
            ->from(Employe::class, 'e')
            ->where('e.disabledAt IS NOT NULL');
        if ($startDate) {
            $qb->andWhere('e.disabledAt >= :startDate')
                ->setParameter('startDate', $startDate);
        }
        if ($endDate) {
            $qb->andWhere('e.disabledAt <= :endDate')
                ->setParameter('endDate', $endDate);
        }
        $stats['employees']['disabled'] = (int)$qb->getQuery()->getSingleScalarResult();

        // Similar for other entities...
        $stats['contrats']['total'] = (int)$this->entityManager->getRepository(EmployeeContrat::class)->createQueryBuilder('ec')->select('COUNT(ec.id)')->getQuery()->getSingleScalarResult();
        $stats['documents']['total'] = (int)$this->entityManager->getRepository(Document::class)->createQueryBuilder('d')->select('COUNT(d.id)')->getQuery()->getSingleScalarResult();
        $stats['dossiers']['total'] = (int)$this->entityManager->getRepository(Dossier::class)->createQueryBuilder('d')->select('COUNT(d.id)')->getQuery()->getSingleScalarResult();
        $stats['organisations']['total'] = (int)$this->entityManager->getRepository(Organisation::class)->createQueryBuilder('o')->select('COUNT(o.id)')->getQuery()->getSingleScalarResult();
        $stats['demandes']['total'] = (int)$this->entityManager->getRepository(Demande::class)->createQueryBuilder('d')->select('COUNT(d.id)')->getQuery()->getSingleScalarResult();
        $stats['reclamations']['total'] = (int)$this->entityManager->getRepository(Reclamation::class)->createQueryBuilder('r')->select('COUNT(r.id)')->getQuery()->getSingleScalarResult();

        // Top users
        $conn = $this->entityManager->getConnection();
        $sql = "
            SELECT 
                COALESCE(cb.prenom || ' ' || cb.nom, 'Système') as user_name,
                COUNT(*) as action_count
            FROM (
                SELECT created_by_id FROM t_user WHERE created_by_id IS NOT NULL
                UNION ALL
                SELECT updated_by_id FROM t_user WHERE updated_by_id IS NOT NULL
                UNION ALL
                SELECT created_by_id FROM t_employee_contrat WHERE created_by_id IS NOT NULL
                UNION ALL
                SELECT updated_by_id FROM t_employee_contrat WHERE updated_by_id IS NOT NULL
                UNION ALL
                SELECT created_by_id FROM p_document WHERE created_by_id IS NOT NULL
                UNION ALL
                SELECT updated_by_id FROM p_document WHERE updated_by_id IS NOT NULL
                UNION ALL
                SELECT created_by_id FROM t_dossier WHERE created_by_id IS NOT NULL
                UNION ALL
                SELECT updated_by_id FROM t_dossier WHERE updated_by_id IS NOT NULL
                UNION ALL
                SELECT created_by_id FROM p_organisation WHERE created_by_id IS NOT NULL
                UNION ALL
                SELECT updated_by_id FROM p_organisation WHERE updated_by_id IS NOT NULL
            ) actions
            LEFT JOIN t_user cb ON actions.created_by_id = cb.id OR actions.created_by_id = cb.id
            GROUP BY user_name
            ORDER BY action_count DESC
            LIMIT 10
        ";
        
        try {
            $topUsers = $conn->executeQuery($sql)->fetchAllAssociative();
            foreach ($topUsers as $user) {
                $stats['topUsers'][] = [
                    'user' => $user['user_name'] ?? 'Inconnu',
                    'count' => (int)($user['action_count'] ?? 0)
                ];
            }
        } catch (\Exception $e) {
            // If query fails, leave empty
        }

        return $stats;
    }
}

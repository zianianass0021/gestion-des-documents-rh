<?php

namespace App\Controller;

use App\Service\KpiService;
use App\Service\SimpleReportService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/kpi')]
#[IsGranted('ROLE_RESPONSABLE_RH')]
class KpiController extends AbstractController
{
    private KpiService $kpiService;
    private SimpleReportService $simpleReportService;

    public function __construct(KpiService $kpiService, SimpleReportService $simpleReportService)
    {
        $this->kpiService = $kpiService;
        $this->simpleReportService = $simpleReportService;
    }

    #[Route('/', name: 'kpi_index')]
    public function index(): Response
    {
        return $this->render('kpi/index.html.twig', [
            'page_title' => 'Tableaux de Bord KPI'
        ]);
    }

    #[Route('/fiabilisation-par-contrat', name: 'kpi_fiabilisation_contrat')]
    public function fiabilisationParContrat(): Response
    {
        $data = $this->kpiService->getDocumentReliabilityByContractType();
        
        return $this->render('kpi/fiabilisation_par_contrat.html.twig', [
            'page_title' => 'A. Fiabilisation Dossier RH par Nature de Contrat',
            'data' => $data
        ]);
    }

    #[Route('/fiabilisation-par-das', name: 'kpi_fiabilisation_das')]
    public function fiabilisationParDas(): Response
    {
        $data = $this->kpiService->getDocumentReliabilityByDAS();
        
        return $this->render('kpi/fiabilisation_par_das.html.twig', [
            'page_title' => 'B. Fiabilisation Dossier RH par DAS',
            'data' => $data
        ]);
    }

    #[Route('/details-par-das', name: 'kpi_details_das')]
    public function detailsParDas(): Response
    {
        $data = $this->kpiService->getDetailedDocumentReliabilityByDAS();
        
        return $this->render('kpi/details_par_das.html.twig', [
            'page_title' => 'C. Détails Fiabilisation Dossier RH par DAS',
            'data' => $data
        ]);
    }

    #[Route('/details-par-contrat', name: 'kpi_details_contrat')]
    public function detailsParContrat(): Response
    {
        $data = $this->kpiService->getDetailedDocumentReliabilityByContractType();
        
        return $this->render('kpi/details_par_contrat.html.twig', [
            'page_title' => 'D. Détails Fiabilisation Dossier RH par Nature de Contrat',
            'data' => $data
        ]);
    }

    #[Route('/matrice-personnel', name: 'kpi_matrice_personnel')]
    public function matricePersonnel(): Response
    {
        $data = $this->kpiService->getPersonnelDocumentMatrix();
        
        return $this->render('kpi/matrice_personnel.html.twig', [
            'page_title' => 'E. Matrice Pièces "Personnel"',
            'data' => $data
        ]);
    }

    #[Route('/matrice-ayant-droits', name: 'kpi_matrice_ayant_droits')]
    public function matriceAyantDroits(): Response
    {
        $data = $this->kpiService->getAyantDroitsDocumentMatrix();
        
        return $this->render('kpi/matrice_ayant_droits.html.twig', [
            'page_title' => 'F. Matrice Pièces "Ayant Droits"',
            'data' => $data
        ]);
    }

    #[Route('/download/fiabilisation-contrat', name: 'kpi_download_fiabilisation_contrat')]
    public function downloadFiabilisationContrat(): Response
    {
        return $this->simpleReportService->generateFiabilisationContratReport();
    }

    #[Route('/download/fiabilisation-das', name: 'kpi_download_fiabilisation_das')]
    public function downloadFiabilisationDas(): Response
    {
        return $this->simpleReportService->generateFiabilisationDasReport();
    }

    #[Route('/download/details-das', name: 'kpi_download_details_das')]
    public function downloadDetailsDas(): Response
    {
        return $this->simpleReportService->generateDetailsDasReport();
    }

    #[Route('/download/details-contrat', name: 'kpi_download_details_contrat')]
    public function downloadDetailsContrat(): Response
    {
        return $this->simpleReportService->generateDetailsContratReport();
    }

    #[Route('/download/matrice-personnel', name: 'kpi_download_matrice_personnel')]
    public function downloadMatricePersonnel(): Response
    {
        return $this->simpleReportService->generateMatricePersonnelReport();
    }

    #[Route('/download/matrice-ayant-droits', name: 'kpi_download_matrice_ayant_droits')]
    public function downloadMatriceAyantDroits(): Response
    {
        return $this->simpleReportService->generateMatriceAyantDroitsReport();
    }
}

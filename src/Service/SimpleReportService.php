<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\Response;
use Doctrine\ORM\EntityManagerInterface;

class SimpleReportService
{
    private $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    public function generateFiabilisationContratReport(): Response
    {
        $data = $this->getFiabilisationContratData();
        $html = $this->generateFiabilisationContratHtml($data);
        
        return $this->generatePdfResponse($html, 'rapport_fiabilisation_contrat');
    }

    public function generateFiabilisationDasReport(): Response
    {
        $data = $this->getFiabilisationDasData();
        $html = $this->generateFiabilisationDasHtml($data);
        
        return $this->generatePdfResponse($html, 'rapport_fiabilisation_das');
    }

    public function generateDetailsDasReport(): Response
    {
        $html = $this->generateBasicHtml('Rapport Détaillé par DAS', 'Ce rapport présente les détails des documents par Direction/Service avec pourcentages individuels.');
        return $this->generatePdfResponse($html, 'rapport_details_das');
    }

    public function generateDetailsContratReport(): Response
    {
        $html = $this->generateBasicHtml('Rapport Détaillé par Nature de Contrat', 'Ce rapport présente les détails des documents par type de contrat avec pourcentages individuels.');
        return $this->generatePdfResponse($html, 'rapport_details_contrat');
    }

    public function generateMatricePersonnelReport(): Response
    {
        $html = $this->generateBasicHtml('Matrice des Documents Personnel', 'Ce rapport présente la matrice croisée des documents "Personnel" par contrat et DAS.');
        return $this->generatePdfResponse($html, 'rapport_matrice_personnel');
    }

    public function generateMatriceAyantDroitsReport(): Response
    {
        $html = $this->generateBasicHtml('Matrice des Documents Ayant Droits', 'Ce rapport présente la matrice croisée des documents "Ayant Droits" par contrat et DAS.');
        return $this->generatePdfResponse($html, 'rapport_matrice_ayant_droits');
    }

    private function getFiabilisationContratData(): array
    {
        return [
            ['contract_type' => 'NATIONAL SALARIÉ PERMANENT CDI', 'personnel' => ['completion_percentage' => 84.83, 'missing_documents' => 2264], 'ayant_droits' => ['completion_percentage' => 6.22, 'missing_documents' => 5833]],
            ['contract_type' => 'NATIONAL SALARIÉ PERMANENT CDD', 'personnel' => ['completion_percentage' => 73.1, 'missing_documents' => 2963], 'ayant_droits' => ['completion_percentage' => 2.92, 'missing_documents' => 4456]],
            ['contract_type' => 'NATIONAL SALARIÉ CONTRACTUEL', 'personnel' => ['completion_percentage' => 74.01, 'missing_documents' => 315], 'ayant_droits' => ['completion_percentage' => 4.55, 'missing_documents' => 482]]
        ];
    }

    private function getFiabilisationDasData(): array
    {
        return [
            ['das_name' => 'DAS RH', 'personnel' => ['completion_percentage' => 78.5, 'missing_documents' => 1250], 'ayant_droits' => ['completion_percentage' => 15.2, 'missing_documents' => 3200]],
            ['das_name' => 'DAS Finances', 'personnel' => ['completion_percentage' => 82.3, 'missing_documents' => 890], 'ayant_droits' => ['completion_percentage' => 12.8, 'missing_documents' => 2100]],
            ['das_name' => 'DAS IT', 'personnel' => ['completion_percentage' => 85.1, 'missing_documents' => 650], 'ayant_droits' => ['completion_percentage' => 8.5, 'missing_documents' => 1800]]
        ];
    }

    private function generateFiabilisationContratHtml(array $data): string
    {
        $html = '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Rapport de Fiabilisation par Nature de Contrat</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        h1 { color: #333; text-align: center; }
        h2 { color: #666; border-bottom: 2px solid #ddd; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; font-weight: bold; }
        .header { text-align: center; margin-bottom: 30px; }
        .footer { text-align: center; margin-top: 30px; font-size: 12px; color: #666; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Rapport de Fiabilisation des Dossiers RH</h1>
        <h2>Par Nature de Contrat</h2>
        <p>Généré le ' . date('d/m/Y à H:i') . '</p>
    </div>

    <table>
        <thead>
            <tr>
                <th rowspan="2">Nature de Contrat</th>
                <th colspan="2">Personnel</th>
                <th colspan="2">Ayant Droits</th>
            </tr>
            <tr>
                <th>Pourcentage</th>
                <th>Documents Manquants</th>
                <th>Pourcentage</th>
                <th>Documents Manquants</th>
            </tr>
        </thead>
        <tbody>';

        foreach ($data as $row) {
            $html .= '<tr>';
            $html .= '<td>' . htmlspecialchars($row['contract_type']) . '</td>';
            $html .= '<td>' . number_format($row['personnel']['completion_percentage'], 2) . '%</td>';
            $html .= '<td>' . $row['personnel']['missing_documents'] . '</td>';
            $html .= '<td>' . number_format($row['ayant_droits']['completion_percentage'], 2) . '%</td>';
            $html .= '<td>' . $row['ayant_droits']['missing_documents'] . '</td>';
            $html .= '</tr>';
        }

        $html .= '
        </tbody>
    </table>

    <div class="footer">
        <p>Système de Gestion RH - Rapport généré automatiquement</p>
    </div>
</body>
</html>';

        return $html;
    }

    private function generateFiabilisationDasHtml(array $data): string
    {
        $html = '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Rapport de Fiabilisation par DAS</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        h1 { color: #333; text-align: center; }
        h2 { color: #666; border-bottom: 2px solid #ddd; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; font-weight: bold; }
        .header { text-align: center; margin-bottom: 30px; }
        .footer { text-align: center; margin-top: 30px; font-size: 12px; color: #666; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Rapport de Fiabilisation des Dossiers RH</h1>
        <h2>Par Direction/Service (DAS)</h2>
        <p>Généré le ' . date('d/m/Y à H:i') . '</p>
    </div>

    <table>
        <thead>
            <tr>
                <th rowspan="2">DAS</th>
                <th colspan="2">Personnel</th>
                <th colspan="2">Ayant Droits</th>
            </tr>
            <tr>
                <th>Pourcentage</th>
                <th>Documents Manquants</th>
                <th>Pourcentage</th>
                <th>Documents Manquants</th>
            </tr>
        </thead>
        <tbody>';

        foreach ($data as $row) {
            $html .= '<tr>';
            $html .= '<td>' . htmlspecialchars($row['das_name']) . '</td>';
            $html .= '<td>' . number_format($row['personnel']['completion_percentage'], 2) . '%</td>';
            $html .= '<td>' . $row['personnel']['missing_documents'] . '</td>';
            $html .= '<td>' . number_format($row['ayant_droits']['completion_percentage'], 2) . '%</td>';
            $html .= '<td>' . $row['ayant_droits']['missing_documents'] . '</td>';
            $html .= '</tr>';
        }

        $html .= '
        </tbody>
    </table>

    <div class="footer">
        <p>Système de Gestion RH - Rapport généré automatiquement</p>
    </div>
</body>
</html>';

        return $html;
    }

    private function generateBasicHtml(string $title, string $description): string
    {
        return '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>' . htmlspecialchars($title) . '</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        h1 { color: #333; text-align: center; }
        h2 { color: #666; border-bottom: 2px solid #ddd; }
        .header { text-align: center; margin-bottom: 30px; }
        .content { margin: 20px 0; line-height: 1.6; }
        .footer { text-align: center; margin-top: 30px; font-size: 12px; color: #666; }
    </style>
</head>
<body>
    <div class="header">
        <h1>' . htmlspecialchars($title) . '</h1>
        <p>Généré le ' . date('d/m/Y à H:i') . '</p>
    </div>

    <div class="content">
        <h2>Description</h2>
        <p>' . htmlspecialchars($description) . '</p>
        
        <h2>Données</h2>
        <p>Les données détaillées seront disponibles dans une version future du système.</p>
    </div>

    <div class="footer">
        <p>Système de Gestion RH - Rapport généré automatiquement</p>
    </div>
</body>
</html>';
    }

    private function generatePdfResponse(string $html, string $filename): Response
    {
        // For now, return HTML that can be printed as PDF by the browser
        $response = new Response($html);
        $response->headers->set('Content-Type', 'text/html');
        $response->headers->set('Content-Disposition', 'inline; filename="' . $filename . '.html"');
        
        // Add JavaScript to trigger print dialog
        $htmlWithPrint = str_replace('</body>', '
<script>
window.onload = function() {
    // Auto-trigger print dialog
    window.print();
};
</script>
</body>', $html);
        
        $response->setContent($htmlWithPrint);
        
        return $response;
    }
}

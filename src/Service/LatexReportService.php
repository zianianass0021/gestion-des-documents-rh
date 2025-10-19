<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Process\Process;
use Symfony\Component\Filesystem\Filesystem;
use Doctrine\ORM\EntityManagerInterface;

class LatexReportService
{
    private $entityManager;
    private $filesystem;
    private $projectDir;

    public function __construct(EntityManagerInterface $entityManager, string $projectDir)
    {
        $this->entityManager = $entityManager;
        $this->filesystem = new Filesystem();
        $this->projectDir = $projectDir;
    }

    public function generateFiabilisationContratReport(): Response
    {
        $data = $this->getFiabilisationContratData();
        $latex = $this->generateFiabilisationContratLatex($data);
        
        return $this->compileAndDownload($latex, 'rapport_fiabilisation_contrat');
    }

    public function generateFiabilisationDasReport(): Response
    {
        $data = $this->getFiabilisationDasData();
        $latex = $this->generateFiabilisationDasLatex($data);
        
        return $this->compileAndDownload($latex, 'rapport_fiabilisation_das');
    }

    public function generateDetailsDasReport(): Response
    {
        $data = $this->getDetailsDasData();
        $latex = $this->generateDetailsDasLatex($data);
        
        return $this->compileAndDownload($latex, 'rapport_details_das');
    }

    public function generateDetailsContratReport(): Response
    {
        $data = $this->getDetailsContratData();
        $latex = $this->generateDetailsContratLatex($data);
        
        return $this->compileAndDownload($latex, 'rapport_details_contrat');
    }

    public function generateMatricePersonnelReport(): Response
    {
        $data = $this->getMatricePersonnelData();
        $latex = $this->generateMatricePersonnelLatex($data);
        
        return $this->compileAndDownload($latex, 'rapport_matrice_personnel');
    }

    public function generateMatriceAyantDroitsReport(): Response
    {
        $data = $this->getMatriceAyantDroitsData();
        $latex = $this->generateMatriceAyantDroitsLatex($data);
        
        return $this->compileAndDownload($latex, 'rapport_matrice_ayant_droits');
    }

    private function getFiabilisationContratData(): array
    {
        // Sample data - replace with actual database queries
        return [
            ['contract_type' => 'NATIONAL SALARIÉ PERMANENT CDI', 'personnel' => ['completion_percentage' => 84.83, 'missing_documents' => 2264], 'ayant_droits' => ['completion_percentage' => 6.22, 'missing_documents' => 5833]],
            ['contract_type' => 'NATIONAL SALARIÉ PERMANENT CDD', 'personnel' => ['completion_percentage' => 73.1, 'missing_documents' => 2963], 'ayant_droits' => ['completion_percentage' => 2.92, 'missing_documents' => 4456]],
            ['contract_type' => 'NATIONAL SALARIÉ CONTRACTUEL', 'personnel' => ['completion_percentage' => 74.01, 'missing_documents' => 315], 'ayant_droits' => ['completion_percentage' => 4.55, 'missing_documents' => 482]]
        ];
    }

    private function getFiabilisationDasData(): array
    {
        // Sample data - replace with actual database queries
        return [
            ['das_name' => 'DAS RH', 'personnel' => ['completion_percentage' => 78.5, 'missing_documents' => 1250], 'ayant_droits' => ['completion_percentage' => 15.2, 'missing_documents' => 3200]],
            ['das_name' => 'DAS Finances', 'personnel' => ['completion_percentage' => 82.3, 'missing_documents' => 890], 'ayant_droits' => ['completion_percentage' => 12.8, 'missing_documents' => 2100]],
            ['das_name' => 'DAS IT', 'personnel' => ['completion_percentage' => 85.1, 'missing_documents' => 650], 'ayant_droits' => ['completion_percentage' => 8.5, 'missing_documents' => 1800]]
        ];
    }

    private function getDetailsDasData(): array
    {
        // Sample data structure for detailed DAS report
        return [];
    }

    private function getDetailsContratData(): array
    {
        // Sample data structure for detailed contract report
        return [];
    }

    private function getMatricePersonnelData(): array
    {
        // Sample data structure for personnel matrix
        return [];
    }

    private function getMatriceAyantDroitsData(): array
    {
        // Sample data structure for dependents matrix
        return [];
    }

    private function generateFiabilisationContratLatex(array $data): string
    {
        $latex = '
\\documentclass[12pt,a4paper]{article}
\\usepackage[utf8]{inputenc}
\\usepackage[french]{babel}
\\usepackage{geometry}
\\usepackage{booktabs}
\\usepackage{array}
\\usepackage{xcolor}
\\usepackage{graphicx}
\\usepackage{fancyhdr}

\\geometry{margin=2cm}

\\pagestyle{fancy}
\\fancyhf{}
\\fancyhead[L]{Rapport de Fiabilisation par Nature de Contrat}
\\fancyhead[R]{\\today}
\\fancyfoot[C]{\\thepage}

\\title{\\textbf{Rapport de Fiabilisation des Dossiers RH}\\\\
\\large{Par Nature de Contrat}}
\\author{Système de Gestion RH}
\\date{\\today}

\\begin{document}

\\maketitle

\\section{Fiabilisation par Nature de Contrat}

\\begin{table}[h!]
\\centering
\\caption{Tableau de fiabilisation des dossiers RH par nature de contrat}
\\begin{tabular}{|p{5cm}|c|c|c|c|}
\\hline
\\textbf{Nature de Contrat} & \\textbf{Personnel}\\\\
& \\textbf{Pourcentage} & \\textbf{Manquants} & \\textbf{Pourcentage} & \\textbf{Manquants} \\\\
\\hline
';

        foreach ($data as $row) {
            $contractType = $this->escapeLatex($row['contract_type']);
            $personnelPercent = number_format($row['personnel']['completion_percentage'], 2);
            $personnelMissing = $row['personnel']['missing_documents'];
            $ayantDroitsPercent = number_format($row['ayant_droits']['completion_percentage'], 2);
            $ayantDroitsMissing = $row['ayant_droits']['missing_documents'];
            
            $latex .= $contractType . ' & ' . $personnelPercent . '\\% & ' . $personnelMissing . ' & ' . $ayantDroitsPercent . '\\% & ' . $ayantDroitsMissing . ' \\\\' . "\n";
        }

        $latex .= '\\hline
\\end{tabular}
\\end{table}

\\section{Analyse}

Ce rapport présente le niveau de fiabilisation des dossiers RH organisés par nature de contrat. Les pourcentages indiquent le taux de complétude des documents requis.

\\end{document}';

        return $latex;
    }

    private function generateFiabilisationDasLatex(array $data): string
    {
        $latex = '
\\documentclass[12pt,a4paper]{article}
\\usepackage[utf8]{inputenc}
\\usepackage[french]{babel}
\\usepackage{geometry}
\\usepackage{booktabs}
\\usepackage{array}
\\usepackage{xcolor}

\\geometry{margin=2cm}

\\title{\\textbf{Rapport de Fiabilisation des Dossiers RH}\\\\
\\large{Par Direction/Service (DAS)}}
\\author{Système de Gestion RH}
\\date{\\today}

\\begin{document}

\\maketitle

\\section{Fiabilisation par DAS}

\\begin{table}[h!]
\\centering
\\caption{Tableau de fiabilisation des dossiers RH par DAS}
\\begin{tabular}{|p{4cm}|c|c|c|c|}
\\hline
\\textbf{DAS} & \\textbf{Personnel}\\\\
& \\textbf{Pourcentage} & \\textbf{Manquants} & \\textbf{Pourcentage} & \\textbf{Manquants} \\\\
\\hline
';

        foreach ($data as $row) {
            $dasName = $this->escapeLatex($row['das_name']);
            $personnelPercent = number_format($row['personnel']['completion_percentage'], 2);
            $personnelMissing = $row['personnel']['missing_documents'];
            $ayantDroitsPercent = number_format($row['ayant_droits']['completion_percentage'], 2);
            $ayantDroitsMissing = $row['ayant_droits']['missing_documents'];
            
            $latex .= $dasName . ' & ' . $personnelPercent . '\\% & ' . $personnelMissing . ' & ' . $ayantDroitsPercent . '\\% & ' . $ayantDroitsMissing . ' \\\\' . "\n";
        }

        $latex .= '\\hline
\\end{tabular}
\\end{table}

\\section{Analyse}

Ce rapport présente le niveau de fiabilisation des dossiers RH organisés par Direction/Service.

\\end{document}';

        return $latex;
    }

    private function generateDetailsDasLatex(array $data): string
    {
        // Implementation for detailed DAS report
        return $this->generateBasicLatex('Rapport Détaillé par DAS', 'Ce rapport présente les détails des documents par Direction/Service avec pourcentages individuels.');
    }

    private function generateDetailsContratLatex(array $data): string
    {
        // Implementation for detailed contract report
        return $this->generateBasicLatex('Rapport Détaillé par Nature de Contrat', 'Ce rapport présente les détails des documents par type de contrat avec pourcentages individuels.');
    }

    private function generateMatricePersonnelLatex(array $data): string
    {
        // Implementation for personnel matrix
        return $this->generateBasicLatex('Matrice des Documents Personnel', 'Ce rapport présente la matrice croisée des documents "Personnel" par contrat et DAS.');
    }

    private function generateMatriceAyantDroitsLatex(array $data): string
    {
        // Implementation for dependents matrix
        return $this->generateBasicLatex('Matrice des Documents Ayant Droits', 'Ce rapport présente la matrice croisée des documents "Ayant Droits" par contrat et DAS.');
    }

    private function generateBasicLatex(string $title, string $description): string
    {
        return '
\\documentclass[12pt,a4paper]{article}
\\usepackage[utf8]{inputenc}
\\usepackage[french]{babel}
\\usepackage{geometry}

\\geometry{margin=2cm}

\\title{\\textbf{' . $this->escapeLatex($title) . '}}
\\author{Système de Gestion RH}
\\date{\\today}

\\begin{document}

\\maketitle

\\section{Description}

' . $this->escapeLatex($description) . '

\\section{Données}

Les données détaillées seront disponibles dans une version future du système.

\\end{document}';
    }

    private function compileAndDownload(string $latex, string $filename): Response
    {
        $tempDir = $this->projectDir . '/var/temp';
        $this->filesystem->mkdir($tempDir);

        $texFile = $tempDir . '/' . $filename . '.tex';
        $pdfFile = $tempDir . '/' . $filename . '.pdf';

        try {
            // Write LaTeX file
            file_put_contents($texFile, $latex);

            // Compile LaTeX to PDF
            $process = new Process(['pdflatex', '-interaction=nonstopmode', '-output-directory', $tempDir, $texFile]);
            $process->setTimeout(30);
            $process->run();

            if (!$process->isSuccessful()) {
                // Fallback: return LaTeX file instead of PDF
                $response = new Response($latex);
                $response->headers->set('Content-Type', 'text/plain');
                $response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '.tex"');
                
                // Clean up
                $this->filesystem->remove($texFile);
                
                return $response;
            }

            // Read PDF content
            if (file_exists($pdfFile)) {
                $pdfContent = file_get_contents($pdfFile);

                // Create response
                $response = new Response($pdfContent);
                $response->headers->set('Content-Type', 'application/pdf');
                $response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '.pdf"');

                // Clean up temporary files
                $this->filesystem->remove([
                    $texFile, 
                    $pdfFile, 
                    $tempDir . '/' . $filename . '.aux', 
                    $tempDir . '/' . $filename . '.log'
                ]);

                return $response;
            } else {
                throw new \Exception('PDF file not generated');
            }
        } catch (\Exception $e) {
            // Fallback: return LaTeX file
            $response = new Response($latex);
            $response->headers->set('Content-Type', 'text/plain');
            $response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '.tex"');
            
            // Clean up
            if (file_exists($texFile)) {
                $this->filesystem->remove($texFile);
            }
            
            return $response;
        }
    }

    private function escapeLatex(string $text): string
    {
        $text = str_replace(['\\', '{', '}', '$', '&', '%', '#', '^', '_'], ['\\textbackslash{}', '\\{', '\\}', '\\$', '\\&', '\\%', '\\#', '\\textasciicircum{}', '\\_'], $text);
        return $text;
    }
}

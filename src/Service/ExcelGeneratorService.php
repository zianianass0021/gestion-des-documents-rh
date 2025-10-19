<?php

namespace App\Service;

use Doctrine\DBAL\Connection;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class ExcelGeneratorService
{
    private Connection $connection;
    private string $projectDir;

    public function __construct(Connection $connection, ParameterBagInterface $params)
    {
        $this->connection = $connection;
        $this->projectDir = $params->get('kernel.project_dir');
    }

    public function generateModuleExcel(): bool
    {
        try {
            // Récupérer tous les employés
            $baseEmployees = $this->connection->executeQuery("
                SELECT id, nom, prenom, email, telephone, is_active as statut
                FROM t_employe
                WHERE roles::text LIKE '%ROLE_EMPLOYEE%'
                ORDER BY id
            ")->fetchAllAssociative();

            $employees = [];

            // Pour chaque employé, récupérer toutes ses données
            foreach ($baseEmployees as $emp) {
                $empId = $emp['id'];
                
                // Récupérer les contrats
                $contrats = $this->connection->executeQuery("
                    SELECT nc.designation, ec.date_debut, ec.date_fin
                    FROM t_employee_contrat ec
                    JOIN p_nature_contrat nc ON ec.nature_contrat_id = nc.id
                    WHERE ec.employe_id = ?
                    ORDER BY ec.date_debut
                ", [$empId])->fetchAllAssociative();
                
                // Récupérer les organisations
                $organisations = $this->connection->executeQuery("
                    SELECT DISTINCT o.division_activites_strategiques, o.das
                    FROM t_organisation_employee_contrat oec
                    JOIN p_organisation o ON oec.organisation_id = o.id
                    JOIN t_employee_contrat ec ON oec.employee_contrat_id = ec.id
                    WHERE ec.employe_id = ?
                    ORDER BY o.division_activites_strategiques
                ", [$empId])->fetchAllAssociative();
                
                // Récupérer le dossier
                $dossier = $this->connection->executeQuery("
                    SELECT d.nom, p.name as placard, d.emplacement
                    FROM t_dossier d
                    LEFT JOIN p_placards p ON d.placard_id = p.id
                    WHERE d.employe_id = ?
                    LIMIT 1
                ", [$empId])->fetchAssociative();
                
                // Compter les documents
                $nbDocs = $this->connection->executeQuery("
                    SELECT COUNT(*)
                    FROM p_document doc
                    WHERE doc.dossier_id = (SELECT id FROM t_dossier WHERE employe_id = ? LIMIT 1)
                ", [$empId])->fetchOne();
                
                // Récupérer quelques documents pour l'aperçu
                $documents = $this->connection->executeQuery("
                    SELECT doc.abbreviation
                    FROM p_document doc
                    WHERE doc.dossier_id = (SELECT id FROM t_dossier WHERE employe_id = ? LIMIT 1)
                    ORDER BY doc.abbreviation
                    LIMIT 10
                ", [$empId])->fetchAllAssociative();
                
                $employees[] = [
                    'id' => $empId,
                    'nom' => $emp['nom'],
                    'prenom' => $emp['prenom'],
                    'email' => $emp['email'],
                    'telephone' => $emp['telephone'],
                    'statut' => $emp['statut'] ? 'Actif' : 'Inactif',
                    'contrats' => $contrats,
                    'organisations' => $organisations,
                    'dossier' => $dossier,
                    'nb_documents' => $nbDocs,
                    'documents_sample' => array_column($documents, 'abbreviation')
                ];
            }

            // Créer le script Python pour générer l'Excel
            $pythonScript = $this->generatePythonScript($employees);
            
            // Écrire le script Python temporaire
            $pythonFile = $this->projectDir . '/temp_generate_excel.py';
            file_put_contents($pythonFile, $pythonScript);
            
            // Exécuter le script Python
            $output = [];
            $returnVar = 0;
            exec("python \"$pythonFile\" 2>&1", $output, $returnVar);
            
            // Supprimer le fichier temporaire
            @unlink($pythonFile);
            
            return $returnVar === 0;
            
        } catch (\Exception $e) {
            error_log("Erreur lors de la génération Excel: " . $e->getMessage());
            return false;
        }
    }

    private function generatePythonScript(array $employees): string
    {
        $script = "import openpyxl\n";
        $script .= "from openpyxl.styles import Font, PatternFill, Alignment, Border, Side\n\n";
        $script .= "wb = openpyxl.Workbook()\n";
        $script .= "ws = wb.active\n";
        $script .= "ws.title = 'Donnees Employes RH'\n\n";
        
        $script .= "# Styles\n";
        $script .= "header_fill = PatternFill(start_color='0284C7', end_color='0284C7', fill_type='solid')\n";
        $script .= "header_font = Font(bold=True, color='FFFFFF', size=11)\n";
        $script .= "cell_font = Font(size=10)\n";
        $script .= "border = Border(left=Side(style='thin', color='CCCCCC'), right=Side(style='thin', color='CCCCCC'), top=Side(style='thin', color='CCCCCC'), bottom=Side(style='thin', color='CCCCCC'))\n\n";
        
        $script .= "# En-tetes\n";
        $script .= "headers = ['ID Employe', 'Nom', 'Prenom', 'Email', 'Telephone', 'Statut', 'Type de Contrat(s)', 'Date(s) Debut', 'Date(s) Fin', 'Organisation(s)', 'DAS', 'Groupement', 'Nom du Dossier', 'Documents (Nombre)', 'Placard', 'Emplacement']\n";
        $script .= "for col, header in enumerate(headers, start=1):\n";
        $script .= "    cell = ws.cell(row=1, column=col, value=header)\n";
        $script .= "    cell.fill = header_fill\n";
        $script .= "    cell.font = header_font\n";
        $script .= "    cell.alignment = Alignment(horizontal='center', vertical='center', wrap_text=True)\n";
        $script .= "    cell.border = border\n\n";
        
        $script .= "# Donnees\n";
        $script .= "employees_data = [\n";
        
        foreach ($employees as $emp) {
            // Formatter les contrats
            $contratsStr = $this->formatContrats($emp['contrats']);
            $datesDebutStr = $this->formatDatesDebut($emp['contrats']);
            $datesFinStr = $this->formatDatesFin($emp['contrats']);
            
            // Formatter les organisations
            $orgsStr = $this->formatOrganisations($emp['organisations']);
            $dasStr = $this->formatDas($emp['organisations']);
            
            // Formatter les documents
            $docsStr = $this->formatDocuments($emp['documents_sample'], $emp['nb_documents']);
            
            // Informations du dossier
            $dossierNom = $emp['dossier'] ? $emp['dossier']['nom'] : "Dossier Personnel - {$emp['nom']} {$emp['prenom']}";
            $placard = $emp['dossier'] && $emp['dossier']['placard'] ? $emp['dossier']['placard'] : 'Non assigné';
            $emplacement = $emp['dossier'] && $emp['dossier']['emplacement'] ? $emp['dossier']['emplacement'] : 'N/A';
            
            // Échapper pour Python
            $data = [
                $emp['id'],
                $this->escapePython($emp['nom']),
                $this->escapePython($emp['prenom']),
                $this->escapePython($emp['email']),
                $this->escapePython($emp['telephone']),
                $emp['statut'],
                $this->escapePython($contratsStr),
                $this->escapePython($datesDebutStr),
                $this->escapePython($datesFinStr),
                $this->escapePython($orgsStr),
                $this->escapePython($dasStr),
                'FCZ',
                $this->escapePython($dossierNom),
                $this->escapePython($docsStr),
                $this->escapePython($placard),
                $this->escapePython($emplacement)
            ];
            
            $script .= "    [" . $emp['id'] . ", ";
            for ($i = 1; $i < count($data); $i++) {
                $script .= "'" . $data[$i] . "'";
                if ($i < count($data) - 1) {
                    $script .= ", ";
                }
            }
            $script .= "],\n";
        }
        
        $script .= "]\n\n";
        $script .= "for row_idx, emp in enumerate(employees_data, start=2):\n";
        $script .= "    for col_idx, value in enumerate(emp, start=1):\n";
        $script .= "        cell = ws.cell(row=row_idx, column=col_idx, value=value)\n";
        $script .= "        cell.font = cell_font\n";
        $script .= "        cell.alignment = Alignment(vertical='center', wrap_text=True)\n";
        $script .= "        cell.border = border\n\n";
        
        $script .= "column_widths = {'A': 12, 'B': 15, 'C': 15, 'D': 28, 'E': 20, 'F': 10, 'G': 55, 'H': 20, 'I': 20, 'J': 50, 'K': 18, 'L': 13, 'M': 38, 'N': 60, 'O': 35, 'P': 15}\n";
        $script .= "for col, width in column_widths.items():\n";
        $script .= "    ws.column_dimensions[col].width = width\n\n";
        $script .= "ws.row_dimensions[1].height = 30\n";
        $script .= "for row in range(2, len(employees_data) + 2):\n";
        $script .= "    ws.row_dimensions[row].height = 50\n\n";
        $script .= "wb.save('" . str_replace('\\', '/', $this->projectDir) . "/module.xlsx')\n";
        
        return $script;
    }

    private function formatContrats(array $contrats): string
    {
        if (count($contrats) > 1) {
            $result = [];
            foreach ($contrats as $i => $c) {
                $result[] = ($i + 1) . ") " . $c['designation'];
            }
            return implode("\\n", $result);
        } else if (count($contrats) == 1) {
            return $contrats[0]['designation'];
        }
        return 'N/A';
    }

    private function formatDatesDebut(array $contrats): string
    {
        if (count($contrats) > 1) {
            $result = [];
            foreach ($contrats as $i => $c) {
                $result[] = ($i + 1) . ") " . $c['date_debut'];
            }
            return implode("\\n", $result);
        } else if (count($contrats) == 1) {
            return $contrats[0]['date_debut'];
        }
        return 'N/A';
    }

    private function formatDatesFin(array $contrats): string
    {
        if (count($contrats) > 1) {
            $result = [];
            foreach ($contrats as $i => $c) {
                $result[] = ($i + 1) . ") " . ($c['date_fin'] ?? 'Indéterminée');
            }
            return implode("\\n", $result);
        } else if (count($contrats) == 1) {
            return $contrats[0]['date_fin'] ?? 'Indéterminée';
        }
        return 'N/A';
    }

    private function formatOrganisations(array $organisations): string
    {
        if (count($organisations) > 1) {
            $result = [];
            foreach ($organisations as $i => $org) {
                $result[] = ($i + 1) . ") " . $org['division_activites_strategiques'];
            }
            return implode("\\n", $result);
        } else if (count($organisations) == 1) {
            return $organisations[0]['division_activites_strategiques'];
        }
        return 'N/A';
    }

    private function formatDas(array $organisations): string
    {
        if (count($organisations) > 0) {
            $das = array_column($organisations, 'das');
            return implode(', ', $das);
        }
        return 'N/A';
    }

    private function formatDocuments(array $documentsSample, int $nbDocuments): string
    {
        if ($nbDocuments == 0) {
            return 'Aucun document';
        }
        
        $sample = implode(', ', array_slice($documentsSample, 0, 10));
        if (count($documentsSample) > 10) {
            $sample .= '...';
        }
        
        return $sample . ' (' . $nbDocuments . ' docs)';
    }

    private function escapePython(string $value): string
    {
        // Échapper les caractères spéciaux pour Python
        $value = str_replace('\\', '\\\\', $value);
        $value = str_replace("'", "\\'", $value);
        // Garder les \n pour les vrais retours à la ligne dans Excel
        return $value;
    }
}


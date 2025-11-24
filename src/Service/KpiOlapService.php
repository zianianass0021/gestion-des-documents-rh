<?php

namespace App\Service;

use App\Service\ClickHouseClient;
use Psr\Log\LoggerInterface;

/**
 * OLAP Service for KPI Reports
 * 
 * Provides methods to query ClickHouse OLAP cube for fast KPI reporting.
 * Each method returns data in the format expected by DashboardController formatting functions.
 */
class KpiOlapService
{
    private ClickHouseClient $clickHouseClient;
    private ?LoggerInterface $logger;

    public function __construct(ClickHouseClient $clickHouseClient, ?LoggerInterface $logger = null)
    {
        $this->clickHouseClient = $clickHouseClient;
        $this->logger = $logger;
    }

    /**
     * KPI A: Performance par Nature de Contrat
     * Returns: Array of contract types with personnel and ayant_droits completion data
     */
    public function getKpiA(): array
    {
        try {
            // Query with max date, using column position as fallback
            $query = "
                SELECT *
                FROM rh_olap.kpi_a_contract_type_performance
                WHERE event_date = (SELECT max(event_date) FROM rh_olap.kpi_a_contract_type_performance)
                ORDER BY contract_type
            ";

            $results = $this->clickHouseClient->select($query);

            // Transform to format expected by formatReportAData
            // JSON format returns associative arrays with column names
            $formatted = [];
            foreach ($results as $row) {
                if (!is_array($row)) {
                    continue;
                }
                
                // Try associative array first (from JSON format)
                if (isset($row['contract_type'])) {
                    $contractType = trim($row['contract_type'] ?? '');
                    if (empty($contractType)) {
                        continue;
                    }
                    
                    $formatted[] = [
                        'contract_type' => $contractType,
                        'personnel' => [
                            'completion_percentage' => (float)($row['personnel_completion'] ?? 0),
                            'missing_documents' => (int)($row['missing_docs_personnel'] ?? 0)
                        ],
                        'ayant_droits' => [
                            'completion_percentage' => (float)($row['ayant_droits_completion'] ?? 0),
                            'missing_documents' => (int)($row['missing_docs_ayantdroits'] ?? 0)
                        ]
                    ];
                } elseif (isset($row[0])) {
                    // Fallback to positional array (TSV format)
                    // Positional order: event_date[0], contract_type[1], personnel_completion[2], ayant_droits_completion[3], missing_docs_personnel[4], missing_docs_ayantdroits[5]
                    $contractType = trim($row[1] ?? '');
                    if (empty($contractType)) {
                        continue;
                    }
                    
                    $formatted[] = [
                        'contract_type' => $contractType,
                        'personnel' => [
                            'completion_percentage' => (float)($row[2] ?? 0),
                            'missing_documents' => (int)($row[4] ?? 0)
                        ],
                        'ayant_droits' => [
                            'completion_percentage' => (float)($row[3] ?? 0),
                            'missing_documents' => (int)($row[5] ?? 0)
                        ]
                    ];
                }
            }

            $this->logger?->info('KPI A fetched', [
                'rows' => count($formatted),
                'sample' => !empty($formatted) ? $formatted[0] : null
            ]);
            
            // Debug: log if empty
            if (empty($formatted)) {
                $this->logger?->warning('KPI A returned empty - check if ETL has synced data', [
                    'raw_results_count' => count($results),
                    'sample_raw' => !empty($results) ? $results[0] : null
                ]);
            }
            
            return $formatted;
        } catch (\Exception $e) {
            $this->logger?->error('KPI A fetch failed', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * KPI B: Performance par Organisation (DAS)
     * Returns: Array of organizations with personnel and ayant_droits completion data
     */
    public function getKpiB(): array
    {
        try {
            $query = "
                SELECT *
                FROM rh_olap.kpi_b_doc_reliability_by_org
                WHERE event_date = (SELECT max(event_date) FROM rh_olap.kpi_b_doc_reliability_by_org)
                ORDER BY organization
            ";

            $results = $this->clickHouseClient->select($query);

            // Transform to format expected by formatReportBData
            $formatted = [];
            foreach ($results as $row) {
                if (!is_array($row)) {
                    continue;
                }
                
                // Try associative array first (from JSON format)
                if (isset($row['organization'])) {
                    $orgName = trim($row['organization'] ?? '');
                    if (empty($orgName)) {
                        continue;
                    }
                    
                    $formatted[] = [
                        'organization' => $orgName,
                        'personnel_completion' => (float)($row['personnel_completion'] ?? 0),
                        'ayant_droits_completion' => (float)($row['ayant_droits_completion'] ?? 0),
                    ];
                } elseif (isset($row[0])) {
                    // Fallback to positional array (TSV format)
                    // Positional order: event_date[0], organization[1], personnel_completion[2], ayant_droits_completion[3]
                    $orgName = trim($row[1] ?? '');
                    if (empty($orgName)) {
                        continue;
                    }
                    
                    $formatted[] = [
                        'organization' => $orgName,
                        'personnel_completion' => (float)($row[2] ?? 0),
                        'ayant_droits_completion' => (float)($row[3] ?? 0),
                    ];
                }
            }

            $this->logger?->info('KPI B fetched', ['rows' => count($formatted)]);
            return $formatted;
        } catch (\Exception $e) {
            $this->logger?->error('KPI B fetch failed', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * KPI C: Performance Détaillée par Organisation (DAS)
     * Returns: Array of organizations with document-level completion percentages
     */
    public function getKpiC(): array
    {
        try {
            $query = "
                SELECT 
                    das_code,
                    document_abbreviation,
                    document_type,
                    completion_percentage
                FROM rh_olap.kpi_c_document_matrix
                WHERE event_date = (SELECT max(event_date) FROM rh_olap.kpi_c_document_matrix)
                ORDER BY das_code, document_abbreviation, document_type
            ";

            $results = $this->clickHouseClient->select($query);

            // Group by DAS and document
            $formatted = [];
            foreach ($results as $row) {
                // Handle both associative and positional arrays
                if (isset($row['das_code'])) {
                    $dasCode = trim($row['das_code'] ?? '');
                    $docAbbr = trim($row['document_abbreviation'] ?? '');
                    $docType = trim($row['document_type'] ?? '');
                    $completion = (float)($row['completion_percentage'] ?? 0);
                } elseif (isset($row[0])) {
                    // Positional: event_date[0], das_code[1], document_abbreviation[2], document_type[3], completion_percentage[4]
                    $dasCode = trim($row[1] ?? '');
                    $docAbbr = trim($row[2] ?? '');
                    $docType = trim($row[3] ?? '');
                    $completion = (float)($row[4] ?? 0);
                } else {
                    continue;
                }
                
                if (empty($dasCode) || empty($docAbbr) || empty($docType)) {
                    continue;
                }
                
                if (!isset($formatted[$dasCode])) {
                    $formatted[$dasCode] = [];
                }
                
                $formatted[$dasCode][$docAbbr . '_' . $docType] = [
                    'document_abbreviation' => $docAbbr,
                    'document_type' => $docType,
                    'completion_percentage' => $completion,
                ];
            }

            // Convert to array format expected by formatReportCData
            $result = [];
            foreach ($formatted as $dasCode => $documents) {
                $result[] = [
                    'das_code' => $dasCode,
                    'documents' => $documents,
                ];
            }

            $this->logger?->info('KPI C fetched', ['rows' => count($result), 'das_count' => count($formatted)]);
            return $result;
        } catch (\Exception $e) {
            $this->logger?->error('KPI C fetch failed', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * KPI D: Performance Détaillée par Nature de Contrat
     * Returns: Array of contract types with document-level completion percentages
     */
    public function getKpiD(): array
    {
        try {
            $query = "
                SELECT 
                    contract_type,
                    document_abbreviation,
                    document_type,
                    completion_percentage
                FROM rh_olap.kpi_d_evolution
                WHERE event_date = (SELECT max(event_date) FROM rh_olap.kpi_d_evolution)
                ORDER BY contract_type, document_abbreviation, document_type
            ";

            $results = $this->clickHouseClient->select($query);

            // Group by contract type and document
            $formatted = [];
            foreach ($results as $row) {
                // Handle both associative and positional arrays
                if (isset($row['contract_type'])) {
                    $contractType = trim($row['contract_type'] ?? '');
                    $docAbbr = trim($row['document_abbreviation'] ?? '');
                    $docType = trim($row['document_type'] ?? '');
                    $completion = (float)($row['completion_percentage'] ?? 0);
                } elseif (isset($row[0])) {
                    // Positional: event_date[0], contract_type[1], document_abbreviation[2], document_type[3], completion_percentage[4]
                    $contractType = trim($row[1] ?? '');
                    $docAbbr = trim($row[2] ?? '');
                    $docType = trim($row[3] ?? '');
                    $completion = (float)($row[4] ?? 0);
                } else {
                    continue;
                }
                
                if (empty($contractType) || empty($docAbbr) || empty($docType)) {
                    continue;
                }
                
                if (!isset($formatted[$contractType])) {
                    $formatted[$contractType] = [];
                }
                
                $formatted[$contractType][$docAbbr . '_' . $docType] = [
                    'document_abbreviation' => $docAbbr,
                    'document_type' => $docType,
                    'completion_percentage' => $completion,
                ];
            }

            // Convert to array format expected by formatReportDData
            $result = [];
            foreach ($formatted as $contractType => $documents) {
                $result[] = [
                    'contract_type' => $contractType,
                    'documents' => $documents,
                ];
            }

            $this->logger?->info('KPI D fetched', ['rows' => count($result), 'contract_types' => count($formatted)]);
            return $result;
        } catch (\Exception $e) {
            $this->logger?->error('KPI D fetch failed', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * KPI E: Matrice Personnel (Contrat × DAS)
     * Returns: Array of contract type × DAS combinations with completion percentages
     */
    public function getKpiE(): array
    {
        try {
            $query = "
                SELECT 
                    contract_type,
                    das_code,
                    completion_percentage
                FROM rh_olap.kpi_e_personnel_matrix
                WHERE event_date = (SELECT max(event_date) FROM rh_olap.kpi_e_personnel_matrix)
                ORDER BY contract_type, das_code
            ";

            $results = $this->clickHouseClient->select($query);

            // Transform to format expected by formatReportEData
            $formatted = [];
            foreach ($results as $row) {
                // Handle both associative and positional arrays
                if (isset($row['contract_type'])) {
                    $contractType = trim($row['contract_type'] ?? '');
                    $dasCode = trim($row['das_code'] ?? '');
                    $completion = (float)($row['completion_percentage'] ?? 0);
                } elseif (isset($row[0])) {
                    // Positional: event_date[0], contract_type[1], das_code[2], completion_percentage[3]
                    $contractType = trim($row[1] ?? '');
                    $dasCode = trim($row[2] ?? '');
                    $completion = (float)($row[3] ?? 0);
                } else {
                    continue;
                }
                
                if (empty($contractType) || empty($dasCode)) {
                    continue;
                }
                
                $formatted[] = [
                    'contract_type' => $contractType,
                    'das_code' => $dasCode,
                    'completion_percentage' => $completion,
                ];
            }

            $this->logger?->info('KPI E fetched', ['rows' => count($formatted)]);
            return $formatted;
        } catch (\Exception $e) {
            $this->logger?->error('KPI E fetch failed', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * KPI F: Matrice Ayant Droits (Contrat × DAS)
     * Returns: Array of contract type × DAS combinations with completion percentages for Ayant Droits
     */
    public function getKpiF(): array
    {
        try {
            $query = "
                SELECT 
                    contract_type,
                    das_code,
                    completion_percentage
                FROM rh_olap.kpi_f_comparative
                WHERE event_date = (SELECT max(event_date) FROM rh_olap.kpi_f_comparative)
                ORDER BY contract_type, das_code
            ";

            $results = $this->clickHouseClient->select($query);

            // Transform to format expected by formatReportFData
            $formatted = [];
            foreach ($results as $row) {
                // Handle both associative and positional arrays
                if (isset($row['contract_type'])) {
                    $contractType = trim($row['contract_type'] ?? '');
                    $dasCode = trim($row['das_code'] ?? '');
                    $completion = (float)($row['completion_percentage'] ?? 0);
                } elseif (isset($row[0])) {
                    // Positional: event_date[0], contract_type[1], das_code[2], completion_percentage[3]
                    $contractType = trim($row[1] ?? '');
                    $dasCode = trim($row[2] ?? '');
                    $completion = (float)($row[3] ?? 0);
                } else {
                    continue;
                }
                
                if (empty($contractType) || empty($dasCode)) {
                    continue;
                }
                
                $formatted[] = [
                    'contract_type' => $contractType,
                    'das_code' => $dasCode,
                    'completion_percentage' => $completion,
                ];
            }

            $this->logger?->info('KPI F fetched', ['rows' => count($formatted)]);
            return $formatted;
        } catch (\Exception $e) {
            $this->logger?->error('KPI F fetch failed', ['error' => $e->getMessage()]);
            return [];
        }
    }
}



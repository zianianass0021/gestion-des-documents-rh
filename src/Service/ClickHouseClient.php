<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * ClickHouse HTTP Client for OLAP operations
 * 
 * Provides methods to execute queries and manage data in ClickHouse
 * using the HTTP interface for better compatibility and error handling.
 */
class ClickHouseClient
{
    private string $host;
    private int $port;
    private string $database;
    private string $username;
    private string $password;
    private string $httpUrl;
    private HttpClientInterface $httpClient;
    private ?LoggerInterface $logger;

    public function __construct(
        string $host,
        int $port,
        string $database,
        string $username,
        string $password,
        string $httpUrl,
        HttpClientInterface $httpClient,
        ?LoggerInterface $logger = null
    ) {
        $this->host = $host;
        $this->port = $port;
        $this->database = $database;
        $this->username = $username;
        $this->password = $password;
        $this->httpUrl = $httpUrl;
        $this->httpClient = $httpClient;
        $this->logger = $logger;
    }

    /**
     * Execute a SELECT query and return results as array
     */
    public function select(string $query, array $params = []): array
    {
        try {
            // Use JSON format for better parsing when SELECT * is used
            $queryWithFormat = $query;
            if (stripos(trim($query), 'FORMAT') === false) {
                $queryWithFormat = rtrim($query, ';') . ' FORMAT JSONEachRow';
            }
            
            $response = $this->executeQuery($queryWithFormat, $params);
            $content = $response->getContent();
            
            // If JSON format was used
            if (stripos($queryWithFormat, 'FORMAT JSON') !== false) {
                $lines = explode("\n", trim($content));
                $result = [];
                foreach ($lines as $line) {
                    if (trim($line) === '') continue;
                    $decoded = json_decode($line, true);
                    if ($decoded !== null) {
                        $result[] = $decoded;
                    }
                }
                
                $this->logger?->info('ClickHouse SELECT executed (JSON)', [
                    'query' => $query,
                    'rows_returned' => count($result)
                ]);
                
                return $result;
            }
            
            // Fallback to TSV parsing
            $lines = explode("\n", trim($content));
            if (empty($lines) || $lines[0] === '') {
                return [];
            }
            
            // Parse TSV format
            $result = [];
            foreach ($lines as $line) {
                if (trim($line) === '') continue;
                $result[] = explode("\t", $line);
            }
            
            // Convert to associative array if we have data
            if (!empty($result)) {
                // For simple queries like "SELECT 1", return the raw values
                if (count($result[0]) === 1) {
                    return array_map(fn($row) => $row[0], $result);
                }
                
                // For queries with multiple columns, try to get column names
                $columnNames = $this->getColumnNames($query);
                if (!empty($columnNames) && count($columnNames) === count($result[0])) {
                    $associativeResult = [];
                    foreach ($result as $row) {
                        $associativeRow = [];
                        foreach ($columnNames as $index => $columnName) {
                            $associativeRow[$columnName] = $row[$index] ?? null;
                        }
                        $associativeResult[] = $associativeRow;
                    }
                    $result = $associativeResult;
                }
            }
            
            $this->logger?->info('ClickHouse SELECT executed', [
                'query' => $query,
                'params' => $params,
                'rows_returned' => count($result)
            ]);
            
            return $result;
        } catch (\Exception $e) {
            $this->logger?->error('ClickHouse SELECT failed', [
                'query' => $query,
                'params' => $params,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Execute an INSERT query
     */
    public function insert(string $query, array $params = []): bool
    {
        try {
            $this->executeQuery($query, $params);
            
            $this->logger?->info('ClickHouse INSERT executed', [
                'query' => $query,
                'params' => $params
            ]);
            
            return true;
        } catch (\Exception $e) {
            $this->logger?->error('ClickHouse INSERT failed', [
                'query' => $query,
                'params' => $params,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Execute any query (SELECT, INSERT, CREATE, etc.)
     */
    public function execute(string $query, array $params = []): ResponseInterface
    {
        return $this->executeQuery($query, $params);
    }

    /**
     * Test connection to ClickHouse
     */
    public function testConnection(): bool
    {
        try {
            $response = $this->execute('SELECT 1');
            $statusCode = $response->getStatusCode();
            
            if ($statusCode === 200) {
                $this->logger?->info('ClickHouse connection test successful');
                return true;
            }
            
            $this->logger?->error('ClickHouse connection test failed', [
                'status_code' => $statusCode
            ]);
            return false;
        } catch (\Exception $e) {
            $this->logger?->error('ClickHouse connection test failed', [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Get database information
     */
    public function getDatabaseInfo(): array
    {
        $query = "SELECT name, engine FROM system.databases WHERE name = ?";
        return $this->select($query, [$this->database]);
    }

    /**
     * Get table information for the current database
     */
    public function getTables(): array
    {
        $query = "SELECT name, engine, total_rows, total_bytes FROM system.tables WHERE database = ?";
        return $this->select($query, [$this->database]);
    }

    /**
     * Execute a query with parameters
     */
    private function executeQuery(string $query, array $params = []): ResponseInterface
    {
        // Replace parameter placeholders in the query
        $processedQuery = $this->processQuery($query, $params);
        
        // Build URL with query parameters
        $url = $this->httpUrl . '?database=' . urlencode($this->database);
        
        $response = $this->httpClient->request('POST', $url, [
            'headers' => [
                'Content-Type' => 'text/plain',
                'X-ClickHouse-User' => $this->username,
                'X-ClickHouse-Key' => $this->password,
            ],
            'body' => $processedQuery,
            'timeout' => 30,
        ]);

        if ($response->getStatusCode() !== 200) {
            throw new \RuntimeException(
                'ClickHouse query failed: ' . $response->getContent(false)
            );
        }

        return $response;
    }

    /**
     * Process query parameters (simple placeholder replacement)
     * For production, consider using a proper query builder
     */
    private function processQuery(string $query, array $params): string
    {
        if (empty($params)) {
            return $query;
        }

        $processedQuery = $query;
        foreach ($params as $key => $value) {
            if (is_string($key) && strpos($processedQuery, ':' . $key) !== false) {
                $processedQuery = str_replace(':' . $key, $this->escapeValue($value), $processedQuery);
            } elseif (is_numeric($key)) {
                // For positional parameters, replace ? placeholders
                $processedQuery = preg_replace('/\?/', $this->escapeValue($value), $processedQuery, 1);
            }
        }

        return $processedQuery;
    }

    /**
     * Escape values for ClickHouse queries
     */
    private function escapeValue($value): string
    {
        if (is_null($value)) {
            return 'NULL';
        }
        
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        
        if (is_numeric($value)) {
            return (string) $value;
        }
        
        // Escape string values
        return "'" . str_replace("'", "''", (string) $value) . "'";
    }

    /**
     * Get column names for a query (simplified approach)
     */
    private function getColumnNames(string $query): array
    {
        // This is a simplified approach - in production you might want to use DESCRIBE or FORMAT JSON
        // For now, we'll extract column names from the SELECT clause
        if (preg_match('/SELECT\s+(.*?)\s+FROM/i', $query, $matches)) {
            $columns = explode(',', $matches[1]);
            return array_map(function($col) {
                $col = trim($col);
                // Handle aliases like "now() as current_time"
                if (preg_match('/\s+as\s+(\w+)$/i', $col, $aliasMatches)) {
                    return $aliasMatches[1];
                }
                // Handle function calls like "version()"
                if (preg_match('/(\w+)\(\)/', $col, $funcMatches)) {
                    return $funcMatches[1];
                }
                return $col;
            }, $columns);
        }
        return [];
    }

    /**
     * Get connection parameters (for debugging)
     */
    public function getConnectionInfo(): array
    {
        return [
            'host' => $this->host,
            'port' => $this->port,
            'database' => $this->database,
            'username' => $this->username,
            'http_url' => $this->httpUrl,
        ];
    }
}

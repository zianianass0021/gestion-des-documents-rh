-- ClickHouse OLAP User Creation Script
-- This script creates a least-privilege user for the application

-- Create read-write user for OLAP operations
CREATE USER IF NOT EXISTS rh_olap_app
IDENTIFIED WITH sha256_password BY 'udoc0021'
SETTINGS
    max_query_size = 100000000,
    max_execution_time = 300,
    readonly = 0;

-- Grant access to OLAP database only
GRANT ALL ON rh_olap.* TO rh_olap_app;

-- Optional: Create a read-only user for reporting/replication
CREATE USER IF NOT EXISTS rh_olap_readonly
IDENTIFIED WITH sha256_password BY 'udoc0021'
SETTINGS
    max_query_size = 100000000,
    max_execution_time = 300,
    readonly = 1;

-- Grant read-only access to OLAP database
GRANT SELECT ON rh_olap.* TO rh_olap_readonly;

-- Verify users were created
SELECT name, auth_type, host_ip FROM system.users WHERE name LIKE 'rh_olap%';


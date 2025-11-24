-- ClickHouse OLAP schema for KPI placeholders
CREATE DATABASE IF NOT EXISTS rh_olap;

CREATE TABLE IF NOT EXISTS rh_olap.kpi_a_contract_type_performance (
  event_date Date,
  contract_type String,
  personnel_completion Float64,
  ayant_droits_completion Float64,
  missing_docs_personnel UInt32,
  missing_docs_ayantdroits UInt32
) ENGINE = MergeTree() ORDER BY (event_date, contract_type);

CREATE TABLE IF NOT EXISTS rh_olap.kpi_b_doc_reliability_by_org (
  event_date Date,
  organization String,
  personnel_completion Float64,
  ayant_droits_completion Float64
) ENGINE = MergeTree() ORDER BY (event_date, organization);

CREATE TABLE IF NOT EXISTS rh_olap.kpi_c_document_matrix (
  event_date Date,
  das_code String,
  document_abbreviation String,
  document_type String,
  completion_percentage Float64
) ENGINE = MergeTree() ORDER BY (event_date, das_code, document_abbreviation);

CREATE TABLE IF NOT EXISTS rh_olap.kpi_d_evolution (
  event_date Date,
  contract_type String,
  document_abbreviation String,
  document_type String,
  completion_percentage Float64
) ENGINE = MergeTree() ORDER BY (event_date, contract_type, document_abbreviation);

CREATE TABLE IF NOT EXISTS rh_olap.kpi_e_personnel_matrix (
  event_date Date,
  contract_type String,
  das_code String,
  completion_percentage Float64
) ENGINE = MergeTree() ORDER BY (event_date, contract_type, das_code);

CREATE TABLE IF NOT EXISTS rh_olap.kpi_f_comparative (
  event_date Date,
  contract_type String,
  das_code String,
  completion_percentage Float64
) ENGINE = MergeTree() ORDER BY (event_date, contract_type, das_code);



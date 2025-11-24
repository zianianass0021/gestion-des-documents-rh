-- Seed zeroed rows for initial dashboard wiring
INSERT INTO rh_olap.kpi_a_contract_type_performance VALUES (today(), 'NATIONAL SALARIÉ PERMANENT CDI', 0, 0, 0, 0);
INSERT INTO rh_olap.kpi_b_doc_reliability_by_org VALUES (today(), 'ORGANISATION_DEFAULT', 0, 0);
INSERT INTO rh_olap.kpi_c_document_matrix VALUES (today(), 'ACTE DE NAISSANCE', 0, 0);
INSERT INTO rh_olap.kpi_d_evolution VALUES (today(), 'total_employees', 0);
INSERT INTO rh_olap.kpi_e_personnel_matrix VALUES (today(), 'Personnel', 'CDI', 0);
INSERT INTO rh_olap.kpi_f_comparative VALUES (today(), 'GLOBAL', 'completion', 0);



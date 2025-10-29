<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Database optimization migration
 * Adds composite indexes, full-text search, and query optimizations
 */
final class Version20251028135751 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Database optimization: Add composite indexes, full-text search, and query optimizations';
    }

    public function up(Schema $schema): void
    {
        // Composite indexes for common query patterns
        
        // For employee search by name/email
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_employe_search ON t_employe(nom, prenom, email)');
        
        // For dossier + document lookups
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_document_composite ON p_document(dossier_id, abbreviation)');
        
        // For employee + contract queries
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_employee_contrat_composite ON t_employee_contrat(employe_id, statut, nature_contrat_id)');
        
        // For demandes filtering by employee and status
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_demandes_composite ON t_demandes(employe_id, statut, date_creation DESC)');
        
        // For organisation + contract lookups
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_org_contrat_composite ON t_organisation_employee_contrat(organisation_id, employee_contrat_id)');
        
        // Partial indexes for active employees only
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_employe_active ON t_employe(id) WHERE is_active = true');
        
        // Partial indexes for active contracts only
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_contrat_actif ON t_employee_contrat(employe_id, nature_contrat_id) WHERE statut = \'actif\'');
        
        // Index for completed dossiers
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_dossier_completed ON t_dossier(id, employe_id) WHERE status = \'completed\'');
        
        // Full-text search indexes for PostgreSQL
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_employe_fulltext ON t_employe USING gin(to_tsvector(\'french\', coalesce(nom, \'\') || \' \' || coalesce(prenom, \'\') || \' \' || coalesce(email, \'\')))');
        
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_dossier_fulltext ON t_dossier USING gin(to_tsvector(\'french\', coalesce(nom, \'\') || \' \' || coalesce(description, \'\')))');
        
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_document_fulltext ON p_document USING gin(to_tsvector(\'french\', coalesce(libelle_complet, \'\') || \' \' || coalesce(abbreviation, \'\') || \' \' || coalesce(type_document, \'\')))');
        
        // Materialized view for dashboard KPIs (optional - uncomment if needed)
        // $this->addSql('CREATE MATERIALIZED VIEW IF NOT EXISTS mv_dashboard_kpis AS 
        //     SELECT 
        //         (SELECT COUNT(*) FROM t_employe WHERE roles::text LIKE \'%ROLE_EMPLOYEE%\') as total_employees,
        //         (SELECT COUNT(*) FROM t_demandes WHERE statut = \'en_attente\') as demandes_en_attente,
        //         (SELECT COUNT(*) FROM t_reclamation WHERE statut != \'resolu\') as reclamations_non_resolues
        // ');
        // $this->addSql('CREATE UNIQUE INDEX ON mv_dashboard_kpis (total_employees, demandes_en_attente, reclamations_non_resolues)');
        
        // Analyze tables for query planner optimization
        $this->addSql('ANALYZE t_employe');
        $this->addSql('ANALYZE t_dossier');
        $this->addSql('ANALYZE p_document');
        $this->addSql('ANALYZE t_employee_contrat');
        $this->addSql('ANALYZE t_demandes');
        $this->addSql('ANALYZE t_organisation_employee_contrat');
        $this->addSql('ANALYZE t_reclamation');
        
        // Note: VACUUM cannot run inside a transaction block
        // Run manually if needed: VACUUM ANALYZE tablename;
    }

    public function down(Schema $schema): void
    {
        // Drop all optimization indexes
        
        $this->addSql('DROP INDEX IF EXISTS idx_employe_search');
        $this->addSql('DROP INDEX IF EXISTS idx_document_composite');
        $this->addSql('DROP INDEX IF EXISTS idx_employee_contrat_composite');
        $this->addSql('DROP INDEX IF EXISTS idx_demandes_composite');
        $this->addSql('DROP INDEX IF EXISTS idx_org_contrat_composite');
        $this->addSql('DROP INDEX IF EXISTS idx_employe_active');
        $this->addSql('DROP INDEX IF EXISTS idx_contrat_actif');
        $this->addSql('DROP INDEX IF EXISTS idx_dossier_completed');
        $this->addSql('DROP INDEX IF EXISTS idx_employe_fulltext');
        $this->addSql('DROP INDEX IF EXISTS idx_dossier_fulltext');
        $this->addSql('DROP INDEX IF EXISTS idx_document_fulltext');
        
        // Drop materialized view if it exists
        // $this->addSql('DROP MATERIALIZED VIEW IF EXISTS mv_dashboard_kpis');
    }
}

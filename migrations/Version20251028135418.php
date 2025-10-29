<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251028135418 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add database indexes for performance optimization';
    }

    public function up(Schema $schema): void
    {
        // Add indexes for frequently queried columns to improve performance
        
        // Indexes for t_employe table
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_employe_roles ON t_employe((roles::text))');
        
        // Indexes for t_dossier table
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_dossier_status ON t_dossier(status)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_dossier_employe_id ON t_dossier(employe_id)');
        
        // Indexes for t_employee_contrat table
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_employee_contrat_employe_id ON t_employee_contrat(employe_id)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_employee_contrat_nature_contrat_id ON t_employee_contrat(nature_contrat_id)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_employee_contrat_statut ON t_employee_contrat(statut)');
        
        // Indexes for t_demandes table
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_demandes_statut ON t_demandes(statut)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_demandes_date_creation ON t_demandes(date_creation DESC)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_demandes_employe_id ON t_demandes(employe_id)');
        
        // Indexes for p_document table
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_document_dossier_id ON p_document(dossier_id)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_document_abbreviation ON p_document(abbreviation)');
        
        // Indexes for t_organisation_employee_contrat table
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_org_employee_contrat_organisation_id ON t_organisation_employee_contrat(organisation_id)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_org_employee_contrat_employee_contrat_id ON t_organisation_employee_contrat(employee_contrat_id)');
        
        // Indexes for t_reclamation table
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_reclamation_statut ON t_reclamation(statut)');
    }

    public function down(Schema $schema): void
    {
        // Drop all performance indexes
        
        $this->addSql('DROP INDEX IF EXISTS idx_employe_roles');
        $this->addSql('DROP INDEX IF EXISTS idx_dossier_status');
        $this->addSql('DROP INDEX IF EXISTS idx_dossier_employe_id');
        $this->addSql('DROP INDEX IF EXISTS idx_employee_contrat_employe_id');
        $this->addSql('DROP INDEX IF EXISTS idx_employee_contrat_nature_contrat_id');
        $this->addSql('DROP INDEX IF EXISTS idx_employee_contrat_statut');
        $this->addSql('DROP INDEX IF EXISTS idx_demandes_statut');
        $this->addSql('DROP INDEX IF EXISTS idx_demandes_date_creation');
        $this->addSql('DROP INDEX IF EXISTS idx_demandes_employe_id');
        $this->addSql('DROP INDEX IF EXISTS idx_document_dossier_id');
        $this->addSql('DROP INDEX IF EXISTS idx_document_abbreviation');
        $this->addSql('DROP INDEX IF EXISTS idx_org_employee_contrat_organisation_id');
        $this->addSql('DROP INDEX IF EXISTS idx_org_employee_contrat_employee_contrat_id');
        $this->addSql('DROP INDEX IF EXISTS idx_reclamation_statut');
    }
}

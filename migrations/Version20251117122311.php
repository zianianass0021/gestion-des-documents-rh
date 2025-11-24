<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251117122311 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SEQUENCE modules_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE user_module_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE TABLE modules (id INT NOT NULL, code VARCHAR(50) NOT NULL, label VARCHAR(100) NOT NULL, description TEXT DEFAULT NULL, icon VARCHAR(50) DEFAULT NULL, route_prefix VARCHAR(100) DEFAULT NULL, "order" SMALLINT DEFAULT 0 NOT NULL, is_active BOOLEAN DEFAULT true NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX unique_module_code ON modules (code)');
        $this->addSql('CREATE TABLE user_module (id INT NOT NULL, user_id INT NOT NULL, module_id INT NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX idx_user_id ON user_module (user_id)');
        $this->addSql('CREATE INDEX idx_module_id ON user_module (module_id)');
        $this->addSql('CREATE UNIQUE INDEX unique_user_module ON user_module (user_id, module_id)');
        $this->addSql('ALTER TABLE user_module ADD CONSTRAINT FK_69763D15A76ED395 FOREIGN KEY (user_id) REFERENCES t_user (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE user_module ADD CONSTRAINT FK_69763D15AFC2B591 FOREIGN KEY (module_id) REFERENCES modules (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('DROP INDEX idx_document_abbreviation');
        $this->addSql('DROP INDEX idx_document_composite');
        $this->addSql('DROP INDEX idx_document_dossier_id');
        $this->addSql('DROP INDEX idx_demandes_composite');
        $this->addSql('DROP INDEX idx_demandes_date_creation');
        $this->addSql('DROP INDEX idx_demandes_employe_id');
        $this->addSql('DROP INDEX idx_demandes_statut');
        $this->addSql('DROP INDEX idx_dossier_completed');
        $this->addSql('DROP INDEX idx_dossier_employe_id');
        $this->addSql('DROP INDEX idx_dossier_status');
        $this->addSql('DROP INDEX idx_employe_active');
        $this->addSql('DROP INDEX idx_employe_search');
        $this->addSql('DROP INDEX idx_contrat_actif');
        $this->addSql('DROP INDEX idx_employee_contrat_composite');
        $this->addSql('DROP INDEX idx_employee_contrat_user_id');
        $this->addSql('DROP INDEX idx_employee_contrat_nature_contrat_id');
        $this->addSql('DROP INDEX idx_employee_contrat_statut');
        $this->addSql('DROP INDEX idx_org_contrat_composite');
        $this->addSql('DROP INDEX idx_org_employee_contrat_usere_contrat_id');
        $this->addSql('DROP INDEX idx_org_employee_contrat_organisation_id');
        $this->addSql('DROP INDEX idx_reclamation_statut');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('DROP SEQUENCE modules_id_seq CASCADE');
        $this->addSql('DROP SEQUENCE user_module_id_seq CASCADE');
        $this->addSql('ALTER TABLE user_module DROP CONSTRAINT FK_69763D15A76ED395');
        $this->addSql('ALTER TABLE user_module DROP CONSTRAINT FK_69763D15AFC2B591');
        $this->addSql('DROP TABLE modules');
        $this->addSql('DROP TABLE user_module');
        $this->addSql('CREATE INDEX idx_contrat_actif ON t_usere_contrat (employe_id, nature_contrat_id) WHERE ((statut)::text = \'actif\'::text)');
        $this->addSql('CREATE INDEX idx_employee_contrat_composite ON t_usere_contrat (employe_id, statut, nature_contrat_id)');
        $this->addSql('CREATE INDEX idx_employee_contrat_user_id ON t_usere_contrat (employe_id)');
        $this->addSql('CREATE INDEX idx_employee_contrat_nature_contrat_id ON t_usere_contrat (nature_contrat_id)');
        $this->addSql('CREATE INDEX idx_employee_contrat_statut ON t_usere_contrat (statut)');
        $this->addSql('CREATE INDEX idx_org_contrat_composite ON t_organisation_employee_contrat (organisation_id, employee_contrat_id)');
        $this->addSql('CREATE INDEX idx_org_employee_contrat_usere_contrat_id ON t_organisation_employee_contrat (employee_contrat_id)');
        $this->addSql('CREATE INDEX idx_org_employee_contrat_organisation_id ON t_organisation_employee_contrat (organisation_id)');
        $this->addSql('CREATE INDEX idx_reclamation_statut ON t_reclamation (statut)');
        $this->addSql('CREATE INDEX idx_employe_active ON t_user (id) WHERE (is_active = true)');
        $this->addSql('CREATE INDEX idx_employe_search ON t_user (nom, prenom, email)');
        $this->addSql('CREATE INDEX idx_document_abbreviation ON p_document (abbreviation)');
        $this->addSql('CREATE INDEX idx_document_composite ON p_document (dossier_id, abbreviation)');
        $this->addSql('CREATE INDEX idx_document_dossier_id ON p_document (dossier_id)');
        $this->addSql('CREATE INDEX idx_demandes_composite ON t_demandes (employe_id, statut, date_creation)');
        $this->addSql('CREATE INDEX idx_demandes_date_creation ON t_demandes (date_creation)');
        $this->addSql('CREATE INDEX idx_demandes_employe_id ON t_demandes (employe_id)');
        $this->addSql('CREATE INDEX idx_demandes_statut ON t_demandes (statut)');
        $this->addSql('CREATE INDEX idx_dossier_completed ON t_dossier (id, employe_id) WHERE ((status)::text = \'completed\'::text)');
        $this->addSql('CREATE INDEX idx_dossier_employe_id ON t_dossier (employe_id)');
        $this->addSql('CREATE INDEX idx_dossier_status ON t_dossier (status)');
    }
}

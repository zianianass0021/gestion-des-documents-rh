<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251117142124 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // Rename sequence
        $this->addSql('ALTER SEQUENCE t_employe_id_seq RENAME TO t_user_id_seq');
        
        // Rename table
        $this->addSql('ALTER TABLE t_employe RENAME TO t_user');
        
        // Rename indexes
        $this->addSql('ALTER INDEX uniq_a2286bdae7927c74 RENAME TO uniq_37e5bf3be7927c74');
        $this->addSql('ALTER INDEX uniq_a2286bdaf85e0677 RENAME TO uniq_37e5bf3bf85e0677');
        
        // Drop old foreign key constraints
        $this->addSql('ALTER TABLE t_demandes DROP CONSTRAINT IF EXISTS fk_bd940cbb1b65292');
        $this->addSql('ALTER TABLE t_demandes DROP CONSTRAINT IF EXISTS fk_bd940cbb4333a21e');
        $this->addSql('ALTER TABLE user_module DROP CONSTRAINT IF EXISTS fk_69763d15a76ed395');
        $this->addSql('ALTER TABLE t_dossier DROP CONSTRAINT IF EXISTS fk_3d48e0371b65292');
        $this->addSql('ALTER TABLE t_employee_contrat DROP CONSTRAINT IF EXISTS fk_e078cf9c1b65292');
        $this->addSql('ALTER TABLE t_reclamation DROP CONSTRAINT IF EXISTS fk_9e314005167fabe8');
        $this->addSql('ALTER TABLE t_reclamation DROP CONSTRAINT IF EXISTS fk_9e3140051b65292');
        $this->addSql('ALTER TABLE t_reclamation DROP CONSTRAINT IF EXISTS fk_9e314005783e3463');
        
        // Recreate foreign key constraints pointing to t_user
        $this->addSql('ALTER TABLE t_demandes ADD CONSTRAINT FK_697510E11B65292 FOREIGN KEY (employe_id) REFERENCES t_user (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE t_demandes ADD CONSTRAINT FK_697510E14333A21E FOREIGN KEY (responsable_rh_id) REFERENCES t_user (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE t_dossier ADD CONSTRAINT FK_676458541B65292 FOREIGN KEY (employe_id) REFERENCES t_user (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE t_employee_contrat ADD CONSTRAINT FK_258DC7961B65292 FOREIGN KEY (employe_id) REFERENCES t_user (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE t_reclamation ADD CONSTRAINT FK_9E314005167FABE8 FOREIGN KEY (traite_par_id) REFERENCES t_user (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE t_reclamation ADD CONSTRAINT FK_9E3140051B65292 FOREIGN KEY (employe_id) REFERENCES t_user (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE t_reclamation ADD CONSTRAINT FK_9E314005783E3463 FOREIGN KEY (manager_id) REFERENCES t_user (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE user_module ADD CONSTRAINT FK_69763D15A76ED395 FOREIGN KEY (user_id) REFERENCES t_user (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        
        // Rename indexes that reference t_employe
        $this->addSql('ALTER INDEX IF EXISTS idx_employe_active RENAME TO idx_user_active');
        $this->addSql('ALTER INDEX IF EXISTS idx_employe_search RENAME TO idx_user_search');
        $this->addSql('ALTER INDEX IF EXISTS idx_employe_roles RENAME TO idx_user_roles');
        $this->addSql('ALTER INDEX IF EXISTS idx_employe_fulltext RENAME TO idx_user_fulltext');
    }

    public function down(Schema $schema): void
    {
        // Drop foreign key constraints
        $this->addSql('ALTER TABLE t_demandes DROP CONSTRAINT IF EXISTS FK_697510E11B65292');
        $this->addSql('ALTER TABLE t_demandes DROP CONSTRAINT IF EXISTS FK_697510E14333A21E');
        $this->addSql('ALTER TABLE t_dossier DROP CONSTRAINT IF EXISTS FK_676458541B65292');
        $this->addSql('ALTER TABLE t_employee_contrat DROP CONSTRAINT IF EXISTS FK_258DC7961B65292');
        $this->addSql('ALTER TABLE t_reclamation DROP CONSTRAINT IF EXISTS FK_9E3140051B65292');
        $this->addSql('ALTER TABLE t_reclamation DROP CONSTRAINT IF EXISTS FK_9E314005783E3463');
        $this->addSql('ALTER TABLE t_reclamation DROP CONSTRAINT IF EXISTS FK_9E314005167FABE8');
        $this->addSql('ALTER TABLE user_module DROP CONSTRAINT IF EXISTS FK_69763D15A76ED395');
        
        // Rename indexes back
        $this->addSql('ALTER INDEX IF EXISTS idx_user_active RENAME TO idx_employe_active');
        $this->addSql('ALTER INDEX IF EXISTS idx_user_search RENAME TO idx_employe_search');
        $this->addSql('ALTER INDEX IF EXISTS idx_user_roles RENAME TO idx_employe_roles');
        $this->addSql('ALTER INDEX IF EXISTS idx_user_fulltext RENAME TO idx_employe_fulltext');
        
        // Rename table back
        $this->addSql('ALTER TABLE t_user RENAME TO t_employe');
        
        // Rename sequence back
        $this->addSql('ALTER SEQUENCE t_user_id_seq RENAME TO t_employe_id_seq');
        
        // Rename indexes back
        $this->addSql('ALTER INDEX uniq_37e5bf3be7927c74 RENAME TO uniq_a2286bdae7927c74');
        $this->addSql('ALTER INDEX uniq_37e5bf3bf85e0677 RENAME TO uniq_a2286bdaf85e0677');
        
        // Recreate foreign key constraints pointing to t_employe
        $this->addSql('ALTER TABLE t_demandes ADD CONSTRAINT fk_bd940cbb1b65292 FOREIGN KEY (employe_id) REFERENCES t_employe (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE t_demandes ADD CONSTRAINT fk_bd940cbb4333a21e FOREIGN KEY (responsable_rh_id) REFERENCES t_employe (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE user_module ADD CONSTRAINT fk_69763d15a76ed395 FOREIGN KEY (user_id) REFERENCES t_employe (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE t_dossier ADD CONSTRAINT fk_3d48e0371b65292 FOREIGN KEY (employe_id) REFERENCES t_employe (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE t_employee_contrat ADD CONSTRAINT fk_e078cf9c1b65292 FOREIGN KEY (employe_id) REFERENCES t_employe (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE t_reclamation ADD CONSTRAINT fk_9e3140051b65292 FOREIGN KEY (employe_id) REFERENCES t_employe (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE t_reclamation ADD CONSTRAINT fk_9e314005783e3463 FOREIGN KEY (manager_id) REFERENCES t_employe (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE t_reclamation ADD CONSTRAINT fk_9e314005167fabe8 FOREIGN KEY (traite_par_id) REFERENCES t_employe (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }
}

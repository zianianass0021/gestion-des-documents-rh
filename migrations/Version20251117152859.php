<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251117152859 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE p_document ADD created_by_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE p_document ADD updated_by_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE p_document ADD disabled_by_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE p_document ADD created_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP');
        $this->addSql('UPDATE p_document SET created_at = CURRENT_TIMESTAMP WHERE created_at IS NULL');
        $this->addSql('ALTER TABLE p_document ALTER COLUMN created_at SET NOT NULL');
        $this->addSql('ALTER TABLE p_document ADD updated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE p_document ADD disabled_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE p_document ADD CONSTRAINT FK_5633656B03A8386 FOREIGN KEY (created_by_id) REFERENCES t_user (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE p_document ADD CONSTRAINT FK_5633656896DBBDE FOREIGN KEY (updated_by_id) REFERENCES t_user (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE p_document ADD CONSTRAINT FK_56336561688BE50 FOREIGN KEY (disabled_by_id) REFERENCES t_user (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_5633656B03A8386 ON p_document (created_by_id)');
        $this->addSql('CREATE INDEX IDX_5633656896DBBDE ON p_document (updated_by_id)');
        $this->addSql('CREATE INDEX IDX_56336561688BE50 ON p_document (disabled_by_id)');
        $this->addSql('ALTER TABLE p_organisation ADD created_by_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE p_organisation ADD updated_by_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE p_organisation ADD disabled_by_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE p_organisation ADD created_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP');
        $this->addSql('UPDATE p_organisation SET created_at = CURRENT_TIMESTAMP WHERE created_at IS NULL');
        $this->addSql('ALTER TABLE p_organisation ALTER COLUMN created_at SET NOT NULL');
        $this->addSql('ALTER TABLE p_organisation ADD updated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE p_organisation ADD disabled_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE p_organisation ADD CONSTRAINT FK_881ADC1DB03A8386 FOREIGN KEY (created_by_id) REFERENCES t_user (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE p_organisation ADD CONSTRAINT FK_881ADC1D896DBBDE FOREIGN KEY (updated_by_id) REFERENCES t_user (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE p_organisation ADD CONSTRAINT FK_881ADC1D1688BE50 FOREIGN KEY (disabled_by_id) REFERENCES t_user (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_881ADC1DB03A8386 ON p_organisation (created_by_id)');
        $this->addSql('CREATE INDEX IDX_881ADC1D896DBBDE ON p_organisation (updated_by_id)');
        $this->addSql('CREATE INDEX IDX_881ADC1D1688BE50 ON p_organisation (disabled_by_id)');
        $this->addSql('ALTER TABLE t_demandes ADD created_by_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE t_demandes ADD updated_by_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE t_demandes ADD disabled_by_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE t_demandes ADD CONSTRAINT FK_697510E1B03A8386 FOREIGN KEY (created_by_id) REFERENCES t_user (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE t_demandes ADD CONSTRAINT FK_697510E1896DBBDE FOREIGN KEY (updated_by_id) REFERENCES t_user (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE t_demandes ADD CONSTRAINT FK_697510E11688BE50 FOREIGN KEY (disabled_by_id) REFERENCES t_user (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_697510E1B03A8386 ON t_demandes (created_by_id)');
        $this->addSql('CREATE INDEX IDX_697510E1896DBBDE ON t_demandes (updated_by_id)');
        $this->addSql('CREATE INDEX IDX_697510E11688BE50 ON t_demandes (disabled_by_id)');
        $this->addSql('ALTER TABLE t_dossier ADD created_by_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE t_dossier ADD updated_by_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE t_dossier ADD disabled_by_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE t_dossier ADD updated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE t_dossier ADD disabled_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE t_dossier ADD CONSTRAINT FK_67645854B03A8386 FOREIGN KEY (created_by_id) REFERENCES t_user (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE t_dossier ADD CONSTRAINT FK_67645854896DBBDE FOREIGN KEY (updated_by_id) REFERENCES t_user (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE t_dossier ADD CONSTRAINT FK_676458541688BE50 FOREIGN KEY (disabled_by_id) REFERENCES t_user (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_67645854B03A8386 ON t_dossier (created_by_id)');
        $this->addSql('CREATE INDEX IDX_67645854896DBBDE ON t_dossier (updated_by_id)');
        $this->addSql('CREATE INDEX IDX_676458541688BE50 ON t_dossier (disabled_by_id)');
        $this->addSql('ALTER TABLE t_employee_contrat ADD created_by_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE t_employee_contrat ADD updated_by_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE t_employee_contrat ADD disabled_by_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE t_employee_contrat ADD created_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP');
        $this->addSql('UPDATE t_employee_contrat SET created_at = CURRENT_TIMESTAMP WHERE created_at IS NULL');
        $this->addSql('ALTER TABLE t_employee_contrat ALTER COLUMN created_at SET NOT NULL');
        $this->addSql('ALTER TABLE t_employee_contrat ADD updated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE t_employee_contrat ADD disabled_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE t_employee_contrat ADD CONSTRAINT FK_258DC796B03A8386 FOREIGN KEY (created_by_id) REFERENCES t_user (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE t_employee_contrat ADD CONSTRAINT FK_258DC796896DBBDE FOREIGN KEY (updated_by_id) REFERENCES t_user (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE t_employee_contrat ADD CONSTRAINT FK_258DC7961688BE50 FOREIGN KEY (disabled_by_id) REFERENCES t_user (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_258DC796B03A8386 ON t_employee_contrat (created_by_id)');
        $this->addSql('CREATE INDEX IDX_258DC796896DBBDE ON t_employee_contrat (updated_by_id)');
        $this->addSql('CREATE INDEX IDX_258DC7961688BE50 ON t_employee_contrat (disabled_by_id)');
        $this->addSql('ALTER TABLE t_reclamation ADD created_by_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE t_reclamation ADD updated_by_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE t_reclamation ADD disabled_by_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE t_reclamation ADD CONSTRAINT FK_9E314005B03A8386 FOREIGN KEY (created_by_id) REFERENCES t_user (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE t_reclamation ADD CONSTRAINT FK_9E314005896DBBDE FOREIGN KEY (updated_by_id) REFERENCES t_user (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE t_reclamation ADD CONSTRAINT FK_9E3140051688BE50 FOREIGN KEY (disabled_by_id) REFERENCES t_user (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_9E314005B03A8386 ON t_reclamation (created_by_id)');
        $this->addSql('CREATE INDEX IDX_9E314005896DBBDE ON t_reclamation (updated_by_id)');
        $this->addSql('CREATE INDEX IDX_9E3140051688BE50 ON t_reclamation (disabled_by_id)');
        $this->addSql('ALTER TABLE t_user ADD created_by_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE t_user ADD updated_by_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE t_user ADD disabled_by_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE t_user ADD created_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP');
        $this->addSql('UPDATE t_user SET created_at = CURRENT_TIMESTAMP WHERE created_at IS NULL');
        $this->addSql('ALTER TABLE t_user ALTER COLUMN created_at SET NOT NULL');
        $this->addSql('ALTER TABLE t_user ADD updated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE t_user ADD disabled_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE t_user ADD CONSTRAINT FK_37E5BF3BB03A8386 FOREIGN KEY (created_by_id) REFERENCES t_user (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE t_user ADD CONSTRAINT FK_37E5BF3B896DBBDE FOREIGN KEY (updated_by_id) REFERENCES t_user (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE t_user ADD CONSTRAINT FK_37E5BF3B1688BE50 FOREIGN KEY (disabled_by_id) REFERENCES t_user (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_37E5BF3BB03A8386 ON t_user (created_by_id)');
        $this->addSql('CREATE INDEX IDX_37E5BF3B896DBBDE ON t_user (updated_by_id)');
        $this->addSql('CREATE INDEX IDX_37E5BF3B1688BE50 ON t_user (disabled_by_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE t_employee_contrat DROP CONSTRAINT FK_258DC796B03A8386');
        $this->addSql('ALTER TABLE t_employee_contrat DROP CONSTRAINT FK_258DC796896DBBDE');
        $this->addSql('ALTER TABLE t_employee_contrat DROP CONSTRAINT FK_258DC7961688BE50');
        $this->addSql('DROP INDEX IDX_258DC796B03A8386');
        $this->addSql('DROP INDEX IDX_258DC796896DBBDE');
        $this->addSql('DROP INDEX IDX_258DC7961688BE50');
        $this->addSql('ALTER TABLE t_employee_contrat DROP created_by_id');
        $this->addSql('ALTER TABLE t_employee_contrat DROP updated_by_id');
        $this->addSql('ALTER TABLE t_employee_contrat DROP disabled_by_id');
        $this->addSql('ALTER TABLE t_employee_contrat DROP created_at');
        $this->addSql('ALTER TABLE t_employee_contrat DROP updated_at');
        $this->addSql('ALTER TABLE t_employee_contrat DROP disabled_at');
        $this->addSql('ALTER TABLE t_dossier DROP CONSTRAINT FK_67645854B03A8386');
        $this->addSql('ALTER TABLE t_dossier DROP CONSTRAINT FK_67645854896DBBDE');
        $this->addSql('ALTER TABLE t_dossier DROP CONSTRAINT FK_676458541688BE50');
        $this->addSql('DROP INDEX IDX_67645854B03A8386');
        $this->addSql('DROP INDEX IDX_67645854896DBBDE');
        $this->addSql('DROP INDEX IDX_676458541688BE50');
        $this->addSql('ALTER TABLE t_dossier DROP created_by_id');
        $this->addSql('ALTER TABLE t_dossier DROP updated_by_id');
        $this->addSql('ALTER TABLE t_dossier DROP disabled_by_id');
        $this->addSql('ALTER TABLE t_dossier DROP updated_at');
        $this->addSql('ALTER TABLE t_dossier DROP disabled_at');
        $this->addSql('ALTER TABLE p_document DROP CONSTRAINT FK_5633656B03A8386');
        $this->addSql('ALTER TABLE p_document DROP CONSTRAINT FK_5633656896DBBDE');
        $this->addSql('ALTER TABLE p_document DROP CONSTRAINT FK_56336561688BE50');
        $this->addSql('DROP INDEX IDX_5633656B03A8386');
        $this->addSql('DROP INDEX IDX_5633656896DBBDE');
        $this->addSql('DROP INDEX IDX_56336561688BE50');
        $this->addSql('ALTER TABLE p_document DROP created_by_id');
        $this->addSql('ALTER TABLE p_document DROP updated_by_id');
        $this->addSql('ALTER TABLE p_document DROP disabled_by_id');
        $this->addSql('ALTER TABLE p_document DROP created_at');
        $this->addSql('ALTER TABLE p_document DROP updated_at');
        $this->addSql('ALTER TABLE p_document DROP disabled_at');
        $this->addSql('ALTER TABLE t_user DROP CONSTRAINT FK_37E5BF3BB03A8386');
        $this->addSql('ALTER TABLE t_user DROP CONSTRAINT FK_37E5BF3B896DBBDE');
        $this->addSql('ALTER TABLE t_user DROP CONSTRAINT FK_37E5BF3B1688BE50');
        $this->addSql('DROP INDEX IDX_37E5BF3BB03A8386');
        $this->addSql('DROP INDEX IDX_37E5BF3B896DBBDE');
        $this->addSql('DROP INDEX IDX_37E5BF3B1688BE50');
        $this->addSql('ALTER TABLE t_user DROP created_by_id');
        $this->addSql('ALTER TABLE t_user DROP updated_by_id');
        $this->addSql('ALTER TABLE t_user DROP disabled_by_id');
        $this->addSql('ALTER TABLE t_user DROP created_at');
        $this->addSql('ALTER TABLE t_user DROP updated_at');
        $this->addSql('ALTER TABLE t_user DROP disabled_at');
        $this->addSql('ALTER TABLE p_organisation DROP CONSTRAINT FK_881ADC1DB03A8386');
        $this->addSql('ALTER TABLE p_organisation DROP CONSTRAINT FK_881ADC1D896DBBDE');
        $this->addSql('ALTER TABLE p_organisation DROP CONSTRAINT FK_881ADC1D1688BE50');
        $this->addSql('DROP INDEX IDX_881ADC1DB03A8386');
        $this->addSql('DROP INDEX IDX_881ADC1D896DBBDE');
        $this->addSql('DROP INDEX IDX_881ADC1D1688BE50');
        $this->addSql('ALTER TABLE p_organisation DROP created_by_id');
        $this->addSql('ALTER TABLE p_organisation DROP updated_by_id');
        $this->addSql('ALTER TABLE p_organisation DROP disabled_by_id');
        $this->addSql('ALTER TABLE p_organisation DROP created_at');
        $this->addSql('ALTER TABLE p_organisation DROP updated_at');
        $this->addSql('ALTER TABLE p_organisation DROP disabled_at');
        $this->addSql('ALTER TABLE t_demandes DROP CONSTRAINT FK_697510E1B03A8386');
        $this->addSql('ALTER TABLE t_demandes DROP CONSTRAINT FK_697510E1896DBBDE');
        $this->addSql('ALTER TABLE t_demandes DROP CONSTRAINT FK_697510E11688BE50');
        $this->addSql('DROP INDEX IDX_697510E1B03A8386');
        $this->addSql('DROP INDEX IDX_697510E1896DBBDE');
        $this->addSql('DROP INDEX IDX_697510E11688BE50');
        $this->addSql('ALTER TABLE t_demandes DROP created_by_id');
        $this->addSql('ALTER TABLE t_demandes DROP updated_by_id');
        $this->addSql('ALTER TABLE t_demandes DROP disabled_by_id');
        $this->addSql('ALTER TABLE t_reclamation DROP CONSTRAINT FK_9E314005B03A8386');
        $this->addSql('ALTER TABLE t_reclamation DROP CONSTRAINT FK_9E314005896DBBDE');
        $this->addSql('ALTER TABLE t_reclamation DROP CONSTRAINT FK_9E3140051688BE50');
        $this->addSql('DROP INDEX IDX_9E314005B03A8386');
        $this->addSql('DROP INDEX IDX_9E314005896DBBDE');
        $this->addSql('DROP INDEX IDX_9E3140051688BE50');
        $this->addSql('ALTER TABLE t_reclamation DROP created_by_id');
        $this->addSql('ALTER TABLE t_reclamation DROP updated_by_id');
        $this->addSql('ALTER TABLE t_reclamation DROP disabled_by_id');
    }
}

<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251122160322 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SEQUENCE t_responsable_rh_organisation_permission_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE TABLE t_responsable_rh_organisation_permission (id INT NOT NULL, responsable_id INT NOT NULL, groupement VARCHAR(10) DEFAULT NULL, das VARCHAR(10) DEFAULT NULL, dossier VARCHAR(10) DEFAULT NULL, permission_type VARCHAR(20) NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_E82D717453C59D72 ON t_responsable_rh_organisation_permission (responsable_id)');
        $this->addSql('CREATE UNIQUE INDEX unique_permission ON t_responsable_rh_organisation_permission (responsable_id, groupement, das, dossier)');
        $this->addSql('ALTER TABLE t_responsable_rh_organisation_permission ADD CONSTRAINT FK_E82D717453C59D72 FOREIGN KEY (responsable_id) REFERENCES t_user (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE p_placards ALTER is_active DROP DEFAULT');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('DROP SEQUENCE t_responsable_rh_organisation_permission_id_seq CASCADE');
        $this->addSql('ALTER TABLE t_responsable_rh_organisation_permission DROP CONSTRAINT FK_E82D717453C59D72');
        $this->addSql('DROP TABLE t_responsable_rh_organisation_permission');
        $this->addSql('ALTER TABLE p_placards ALTER is_active SET DEFAULT true');
    }
}

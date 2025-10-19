<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251014135718 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SEQUENCE t_reclamation_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE TABLE t_reclamation (id INT NOT NULL, employe_id INT NOT NULL, manager_id INT NOT NULL, traite_par_id INT DEFAULT NULL, type_reclamation VARCHAR(50) NOT NULL, commentaire TEXT NOT NULL, document_path VARCHAR(255) DEFAULT NULL, document_type VARCHAR(100) DEFAULT NULL, statut VARCHAR(50) NOT NULL, date_creation TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, date_traitement TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, reponse_rh TEXT DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_9E3140051B65292 ON t_reclamation (employe_id)');
        $this->addSql('CREATE INDEX IDX_9E314005783E3463 ON t_reclamation (manager_id)');
        $this->addSql('CREATE INDEX IDX_9E314005167FABE8 ON t_reclamation (traite_par_id)');
        $this->addSql('ALTER TABLE t_reclamation ADD CONSTRAINT FK_9E3140051B65292 FOREIGN KEY (employe_id) REFERENCES t_employe (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE t_reclamation ADD CONSTRAINT FK_9E314005783E3463 FOREIGN KEY (manager_id) REFERENCES t_employe (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE t_reclamation ADD CONSTRAINT FK_9E314005167FABE8 FOREIGN KEY (traite_par_id) REFERENCES t_employe (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('DROP SEQUENCE t_reclamation_id_seq CASCADE');
        $this->addSql('ALTER TABLE t_reclamation DROP CONSTRAINT FK_9E3140051B65292');
        $this->addSql('ALTER TABLE t_reclamation DROP CONSTRAINT FK_9E314005783E3463');
        $this->addSql('ALTER TABLE t_reclamation DROP CONSTRAINT FK_9E314005167FABE8');
        $this->addSql('DROP TABLE t_reclamation');
    }
}

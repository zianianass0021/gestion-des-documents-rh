<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251005172417 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SEQUENCE "documents_id_seq" INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE "dossiers_id_seq" INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE "employee_contrat_id_seq" INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE "employees_id_seq" INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE "nature_contrat_id_seq" INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE "placards_id_seq" INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE TABLE "documents" (id INT NOT NULL, dossier_id INT NOT NULL, reference VARCHAR(255) NOT NULL, filename VARCHAR(255) NOT NULL, file_type VARCHAR(50) NOT NULL, file_path VARCHAR(500) NOT NULL, uploaded_by VARCHAR(255) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_A2B07288AEA34913 ON "documents" (reference)');
        $this->addSql('CREATE INDEX IDX_A2B07288611C0C56 ON "documents" (dossier_id)');
        $this->addSql('COMMENT ON COLUMN "documents".created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE TABLE "dossiers" (id INT NOT NULL, employee_id INT NOT NULL, title VARCHAR(255) NOT NULL, description TEXT DEFAULT NULL, type VARCHAR(100) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_A38E22E48C03F15C ON "dossiers" (employee_id)');
        $this->addSql('COMMENT ON COLUMN "dossiers".created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE TABLE "employee_contrat" (id INT NOT NULL, employee_id INT NOT NULL, nature_contrat_id INT NOT NULL, start_date DATE NOT NULL, end_date DATE DEFAULT NULL, statut VARCHAR(50) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_E078CF9C8C03F15C ON "employee_contrat" (employee_id)');
        $this->addSql('CREATE INDEX IDX_E078CF9CF45DB588 ON "employee_contrat" (nature_contrat_id)');
        $this->addSql('COMMENT ON COLUMN "employee_contrat".created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE TABLE "employees" (id INT NOT NULL, first_name VARCHAR(255) NOT NULL, last_name VARCHAR(255) NOT NULL, email VARCHAR(255) NOT NULL, phone VARCHAR(20) DEFAULT NULL, hire_date DATE NOT NULL, position VARCHAR(255) NOT NULL, department VARCHAR(255) NOT NULL, roles JSON NOT NULL, password VARCHAR(255) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_BA82C300E7927C74 ON "employees" (email)');
        $this->addSql('COMMENT ON COLUMN "employees".created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE TABLE "nature_contrat" (id INT NOT NULL, libelle VARCHAR(255) NOT NULL, description TEXT DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('COMMENT ON COLUMN "nature_contrat".created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE TABLE "placards" (id INT NOT NULL, dossier_id INT NOT NULL, name VARCHAR(255) NOT NULL, location VARCHAR(255) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_842C1B77611C0C56 ON "placards" (dossier_id)');
        $this->addSql('COMMENT ON COLUMN "placards".created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE "documents" ADD CONSTRAINT FK_A2B07288611C0C56 FOREIGN KEY (dossier_id) REFERENCES "dossiers" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE "dossiers" ADD CONSTRAINT FK_A38E22E48C03F15C FOREIGN KEY (employee_id) REFERENCES "employees" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE "employee_contrat" ADD CONSTRAINT FK_E078CF9C8C03F15C FOREIGN KEY (employee_id) REFERENCES "employees" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE "employee_contrat" ADD CONSTRAINT FK_E078CF9CF45DB588 FOREIGN KEY (nature_contrat_id) REFERENCES "nature_contrat" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE "placards" ADD CONSTRAINT FK_842C1B77611C0C56 FOREIGN KEY (dossier_id) REFERENCES "dossiers" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('DROP SEQUENCE "documents_id_seq" CASCADE');
        $this->addSql('DROP SEQUENCE "dossiers_id_seq" CASCADE');
        $this->addSql('DROP SEQUENCE "employee_contrat_id_seq" CASCADE');
        $this->addSql('DROP SEQUENCE "employees_id_seq" CASCADE');
        $this->addSql('DROP SEQUENCE "nature_contrat_id_seq" CASCADE');
        $this->addSql('DROP SEQUENCE "placards_id_seq" CASCADE');
        $this->addSql('ALTER TABLE "documents" DROP CONSTRAINT FK_A2B07288611C0C56');
        $this->addSql('ALTER TABLE "dossiers" DROP CONSTRAINT FK_A38E22E48C03F15C');
        $this->addSql('ALTER TABLE "employee_contrat" DROP CONSTRAINT FK_E078CF9C8C03F15C');
        $this->addSql('ALTER TABLE "employee_contrat" DROP CONSTRAINT FK_E078CF9CF45DB588');
        $this->addSql('ALTER TABLE "placards" DROP CONSTRAINT FK_842C1B77611C0C56');
        $this->addSql('DROP TABLE "documents"');
        $this->addSql('DROP TABLE "dossiers"');
        $this->addSql('DROP TABLE "employee_contrat"');
        $this->addSql('DROP TABLE "employees"');
        $this->addSql('DROP TABLE "nature_contrat"');
        $this->addSql('DROP TABLE "placards"');
    }
}

<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251005191856 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP SEQUENCE employe_id_seq CASCADE');
        $this->addSql('CREATE SEQUENCE "demandes_id_seq" INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE TABLE "demandes" (id INT NOT NULL, employe_id INT NOT NULL, responsable_rh_id INT DEFAULT NULL, titre VARCHAR(255) NOT NULL, contenu TEXT NOT NULL, statut VARCHAR(50) NOT NULL, reponse TEXT DEFAULT NULL, date_creation TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, date_reponse TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_BD940CBB1B65292 ON "demandes" (employe_id)');
        $this->addSql('CREATE INDEX IDX_BD940CBB4333A21E ON "demandes" (responsable_rh_id)');
        $this->addSql('COMMENT ON COLUMN "demandes".date_creation IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN "demandes".date_reponse IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE "demandes" ADD CONSTRAINT FK_BD940CBB1B65292 FOREIGN KEY (employe_id) REFERENCES "employees" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE "demandes" ADD CONSTRAINT FK_BD940CBB4333A21E FOREIGN KEY (responsable_rh_id) REFERENCES "employees" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('DROP TABLE employe');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('DROP SEQUENCE "demandes_id_seq" CASCADE');
        $this->addSql('CREATE SEQUENCE employe_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE TABLE employe (id INT NOT NULL, email VARCHAR(180) NOT NULL, roles JSON NOT NULL, password VARCHAR(255) NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_f804d3b9e7927c74 ON employe (email)');
        $this->addSql('ALTER TABLE "demandes" DROP CONSTRAINT FK_BD940CBB1B65292');
        $this->addSql('ALTER TABLE "demandes" DROP CONSTRAINT FK_BD940CBB4333A21E');
        $this->addSql('DROP TABLE "demandes"');
    }
}

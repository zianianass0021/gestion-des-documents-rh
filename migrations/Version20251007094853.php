<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251007094853 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP SEQUENCE placards_id_seq1 CASCADE');
        $this->addSql('CREATE SEQUENCE type_document_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE TABLE nature_contrat_type_document (nature_contrat_id INT NOT NULL, type_document_id INT NOT NULL, PRIMARY KEY(nature_contrat_id, type_document_id))');
        $this->addSql('CREATE INDEX IDX_8E96F513F45DB588 ON nature_contrat_type_document (nature_contrat_id)');
        $this->addSql('CREATE INDEX IDX_8E96F5138826AFA6 ON nature_contrat_type_document (type_document_id)');
        $this->addSql('CREATE TABLE type_document (id INT NOT NULL, nom VARCHAR(255) NOT NULL, description VARCHAR(500) DEFAULT NULL, obligatoire BOOLEAN NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('COMMENT ON COLUMN type_document.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE nature_contrat_type_document ADD CONSTRAINT FK_8E96F513F45DB588 FOREIGN KEY (nature_contrat_id) REFERENCES "nature_contrat" (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE nature_contrat_type_document ADD CONSTRAINT FK_8E96F5138826AFA6 FOREIGN KEY (type_document_id) REFERENCES type_document (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE documents ADD type_document_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE documents ADD CONSTRAINT FK_A2B072888826AFA6 FOREIGN KEY (type_document_id) REFERENCES type_document (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_A2B072888826AFA6 ON documents (type_document_id)');
        $this->addSql('ALTER TABLE placards ALTER id DROP DEFAULT');
        $this->addSql('ALTER TABLE placards ALTER created_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('COMMENT ON COLUMN placards.created_at IS \'(DC2Type:datetime_immutable)\'');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE "documents" DROP CONSTRAINT FK_A2B072888826AFA6');
        $this->addSql('DROP SEQUENCE type_document_id_seq CASCADE');
        $this->addSql('CREATE SEQUENCE placards_id_seq1 INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('ALTER TABLE nature_contrat_type_document DROP CONSTRAINT FK_8E96F513F45DB588');
        $this->addSql('ALTER TABLE nature_contrat_type_document DROP CONSTRAINT FK_8E96F5138826AFA6');
        $this->addSql('DROP TABLE nature_contrat_type_document');
        $this->addSql('DROP TABLE type_document');
        $this->addSql('CREATE SEQUENCE placards_id_seq');
        $this->addSql('SELECT setval(\'placards_id_seq\', (SELECT MAX(id) FROM "placards"))');
        $this->addSql('ALTER TABLE "placards" ALTER id SET DEFAULT nextval(\'placards_id_seq\')');
        $this->addSql('ALTER TABLE "placards" ALTER created_at TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('COMMENT ON COLUMN "placards".created_at IS NULL');
        $this->addSql('DROP INDEX IDX_A2B072888826AFA6');
        $this->addSql('ALTER TABLE "documents" DROP type_document_id');
    }
}

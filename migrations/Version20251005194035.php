<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251005194035 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE dossiers ADD placard_id INT NOT NULL');
        $this->addSql('ALTER TABLE dossiers ADD CONSTRAINT FK_A38E22E464757A25 FOREIGN KEY (placard_id) REFERENCES "placards" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_A38E22E464757A25 ON dossiers (placard_id)');
        $this->addSql('ALTER TABLE placards DROP CONSTRAINT fk_842c1b77611c0c56');
        $this->addSql('DROP INDEX idx_842c1b77611c0c56');
        $this->addSql('ALTER TABLE placards DROP dossier_id');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE "dossiers" DROP CONSTRAINT FK_A38E22E464757A25');
        $this->addSql('DROP INDEX IDX_A38E22E464757A25');
        $this->addSql('ALTER TABLE "dossiers" DROP placard_id');
        $this->addSql('ALTER TABLE "placards" ADD dossier_id INT NOT NULL');
        $this->addSql('ALTER TABLE "placards" ADD CONSTRAINT fk_842c1b77611c0c56 FOREIGN KEY (dossier_id) REFERENCES dossiers (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX idx_842c1b77611c0c56 ON "placards" (dossier_id)');
    }
}

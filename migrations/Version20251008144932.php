<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251008144932 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE dossier ADD placard_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE dossier DROP placard');
        $this->addSql('ALTER TABLE dossier ADD CONSTRAINT FK_3D48E03764757A25 FOREIGN KEY (placard_id) REFERENCES "placards" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_3D48E03764757A25 ON dossier (placard_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE dossier DROP CONSTRAINT FK_3D48E03764757A25');
        $this->addSql('DROP INDEX IDX_3D48E03764757A25');
        $this->addSql('ALTER TABLE dossier ADD placard VARCHAR(100) DEFAULT NULL');
        $this->addSql('ALTER TABLE dossier DROP placard_id');
    }
}

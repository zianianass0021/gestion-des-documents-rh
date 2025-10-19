<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251009160608 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE p_document ADD dossier_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE p_document ADD CONSTRAINT FK_5633656611C0C56 FOREIGN KEY (dossier_id) REFERENCES t_dossier (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_5633656611C0C56 ON p_document (dossier_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE p_document DROP CONSTRAINT FK_5633656611C0C56');
        $this->addSql('DROP INDEX IDX_5633656611C0C56');
        $this->addSql('ALTER TABLE p_document DROP dossier_id');
    }
}

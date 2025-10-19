<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251009154643 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE p_document ADD file_path VARCHAR(500) DEFAULT NULL');
        $this->addSql('ALTER TABLE p_document ADD file_type VARCHAR(100) DEFAULT NULL');
        $this->addSql('ALTER TABLE p_document ADD uploaded_by VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE p_document DROP file_path');
        $this->addSql('ALTER TABLE p_document DROP file_type');
        $this->addSql('ALTER TABLE p_document DROP uploaded_by');
    }
}

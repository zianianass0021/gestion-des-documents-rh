<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251122154344 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // Add is_active column with default value true
        $this->addSql('ALTER TABLE p_placards ADD is_active BOOLEAN DEFAULT true NOT NULL');
        // Update existing rows to be active by default (safety measure)
        $this->addSql('UPDATE p_placards SET is_active = true WHERE is_active IS NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE p_placards DROP is_active');
    }
}

<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251012155420 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // Add unique constraint to ensure one dossier per employee
        $this->addSql('ALTER TABLE t_dossier ADD CONSTRAINT unique_employee_dossier UNIQUE (employe_id)');
    }

    public function down(Schema $schema): void
    {
        // Remove unique constraint
        $this->addSql('ALTER TABLE t_dossier DROP CONSTRAINT unique_employee_dossier');
    }
}

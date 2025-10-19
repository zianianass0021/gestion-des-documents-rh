<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251012162543 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX idx_676458541b65292');
        $this->addSql('ALTER INDEX unique_employee_dossier RENAME TO UNIQ_676458541B65292');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('CREATE INDEX idx_676458541b65292 ON t_dossier (employe_id)');
        $this->addSql('ALTER INDEX uniq_676458541b65292 RENAME TO unique_employee_dossier');
    }
}

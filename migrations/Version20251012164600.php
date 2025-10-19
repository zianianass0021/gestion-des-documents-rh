<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251012164600 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE t_employe ADD username VARCHAR(50)');
        
        // Generate username from email for existing users
        $this->addSql("UPDATE t_employe SET username = LOWER(REGEXP_REPLACE(email, '@.*', '')) WHERE username IS NULL");
        
        // Make username NOT NULL after populating
        $this->addSql('ALTER TABLE t_employe ALTER COLUMN username SET NOT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_A2286BDAF85E0677 ON t_employe (username)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('DROP INDEX UNIQ_A2286BDAF85E0677');
        $this->addSql('ALTER TABLE t_employe DROP username');
    }
}

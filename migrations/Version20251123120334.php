<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251123120334 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // Ajouter la nouvelle colonne dossiers_geres
        $this->addSql('ALTER TABLE t_user ADD dossiers_geres JSON DEFAULT NULL');
        
        // Convertir les données existantes : dossier_gere -> dossiers_geres (array JSON)
        $this->addSql("
            UPDATE t_user 
            SET dossiers_geres = ('[\"' || dossier_gere || '\"]')::json
            WHERE dossier_gere IS NOT NULL AND dossier_gere != ''
        ");
        
        // Supprimer l'ancienne colonne dossier_gere
        $this->addSql('ALTER TABLE t_user DROP dossier_gere');
    }

    public function down(Schema $schema): void
    {
        // Ajouter l'ancienne colonne dossier_gere
        $this->addSql('ALTER TABLE t_user ADD dossier_gere VARCHAR(10) DEFAULT NULL');
        
        // Convertir les données : dossiers_geres (array JSON) -> dossier_gere (premier élément)
        $this->addSql("
            UPDATE t_user 
            SET dossier_gere = (dossiers_geres->>0)::varchar
            WHERE dossiers_geres IS NOT NULL AND json_array_length(dossiers_geres) > 0
        ");
        
        // Supprimer la nouvelle colonne dossiers_geres
        $this->addSql('ALTER TABLE t_user DROP dossiers_geres');
    }
}

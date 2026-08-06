<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * v9 — Nettoyage du schéma : suppression des colonnes éditables ou écrites mais jamais lues.
 *   - activity_category.nbr_max_activity / restrictions / sphere_completion_bonus
 *     (le bonus de complétion est piloté par BONUS_SPHERE_COMPLETION / BONUS_PATH_COMPLETION)
 *   - sphere.category_id : relation jamais alimentée ni lue
 */
final class Version20260727120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Nettoyage schéma : activity_category (nbr_max_activity, restrictions, sphere_completion_bonus) et sphere.category_id';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE activity_category DROP nbr_max_activity, DROP restrictions, DROP sphere_completion_bonus');
        $this->addSql('ALTER TABLE sphere DROP FOREIGN KEY FK_55F9668712469DE2');
        $this->addSql('DROP INDEX IDX_55F9668712469DE2 ON sphere');
        $this->addSql('ALTER TABLE sphere DROP category_id');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE activity_category ADD nbr_max_activity INT NOT NULL, ADD restrictions TEXT DEFAULT NULL, ADD sphere_completion_bonus INT UNSIGNED DEFAULT 30 NOT NULL');
        $this->addSql('ALTER TABLE sphere ADD category_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE sphere ADD CONSTRAINT FK_55F9668712469DE2 FOREIGN KEY (category_id) REFERENCES activity_category (id)');
        $this->addSql('CREATE INDEX IDX_55F9668712469DE2 ON sphere (category_id)');
    }
}

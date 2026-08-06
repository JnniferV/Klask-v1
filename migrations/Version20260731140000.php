<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * v12 — Bonus de complétion en 3 paliers au lieu d'un bonus par sphère.
 * Les valeurs configurées en back-office sont conservées : les deux anciennes clés
 * sont renommées (pas supprimées), la troisième est créée.
 */
final class Version20260731140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'app_parameter : BONUS_TOP3_SPHERES / BONUS_ALL_SPHERES / BONUS_MAX_SCORE';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("UPDATE app_parameter SET param_key = 'BONUS_TOP3_SPHERES', description = 'Bonus de points quand les 3 sphères préférées (étapes 1 à 3 du parcours) sont faites.' WHERE param_key = 'BONUS_SPHERE_COMPLETION'");
        $this->addSql("UPDATE app_parameter SET param_key = 'BONUS_ALL_SPHERES', description = 'Bonus de points quand les stands des 6 sphères sont faits.' WHERE param_key = 'BONUS_PATH_COMPLETION'");
        $this->addSql("INSERT INTO app_parameter (param_key, param_value, param_type, description, updated_at) SELECT 'BONUS_MAX_SCORE', '150', 'integer', 'Bonus de points du score maximal : toute la carte faite, ateliers et conférences compris.', NOW() FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM app_parameter p WHERE p.param_key = 'BONUS_MAX_SCORE')");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DELETE FROM app_parameter WHERE param_key = 'BONUS_MAX_SCORE'");
        $this->addSql("UPDATE app_parameter SET param_key = 'BONUS_PATH_COMPLETION', description = 'Bonus de points si le parcours est entièrement terminé.' WHERE param_key = 'BONUS_ALL_SPHERES'");
        $this->addSql("UPDATE app_parameter SET param_key = 'BONUS_SPHERE_COMPLETION', description = 'Bonus de points si une sphère est complétée hors parcours.' WHERE param_key = 'BONUS_TOP3_SPHERES'");
    }
}

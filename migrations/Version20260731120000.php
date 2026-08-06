<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * v11 — ON DELETE CASCADE sur les 3 tables filles de `user`.
 * Sans cela, supprimer un élève (back-office ou reset d'event) échouait sur une
 * contrainte FK dès qu'il avait répondu au questionnaire ou scanné un stand.
 */
final class Version20260731120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'FK scan/parcours/user_sphere_rating -> user : ON DELETE CASCADE';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE parcours DROP FOREIGN KEY FK_99B1DEE3A76ED395');
        $this->addSql('ALTER TABLE parcours ADD CONSTRAINT FK_99B1DEE3A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE scan DROP FOREIGN KEY FK_C4B3B3AEA76ED395');
        $this->addSql('ALTER TABLE scan ADD CONSTRAINT FK_C4B3B3AEA76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE user_sphere_rating DROP FOREIGN KEY FK_E5DF9976A76ED395');
        $this->addSql('ALTER TABLE user_sphere_rating ADD CONSTRAINT FK_E5DF9976A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE parcours DROP FOREIGN KEY FK_99B1DEE3A76ED395');
        $this->addSql('ALTER TABLE parcours ADD CONSTRAINT FK_99B1DEE3A76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE scan DROP FOREIGN KEY FK_C4B3B3AEA76ED395');
        $this->addSql('ALTER TABLE scan ADD CONSTRAINT FK_C4B3B3AEA76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE user_sphere_rating DROP FOREIGN KEY FK_E5DF9976A76ED395');
        $this->addSql('ALTER TABLE user_sphere_rating ADD CONSTRAINT FK_E5DF9976A76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
    }
}

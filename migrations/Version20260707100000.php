<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * v8 — Profession devient une catégorie d'activité (table profession supprimée) ;
 * notifications enrichies : titre, lien, type, couleur.
 */
final class Version20260707100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Suppression de la table profession (remplacée par la catégorie Profession) ; notification : title, link, type, color';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE activity DROP FOREIGN KEY FK_AC74095AFDEF8996');
        $this->addSql('DROP INDEX IDX_AC74095AFDEF8996 ON activity');
        $this->addSql('ALTER TABLE activity DROP profession_id');
        $this->addSql('DROP TABLE profession');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('CREATE TABLE profession (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(100) NOT NULL, description VARCHAR(500) DEFAULT NULL, code_rome VARCHAR(5) DEFAULT NULL, narrator VARCHAR(50) DEFAULT NULL, UNIQUE INDEX UNIQ_BA930D697899D1EA (code_rome), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 ENGINE = InnoDB');
        $this->addSql('ALTER TABLE activity ADD profession_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE activity ADD CONSTRAINT FK_AC74095AFDEF8996 FOREIGN KEY (profession_id) REFERENCES profession (id)');
        $this->addSql('CREATE INDEX IDX_AC74095AFDEF8996 ON activity (profession_id)');
    }
}

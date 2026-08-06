<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260519100450 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE user_preferred_sphere DROP FOREIGN KEY `FK_D18AA43475FD4EF9`');
        $this->addSql('ALTER TABLE user_preferred_sphere DROP FOREIGN KEY `FK_D18AA434A76ED395`');
        $this->addSql('DROP TABLE user_preferred_sphere');
        $this->addSql('ALTER TABLE activity DROP FOREIGN KEY `FK_AC74095AFDEF8996`');
        $this->addSql('DROP INDEX IDX_AC74095AFDEF8996 ON activity');
        $this->addSql('ALTER TABLE activity DROP profession_id');
        $this->addSql('ALTER TABLE user ADD poked_at DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE user_preferred_sphere (user_id INT NOT NULL, sphere_id INT NOT NULL, INDEX IDX_D18AA43475FD4EF9 (sphere_id), INDEX IDX_D18AA434A76ED395 (user_id), PRIMARY KEY (user_id, sphere_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_0900_ai_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('ALTER TABLE user_preferred_sphere ADD CONSTRAINT `FK_D18AA43475FD4EF9` FOREIGN KEY (sphere_id) REFERENCES sphere (id) ON UPDATE NO ACTION ON DELETE CASCADE');
        $this->addSql('ALTER TABLE user_preferred_sphere ADD CONSTRAINT `FK_D18AA434A76ED395` FOREIGN KEY (user_id) REFERENCES user (id) ON UPDATE NO ACTION ON DELETE CASCADE');
        $this->addSql('ALTER TABLE activity ADD profession_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE activity ADD CONSTRAINT `FK_AC74095AFDEF8996` FOREIGN KEY (profession_id) REFERENCES profession (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('CREATE INDEX IDX_AC74095AFDEF8996 ON activity (profession_id)');
        $this->addSql('ALTER TABLE user DROP poked_at');
    }
}

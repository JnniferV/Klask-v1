<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260514213455 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Activity: colonnes stand (is_available, estimated_wait_minutes, stand_updated_at) + table user_sphere_rating.';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE user_sphere_rating (rating INT NOT NULL, user_id INT NOT NULL, sphere_id INT NOT NULL, INDEX IDX_E5DF9976A76ED395 (user_id), INDEX IDX_E5DF997675FD4EF9 (sphere_id), PRIMARY KEY (user_id, sphere_id)) DEFAULT CHARACTER SET utf8mb4 ENGINE = InnoDB');
        $this->addSql('ALTER TABLE user_sphere_rating ADD CONSTRAINT FK_E5DF9976A76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE user_sphere_rating ADD CONSTRAINT FK_E5DF997675FD4EF9 FOREIGN KEY (sphere_id) REFERENCES sphere (id)');
        $this->addSql('ALTER TABLE activity ADD is_available TINYINT DEFAULT 1 NOT NULL, ADD estimated_wait_minutes INT DEFAULT NULL, ADD stand_updated_at DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE user_sphere_rating DROP FOREIGN KEY FK_E5DF9976A76ED395');
        $this->addSql('ALTER TABLE user_sphere_rating DROP FOREIGN KEY FK_E5DF997675FD4EF9');
        $this->addSql('DROP TABLE user_sphere_rating');
        $this->addSql('ALTER TABLE activity DROP is_available, DROP estimated_wait_minutes, DROP stand_updated_at');
    }
}

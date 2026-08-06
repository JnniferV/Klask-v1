<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260528192254 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE parcours (id INT AUTO_INCREMENT NOT NULL, priority INT DEFAULT 0 NOT NULL, recommended_at DATETIME NOT NULL, user_id INT NOT NULL, activity_id INT NOT NULL, INDEX IDX_99B1DEE3A76ED395 (user_id), INDEX IDX_99B1DEE381C06096 (activity_id), UNIQUE INDEX parcours_user_activity_uniq (user_id, activity_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 ENGINE = InnoDB');
        $this->addSql('ALTER TABLE parcours ADD CONSTRAINT FK_99B1DEE3A76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE parcours ADD CONSTRAINT FK_99B1DEE381C06096 FOREIGN KEY (activity_id) REFERENCES activity (id)');
        $this->addSql('ALTER TABLE activity_category ADD sphere_completion_bonus INT UNSIGNED DEFAULT 30 NOT NULL');
        $this->addSql('ALTER TABLE `group` ADD score INT DEFAULT NULL');
        $this->addSql('ALTER TABLE user ADD score INT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE parcours DROP FOREIGN KEY FK_99B1DEE3A76ED395');
        $this->addSql('ALTER TABLE parcours DROP FOREIGN KEY FK_99B1DEE381C06096');
        $this->addSql('DROP TABLE parcours');
        $this->addSql('ALTER TABLE activity_category DROP sphere_completion_bonus');
        $this->addSql('ALTER TABLE `group` DROP score');
        $this->addSql('ALTER TABLE user DROP score');
    }
}

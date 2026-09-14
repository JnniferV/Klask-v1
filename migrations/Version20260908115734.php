<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260908115734 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE activity (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(100) NOT NULL, description VARCHAR(500) DEFAULT NULL, qrcode VARCHAR(255) DEFAULT NULL, qrcode_token VARCHAR(255) DEFAULT NULL, point_x DOUBLE PRECISION DEFAULT NULL, point_y DOUBLE PRECISION DEFAULT NULL, soft_limit INT DEFAULT 0 NOT NULL, hard_limit INT DEFAULT 0 NOT NULL, is_internship TINYINT DEFAULT 0 NOT NULL, is_available TINYINT DEFAULT 1 NOT NULL, estimated_wait_minutes INT DEFAULT NULL, sphere_id INT DEFAULT NULL, category_id INT NOT NULL, UNIQUE INDEX UNIQ_AC74095A5E237E06 (name), UNIQUE INDEX UNIQ_AC74095A329C30C7 (qrcode_token), INDEX IDX_AC74095A75FD4EF9 (sphere_id), INDEX IDX_AC74095A12469DE2 (category_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 ENGINE = InnoDB');
        $this->addSql('CREATE TABLE activity_category (id INT AUTO_INCREMENT NOT NULL, type VARCHAR(50) NOT NULL, nbr_points INT UNSIGNED NOT NULL, beginning_hour_category DATETIME DEFAULT NULL, UNIQUE INDEX UNIQ_A646A9CF8CDE5729 (type), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 ENGINE = InnoDB');
        $this->addSql('CREATE TABLE app_parameter (id INT AUTO_INCREMENT NOT NULL, param_key VARCHAR(100) NOT NULL, param_value VARCHAR(500) NOT NULL, param_type VARCHAR(20) DEFAULT \'string\' NOT NULL, description VARCHAR(500) DEFAULT NULL, updated_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_82F8CE2535A9B410 (param_key), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 ENGINE = InnoDB');
        $this->addSql('CREATE TABLE authority (id INT AUTO_INCREMENT NOT NULL, authority_user VARCHAR(50) NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 ENGINE = InnoDB');
        $this->addSql('CREATE TABLE authority_role (authority_id INT NOT NULL, role_id INT NOT NULL, INDEX IDX_6390BF9A81EC865B (authority_id), INDEX IDX_6390BF9AD60322AC (role_id), PRIMARY KEY (authority_id, role_id)) DEFAULT CHARACTER SET utf8mb4 ENGINE = InnoDB');
        $this->addSql('CREATE TABLE establishment (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(100) NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 ENGINE = InnoDB');
        $this->addSql('CREATE TABLE event (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(50) NOT NULL, beginning_hour_event DATETIME DEFAULT NULL, end_hour_event DATETIME DEFAULT NULL, reset_at DATETIME DEFAULT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 ENGINE = InnoDB');
        $this->addSql('CREATE TABLE `group` (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(50) DEFAULT NULL, color VARCHAR(50) NOT NULL, code VARCHAR(10) NOT NULL, score INT DEFAULT NULL, establishment_id INT NOT NULL, event_id INT NOT NULL, UNIQUE INDEX UNIQ_6DC044C577153098 (code), INDEX IDX_6DC044C58565851 (establishment_id), INDEX IDX_6DC044C571F7E88B (event_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 ENGINE = InnoDB');
        $this->addSql('CREATE TABLE notification (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(100) DEFAULT \'\' NOT NULL, message VARCHAR(255) NOT NULL, link VARCHAR(255) DEFAULT NULL, type VARCHAR(20) DEFAULT \'info\' NOT NULL, recipient_type VARCHAR(20) NOT NULL, recipient_value VARCHAR(100) DEFAULT NULL, scheduled_at DATETIME DEFAULT NULL, sent_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 ENGINE = InnoDB');
        $this->addSql('CREATE TABLE parcours (id INT AUTO_INCREMENT NOT NULL, priority INT DEFAULT 0 NOT NULL, step_order INT DEFAULT 0 NOT NULL, recommended_at DATETIME NOT NULL, user_id INT NOT NULL, activity_id INT NOT NULL, INDEX IDX_99B1DEE3A76ED395 (user_id), INDEX IDX_99B1DEE381C06096 (activity_id), UNIQUE INDEX parcours_user_activity_uniq (user_id, activity_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 ENGINE = InnoDB');
        $this->addSql('CREATE TABLE reset_password_request (id INT AUTO_INCREMENT NOT NULL, selector VARCHAR(20) NOT NULL, hashed_token VARCHAR(100) NOT NULL, requested_at DATETIME NOT NULL, expires_at DATETIME NOT NULL, user_id INT NOT NULL, INDEX IDX_7CE748AA76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 ENGINE = InnoDB');
        $this->addSql('CREATE TABLE role (id INT AUTO_INCREMENT NOT NULL, name_role VARCHAR(50) NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 ENGINE = InnoDB');
        $this->addSql('CREATE TABLE scan (id INT AUTO_INCREMENT NOT NULL, hour_validation DATETIME NOT NULL, activity_id INT NOT NULL, user_id INT NOT NULL, INDEX IDX_C4B3B3AE81C06096 (activity_id), INDEX IDX_C4B3B3AEA76ED395 (user_id), UNIQUE INDEX scan_user_activity_uniq (user_id, activity_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 ENGINE = InnoDB');
        $this->addSql('CREATE TABLE sphere (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(100) NOT NULL, color VARCHAR(50) NOT NULL, point_x DOUBLE PRECISION DEFAULT NULL, point_y DOUBLE PRECISION DEFAULT NULL, radius DOUBLE PRECISION NOT NULL, UNIQUE INDEX UNIQ_55F966875E237E06 (name), UNIQUE INDEX UNIQ_55F96687665648E9 (color), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 ENGINE = InnoDB');
        $this->addSql('CREATE TABLE user (id INT AUTO_INCREMENT NOT NULL, pseudo VARCHAR(255) DEFAULT NULL, email VARCHAR(180) DEFAULT NULL, password VARCHAR(255) DEFAULT NULL, blocked_until DATETIME DEFAULT NULL, created_at DATETIME DEFAULT NULL, invalid_scan_count INT DEFAULT 0 NOT NULL, score INT DEFAULT NULL, authority_id INT NOT NULL, group_id INT DEFAULT NULL, UNIQUE INDEX UNIQ_8D93D64986CC499D (pseudo), UNIQUE INDEX UNIQ_8D93D649E7927C74 (email), INDEX IDX_8D93D64981EC865B (authority_id), INDEX IDX_8D93D649FE54D947 (group_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 ENGINE = InnoDB');
        $this->addSql('CREATE TABLE user_sphere_rating (rating INT NOT NULL, user_id INT NOT NULL, sphere_id INT NOT NULL, INDEX IDX_E5DF9976A76ED395 (user_id), INDEX IDX_E5DF997675FD4EF9 (sphere_id), PRIMARY KEY (user_id, sphere_id)) DEFAULT CHARACTER SET utf8mb4 ENGINE = InnoDB');
        $this->addSql('ALTER TABLE activity ADD CONSTRAINT FK_AC74095A75FD4EF9 FOREIGN KEY (sphere_id) REFERENCES sphere (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE activity ADD CONSTRAINT FK_AC74095A12469DE2 FOREIGN KEY (category_id) REFERENCES activity_category (id)');
        $this->addSql('ALTER TABLE authority_role ADD CONSTRAINT FK_6390BF9A81EC865B FOREIGN KEY (authority_id) REFERENCES authority (id)');
        $this->addSql('ALTER TABLE authority_role ADD CONSTRAINT FK_6390BF9AD60322AC FOREIGN KEY (role_id) REFERENCES role (id)');
        $this->addSql('ALTER TABLE `group` ADD CONSTRAINT FK_6DC044C58565851 FOREIGN KEY (establishment_id) REFERENCES establishment (id)');
        $this->addSql('ALTER TABLE `group` ADD CONSTRAINT FK_6DC044C571F7E88B FOREIGN KEY (event_id) REFERENCES event (id)');
        $this->addSql('ALTER TABLE parcours ADD CONSTRAINT FK_99B1DEE3A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE parcours ADD CONSTRAINT FK_99B1DEE381C06096 FOREIGN KEY (activity_id) REFERENCES activity (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE reset_password_request ADD CONSTRAINT FK_7CE748AA76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE scan ADD CONSTRAINT FK_C4B3B3AE81C06096 FOREIGN KEY (activity_id) REFERENCES activity (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE scan ADD CONSTRAINT FK_C4B3B3AEA76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE user ADD CONSTRAINT FK_8D93D64981EC865B FOREIGN KEY (authority_id) REFERENCES authority (id)');
        $this->addSql('ALTER TABLE user ADD CONSTRAINT FK_8D93D649FE54D947 FOREIGN KEY (group_id) REFERENCES `group` (id)');
        $this->addSql('ALTER TABLE user_sphere_rating ADD CONSTRAINT FK_E5DF9976A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE user_sphere_rating ADD CONSTRAINT FK_E5DF997675FD4EF9 FOREIGN KEY (sphere_id) REFERENCES sphere (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE activity DROP FOREIGN KEY FK_AC74095A75FD4EF9');
        $this->addSql('ALTER TABLE activity DROP FOREIGN KEY FK_AC74095A12469DE2');
        $this->addSql('ALTER TABLE authority_role DROP FOREIGN KEY FK_6390BF9A81EC865B');
        $this->addSql('ALTER TABLE authority_role DROP FOREIGN KEY FK_6390BF9AD60322AC');
        $this->addSql('ALTER TABLE `group` DROP FOREIGN KEY FK_6DC044C58565851');
        $this->addSql('ALTER TABLE `group` DROP FOREIGN KEY FK_6DC044C571F7E88B');
        $this->addSql('ALTER TABLE parcours DROP FOREIGN KEY FK_99B1DEE3A76ED395');
        $this->addSql('ALTER TABLE parcours DROP FOREIGN KEY FK_99B1DEE381C06096');
        $this->addSql('ALTER TABLE reset_password_request DROP FOREIGN KEY FK_7CE748AA76ED395');
        $this->addSql('ALTER TABLE scan DROP FOREIGN KEY FK_C4B3B3AE81C06096');
        $this->addSql('ALTER TABLE scan DROP FOREIGN KEY FK_C4B3B3AEA76ED395');
        $this->addSql('ALTER TABLE user DROP FOREIGN KEY FK_8D93D64981EC865B');
        $this->addSql('ALTER TABLE user DROP FOREIGN KEY FK_8D93D649FE54D947');
        $this->addSql('ALTER TABLE user_sphere_rating DROP FOREIGN KEY FK_E5DF9976A76ED395');
        $this->addSql('ALTER TABLE user_sphere_rating DROP FOREIGN KEY FK_E5DF997675FD4EF9');
        $this->addSql('DROP TABLE activity');
        $this->addSql('DROP TABLE activity_category');
        $this->addSql('DROP TABLE app_parameter');
        $this->addSql('DROP TABLE authority');
        $this->addSql('DROP TABLE authority_role');
        $this->addSql('DROP TABLE establishment');
        $this->addSql('DROP TABLE event');
        $this->addSql('DROP TABLE `group`');
        $this->addSql('DROP TABLE notification');
        $this->addSql('DROP TABLE parcours');
        $this->addSql('DROP TABLE reset_password_request');
        $this->addSql('DROP TABLE role');
        $this->addSql('DROP TABLE scan');
        $this->addSql('DROP TABLE sphere');
        $this->addSql('DROP TABLE user');
        $this->addSql('DROP TABLE user_sphere_rating');
    }
}

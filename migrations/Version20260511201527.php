<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260511201527 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE activity (id INT AUTO_INCREMENT NOT NULL, name_activity VARCHAR(100) NOT NULL, description_activity VARCHAR(500) DEFAULT NULL, qrcode_activity VARCHAR(255) DEFAULT NULL, point_xactivity DOUBLE PRECISION DEFAULT NULL, point_yactivity DOUBLE PRECISION DEFAULT NULL, sphere_id INT DEFAULT NULL, category_id INT NOT NULL, profession_id INT DEFAULT NULL, UNIQUE INDEX UNIQ_AC74095A80A5FC94 (name_activity), INDEX IDX_AC74095A75FD4EF9 (sphere_id), INDEX IDX_AC74095A12469DE2 (category_id), INDEX IDX_AC74095AFDEF8996 (profession_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 ENGINE = InnoDB');
        $this->addSql('CREATE TABLE activity_category (id INT AUTO_INCREMENT NOT NULL, type_category VARCHAR(191) NOT NULL, nbr_points INT NOT NULL, nbr_max_activity INT NOT NULL, beginning_hour_category DATETIME DEFAULT NULL, UNIQUE INDEX UNIQ_A646A9CFCBB33E3D (type_category), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 ENGINE = InnoDB');
        $this->addSql('CREATE TABLE authority (id INT AUTO_INCREMENT NOT NULL, authority_user VARCHAR(50) NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 ENGINE = InnoDB');
        $this->addSql('CREATE TABLE authority_role (authority_id INT NOT NULL, role_id INT NOT NULL, INDEX IDX_6390BF9A81EC865B (authority_id), INDEX IDX_6390BF9AD60322AC (role_id), PRIMARY KEY (authority_id, role_id)) DEFAULT CHARACTER SET utf8mb4 ENGINE = InnoDB');
        $this->addSql('CREATE TABLE establishment (id INT AUTO_INCREMENT NOT NULL, name_establishment VARCHAR(100) NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 ENGINE = InnoDB');
        $this->addSql('CREATE TABLE event (id INT AUTO_INCREMENT NOT NULL, name_event VARCHAR(50) NOT NULL, beginning_hour_event DATETIME DEFAULT NULL, end_hour_event DATETIME DEFAULT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 ENGINE = InnoDB');
        $this->addSql('CREATE TABLE `group` (id INT AUTO_INCREMENT NOT NULL, name_group VARCHAR(50) DEFAULT NULL, establishment_id INT NOT NULL, event_id INT NOT NULL, INDEX IDX_6DC044C58565851 (establishment_id), INDEX IDX_6DC044C571F7E88B (event_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 ENGINE = InnoDB');
        $this->addSql('CREATE TABLE profession (id INT AUTO_INCREMENT NOT NULL, description_profession VARCHAR(500) DEFAULT NULL, code_rom VARCHAR(5) NOT NULL, narrator_profession VARCHAR(50) DEFAULT NULL, UNIQUE INDEX UNIQ_BA930D699172380B (code_rom), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 ENGINE = InnoDB');
        $this->addSql('CREATE TABLE role (id INT AUTO_INCREMENT NOT NULL, name_role VARCHAR(50) NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 ENGINE = InnoDB');
        $this->addSql('CREATE TABLE scan (id INT AUTO_INCREMENT NOT NULL, hour_validation DATETIME NOT NULL, activity_id INT NOT NULL, user_id INT NOT NULL, INDEX IDX_C4B3B3AE81C06096 (activity_id), INDEX IDX_C4B3B3AEA76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 ENGINE = InnoDB');
        $this->addSql('CREATE TABLE sphere (id INT AUTO_INCREMENT NOT NULL, name_sphere VARCHAR(100) NOT NULL, color_sphere VARCHAR(50) NOT NULL, description_sphere LONGTEXT DEFAULT NULL, category_id INT DEFAULT NULL, UNIQUE INDEX UNIQ_55F966875381F5CA (name_sphere), UNIQUE INDEX UNIQ_55F9668770E77875 (color_sphere), INDEX IDX_55F9668712469DE2 (category_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 ENGINE = InnoDB');
        $this->addSql('CREATE TABLE user (id INT AUTO_INCREMENT NOT NULL, pseudo_user VARCHAR(191) NOT NULL, password VARCHAR(40) DEFAULT NULL, authority_id INT NOT NULL, group_id INT NOT NULL, UNIQUE INDEX UNIQ_8D93D649EA5002AD (pseudo_user), INDEX IDX_8D93D64981EC865B (authority_id), INDEX IDX_8D93D649FE54D947 (group_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 ENGINE = InnoDB');
        $this->addSql('CREATE TABLE messenger_messages (id BIGINT AUTO_INCREMENT NOT NULL, body LONGTEXT NOT NULL, headers LONGTEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL, available_at DATETIME NOT NULL, delivered_at DATETIME DEFAULT NULL, INDEX IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750 (queue_name, available_at, delivered_at, id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 ENGINE = InnoDB');
        $this->addSql('ALTER TABLE activity ADD CONSTRAINT FK_AC74095A75FD4EF9 FOREIGN KEY (sphere_id) REFERENCES sphere (id)');
        $this->addSql('ALTER TABLE activity ADD CONSTRAINT FK_AC74095A12469DE2 FOREIGN KEY (category_id) REFERENCES activity_category (id)');
        $this->addSql('ALTER TABLE activity ADD CONSTRAINT FK_AC74095AFDEF8996 FOREIGN KEY (profession_id) REFERENCES profession (id)');
        $this->addSql('ALTER TABLE authority_role ADD CONSTRAINT FK_6390BF9A81EC865B FOREIGN KEY (authority_id) REFERENCES authority (id)');
        $this->addSql('ALTER TABLE authority_role ADD CONSTRAINT FK_6390BF9AD60322AC FOREIGN KEY (role_id) REFERENCES role (id)');
        $this->addSql('ALTER TABLE `group` ADD CONSTRAINT FK_6DC044C58565851 FOREIGN KEY (establishment_id) REFERENCES establishment (id)');
        $this->addSql('ALTER TABLE `group` ADD CONSTRAINT FK_6DC044C571F7E88B FOREIGN KEY (event_id) REFERENCES event (id)');
        $this->addSql('ALTER TABLE scan ADD CONSTRAINT FK_C4B3B3AE81C06096 FOREIGN KEY (activity_id) REFERENCES activity (id)');
        $this->addSql('ALTER TABLE scan ADD CONSTRAINT FK_C4B3B3AEA76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE sphere ADD CONSTRAINT FK_55F9668712469DE2 FOREIGN KEY (category_id) REFERENCES activity_category (id)');
        $this->addSql('ALTER TABLE user ADD CONSTRAINT FK_8D93D64981EC865B FOREIGN KEY (authority_id) REFERENCES authority (id)');
        $this->addSql('ALTER TABLE user ADD CONSTRAINT FK_8D93D649FE54D947 FOREIGN KEY (group_id) REFERENCES `group` (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE activity DROP FOREIGN KEY FK_AC74095A75FD4EF9');
        $this->addSql('ALTER TABLE activity DROP FOREIGN KEY FK_AC74095A12469DE2');
        $this->addSql('ALTER TABLE activity DROP FOREIGN KEY FK_AC74095AFDEF8996');
        $this->addSql('ALTER TABLE authority_role DROP FOREIGN KEY FK_6390BF9A81EC865B');
        $this->addSql('ALTER TABLE authority_role DROP FOREIGN KEY FK_6390BF9AD60322AC');
        $this->addSql('ALTER TABLE `group` DROP FOREIGN KEY FK_6DC044C58565851');
        $this->addSql('ALTER TABLE `group` DROP FOREIGN KEY FK_6DC044C571F7E88B');
        $this->addSql('ALTER TABLE scan DROP FOREIGN KEY FK_C4B3B3AE81C06096');
        $this->addSql('ALTER TABLE scan DROP FOREIGN KEY FK_C4B3B3AEA76ED395');
        $this->addSql('ALTER TABLE sphere DROP FOREIGN KEY FK_55F9668712469DE2');
        $this->addSql('ALTER TABLE user DROP FOREIGN KEY FK_8D93D64981EC865B');
        $this->addSql('ALTER TABLE user DROP FOREIGN KEY FK_8D93D649FE54D947');
        $this->addSql('DROP TABLE activity');
        $this->addSql('DROP TABLE activity_category');
        $this->addSql('DROP TABLE authority');
        $this->addSql('DROP TABLE authority_role');
        $this->addSql('DROP TABLE establishment');
        $this->addSql('DROP TABLE event');
        $this->addSql('DROP TABLE `group`');
        $this->addSql('DROP TABLE profession');
        $this->addSql('DROP TABLE role');
        $this->addSql('DROP TABLE scan');
        $this->addSql('DROP TABLE sphere');
        $this->addSql('DROP TABLE user');
        $this->addSql('DROP TABLE messenger_messages');
    }
}

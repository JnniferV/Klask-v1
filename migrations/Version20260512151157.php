<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260512151157 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE app_parameter (id INT AUTO_INCREMENT NOT NULL, param_key VARCHAR(100) NOT NULL, param_value VARCHAR(500) NOT NULL, param_type VARCHAR(20) DEFAULT \'string\' NOT NULL, description VARCHAR(500) DEFAULT NULL, updated_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_82F8CE2535A9B410 (param_key), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 ENGINE = InnoDB');
        $this->addSql('CREATE TABLE user_preferred_sphere (user_id INT NOT NULL, sphere_id INT NOT NULL, INDEX IDX_D18AA434A76ED395 (user_id), INDEX IDX_D18AA43475FD4EF9 (sphere_id), PRIMARY KEY (user_id, sphere_id)) DEFAULT CHARACTER SET utf8mb4 ENGINE = InnoDB');
        $this->addSql('ALTER TABLE user_preferred_sphere ADD CONSTRAINT FK_D18AA434A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE user_preferred_sphere ADD CONSTRAINT FK_D18AA43475FD4EF9 FOREIGN KEY (sphere_id) REFERENCES sphere (id) ON DELETE CASCADE');
        $this->addSql('DROP INDEX UNIQ_AC74095A80A5FC94 ON activity');
        $this->addSql('ALTER TABLE activity ADD qrcode_token VARCHAR(255) DEFAULT NULL, ADD point_x DOUBLE PRECISION DEFAULT NULL, ADD point_y DOUBLE PRECISION DEFAULT NULL, ADD soft_limit INT DEFAULT 0 NOT NULL, ADD hard_limit INT DEFAULT 0 NOT NULL, ADD is_internship TINYINT DEFAULT 0 NOT NULL, DROP point_xactivity, DROP point_yactivity, CHANGE name_activity name VARCHAR(100) NOT NULL, CHANGE description_activity description VARCHAR(500) DEFAULT NULL, CHANGE qrcode_activity qrcode VARCHAR(255) DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_AC74095A5E237E06 ON activity (name)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_AC74095A329C30C7 ON activity (qrcode_token)');
        $this->addSql('DROP INDEX UNIQ_A646A9CFCBB33E3D ON activity_category');
        $this->addSql('ALTER TABLE activity_category ADD type VARCHAR(50) NOT NULL, ADD restrictions TEXT DEFAULT NULL, DROP type_category, CHANGE nbr_points nbr_points INT UNSIGNED NOT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_A646A9CF8CDE5729 ON activity_category (type)');
        $this->addSql('ALTER TABLE establishment CHANGE name_establishment name VARCHAR(100) NOT NULL');
        $this->addSql('ALTER TABLE event CHANGE name_event name VARCHAR(50) NOT NULL');
        $this->addSql('ALTER TABLE `group` ADD color VARCHAR(50) NOT NULL, ADD code VARCHAR(10) NOT NULL, CHANGE name_group name VARCHAR(50) DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_6DC044C577153098 ON `group` (code)');
        $this->addSql('DROP INDEX UNIQ_BA930D699172380B ON profession');
        $this->addSql('ALTER TABLE profession ADD name VARCHAR(100) NOT NULL, ADD code_rome VARCHAR(5) DEFAULT NULL, DROP code_rom, CHANGE description_profession description VARCHAR(500) DEFAULT NULL, CHANGE narrator_profession narrator VARCHAR(50) DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_BA930D697899D1EA ON profession (code_rome)');
        $this->addSql('DROP INDEX UNIQ_55F966875381F5CA ON sphere');
        $this->addSql('DROP INDEX UNIQ_55F9668770E77875 ON sphere');
        $this->addSql('ALTER TABLE sphere ADD icon VARCHAR(255) DEFAULT NULL, ADD point_x DOUBLE PRECISION DEFAULT NULL, ADD point_y DOUBLE PRECISION DEFAULT NULL, ADD radius DOUBLE PRECISION DEFAULT 10 NOT NULL, CHANGE name_sphere name VARCHAR(100) NOT NULL, CHANGE color_sphere color VARCHAR(50) NOT NULL, CHANGE description_sphere description LONGTEXT DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_55F966875E237E06 ON sphere (name)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_55F96687665648E9 ON sphere (color)');
        $this->addSql('DROP INDEX UNIQ_8D93D649EA5002AD ON user');
        $this->addSql('ALTER TABLE user ADD pseudo VARCHAR(255) DEFAULT NULL, ADD email VARCHAR(180) DEFAULT NULL, ADD blocked_until DATETIME DEFAULT NULL, ADD invalid_scan_count INT DEFAULT 0 NOT NULL, ADD group_code VARCHAR(10) DEFAULT NULL, DROP pseudo_user, CHANGE password password VARCHAR(255) DEFAULT NULL, CHANGE group_id group_id INT DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_8D93D64986CC499D ON user (pseudo)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_8D93D649E7927C74 ON user (email)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE user_preferred_sphere DROP FOREIGN KEY FK_D18AA434A76ED395');
        $this->addSql('ALTER TABLE user_preferred_sphere DROP FOREIGN KEY FK_D18AA43475FD4EF9');
        $this->addSql('DROP TABLE app_parameter');
        $this->addSql('DROP TABLE user_preferred_sphere');
        $this->addSql('DROP INDEX UNIQ_AC74095A5E237E06 ON activity');
        $this->addSql('DROP INDEX UNIQ_AC74095A329C30C7 ON activity');
        $this->addSql('ALTER TABLE activity ADD qrcode_activity VARCHAR(255) DEFAULT NULL, ADD point_xactivity DOUBLE PRECISION DEFAULT NULL, ADD point_yactivity DOUBLE PRECISION DEFAULT NULL, DROP qrcode, DROP qrcode_token, DROP point_x, DROP point_y, DROP soft_limit, DROP hard_limit, DROP is_internship, CHANGE name name_activity VARCHAR(100) NOT NULL, CHANGE description description_activity VARCHAR(500) DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_AC74095A80A5FC94 ON activity (name_activity)');
        $this->addSql('DROP INDEX UNIQ_A646A9CF8CDE5729 ON activity_category');
        $this->addSql('ALTER TABLE activity_category ADD type_category VARCHAR(191) NOT NULL, DROP type, DROP restrictions, CHANGE nbr_points nbr_points INT NOT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_A646A9CFCBB33E3D ON activity_category (type_category)');
        $this->addSql('ALTER TABLE establishment CHANGE name name_establishment VARCHAR(100) NOT NULL');
        $this->addSql('ALTER TABLE event CHANGE name name_event VARCHAR(50) NOT NULL');
        $this->addSql('DROP INDEX UNIQ_6DC044C577153098 ON `group`');
        $this->addSql('ALTER TABLE `group` DROP color, DROP code, CHANGE name name_group VARCHAR(50) DEFAULT NULL');
        $this->addSql('DROP INDEX UNIQ_BA930D697899D1EA ON profession');
        $this->addSql('ALTER TABLE profession ADD code_rom VARCHAR(5) NOT NULL, DROP name, DROP code_rome, CHANGE description description_profession VARCHAR(500) DEFAULT NULL, CHANGE narrator narrator_profession VARCHAR(50) DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_BA930D699172380B ON profession (code_rom)');
        $this->addSql('DROP INDEX UNIQ_55F966875E237E06 ON sphere');
        $this->addSql('DROP INDEX UNIQ_55F96687665648E9 ON sphere');
        $this->addSql('ALTER TABLE sphere DROP icon, DROP point_x, DROP point_y, DROP radius, CHANGE name name_sphere VARCHAR(100) NOT NULL, CHANGE color color_sphere VARCHAR(50) NOT NULL, CHANGE description description_sphere LONGTEXT DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_55F966875381F5CA ON sphere (name_sphere)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_55F9668770E77875 ON sphere (color_sphere)');
        $this->addSql('DROP INDEX UNIQ_8D93D64986CC499D ON user');
        $this->addSql('DROP INDEX UNIQ_8D93D649E7927C74 ON user');
        $this->addSql('ALTER TABLE user ADD pseudo_user VARCHAR(191) NOT NULL, DROP pseudo, DROP email, DROP blocked_until, DROP invalid_scan_count, DROP group_code, CHANGE password password VARCHAR(40) DEFAULT NULL, CHANGE group_id group_id INT NOT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_8D93D649EA5002AD ON user (pseudo_user)');
    }
}

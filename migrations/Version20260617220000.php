<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260617220000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajout event.reset_at + table notification';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE event ADD reset_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');

        $this->addSql('CREATE TABLE notification (
            id INT AUTO_INCREMENT NOT NULL,
            title VARCHAR(100) NOT NULL DEFAULT \'\',
            message VARCHAR(255) NOT NULL,
            link VARCHAR(255) DEFAULT NULL,
            type VARCHAR(20) NOT NULL DEFAULT \'info\',
            color VARCHAR(7) DEFAULT NULL,
            recipient_type VARCHAR(20) NOT NULL,
            recipient_value VARCHAR(100) DEFAULT NULL,
            scheduled_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            sent_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE event DROP reset_at');
        $this->addSql('DROP TABLE notification');
    }
}

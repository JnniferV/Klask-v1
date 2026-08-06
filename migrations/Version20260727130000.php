<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * v10 — Resynchronisation base / mapping (doctrine:schema:validate).
 *   - activity.stand_updated_at : colonne écrite à chaque édition admin, jamais lue → retirée du mapping.
 *   - messenger_messages : table créée à l'installation de symfony/doctrine-messenger, dépendance retirée depuis.
 *   - commentaires « (DC2Type:datetime_immutable) » : DBAL 4 ne les émet plus, ils créaient un écart permanent.
 */
final class Version20260727130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Resync schéma : DROP activity.stand_updated_at, DROP TABLE messenger_messages, retrait des commentaires DC2Type';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE activity DROP stand_updated_at');
        $this->addSql('DROP TABLE IF EXISTS messenger_messages');
        $this->addSql('ALTER TABLE event CHANGE reset_at reset_at DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE notification CHANGE scheduled_at scheduled_at DATETIME DEFAULT NULL, CHANGE sent_at sent_at DATETIME DEFAULT NULL, CHANGE created_at created_at DATETIME NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE activity ADD stand_updated_at DATETIME DEFAULT NULL');
        $this->addSql('CREATE TABLE messenger_messages (id BIGINT AUTO_INCREMENT NOT NULL, body LONGTEXT NOT NULL, headers LONGTEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL, available_at DATETIME NOT NULL, delivered_at DATETIME DEFAULT NULL, INDEX IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750 (queue_name, available_at, delivered_at, id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 ENGINE = InnoDB');
        $this->addSql('ALTER TABLE event CHANGE reset_at reset_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE notification CHANGE scheduled_at scheduled_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', CHANGE sent_at sent_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', CHANGE created_at created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
    }
}

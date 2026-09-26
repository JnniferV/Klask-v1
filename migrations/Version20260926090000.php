<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260926090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Halo allumé à la main sur les ateliers et conférences.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE activity ADD is_highlighted TINYINT DEFAULT 0 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE activity DROP is_highlighted');
    }
}

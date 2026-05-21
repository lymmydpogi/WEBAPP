<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260502133000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add first_name and last_name columns to user profile.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE user ADD first_name VARCHAR(100) DEFAULT NULL, ADD last_name VARCHAR(100) DEFAULT NULL");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("ALTER TABLE user DROP first_name, DROP last_name");
    }
}

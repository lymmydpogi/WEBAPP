<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Migrations\MigrationHelper;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260530120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add user.last_mobile_login_at for admin live login alerts';
    }

    public function up(Schema $schema): void
    {
        $conn = $this->connection;

        if (MigrationHelper::columnExists($conn, 'user', 'last_mobile_login_at')) {
            return;
        }

        $this->addSql('ALTER TABLE user ADD last_mobile_login_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
    }

    public function down(Schema $schema): void
    {
        $conn = $this->connection;

        if (!MigrationHelper::columnExists($conn, 'user', 'last_mobile_login_at')) {
            return;
        }

        $this->addSql('ALTER TABLE user DROP last_mobile_login_at');
    }
}

<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Migrations\MigrationHelper;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260521120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add order quantity; normalize Cancelled status spelling';
    }

    public function up(Schema $schema): void
    {
        $conn = $this->connection;

        if (MigrationHelper::tableExists($conn, 'order') && !MigrationHelper::columnExists($conn, 'order', 'quantity')) {
            $conn->executeStatement('ALTER TABLE `order` ADD quantity INT NOT NULL DEFAULT 1');
        }

        if (MigrationHelper::tableExists($conn, 'order')) {
            $conn->executeStatement("UPDATE `order` SET status = 'Cancelled' WHERE status IN ('Canceled', 'Cancel')");
        }
    }

    public function down(Schema $schema): void
    {
        $conn = $this->connection;

        if (MigrationHelper::tableExists($conn, 'order') && MigrationHelper::columnExists($conn, 'order', 'quantity')) {
            $conn->executeStatement('ALTER TABLE `order` DROP quantity');
        }

        if (MigrationHelper::tableExists($conn, 'order')) {
            $conn->executeStatement("UPDATE `order` SET status = 'Canceled' WHERE status = 'Cancelled'");
        }
    }
}

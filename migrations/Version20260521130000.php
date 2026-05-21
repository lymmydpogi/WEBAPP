<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Migrations\MigrationHelper;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260521130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Sync services.is_active with services.status';
    }

    public function up(Schema $schema): void
    {
        if (!MigrationHelper::tableExists($this->connection, 'services')) {
            return;
        }

        $this->connection->executeStatement(
            "UPDATE services SET is_active = IF(status = 'active', 1, 0)"
        );
    }

    public function down(Schema $schema): void
    {
    }
}

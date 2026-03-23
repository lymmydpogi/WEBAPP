<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Migrations\MigrationHelper;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260320000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add email verification fields to user (idempotent)';
    }

    public function up(Schema $schema): void
    {
        $conn = $this->connection;

        $hasVerified = MigrationHelper::columnExists($conn, 'user', 'is_verified');
        $hasToken = MigrationHelper::columnExists($conn, 'user', 'verification_token');

        if (!$hasVerified && !$hasToken) {
            $conn->executeStatement('ALTER TABLE user ADD is_verified TINYINT(1) NOT NULL DEFAULT 0, ADD verification_token VARCHAR(64) DEFAULT NULL');
        } elseif (!$hasVerified) {
            $conn->executeStatement('ALTER TABLE user ADD is_verified TINYINT(1) NOT NULL DEFAULT 0');
        } elseif (!$hasToken) {
            $conn->executeStatement('ALTER TABLE user ADD verification_token VARCHAR(64) DEFAULT NULL');
        }
    }

    public function down(Schema $schema): void
    {
        $conn = $this->connection;
        if (MigrationHelper::columnExists($conn, 'user', 'verification_token')) {
            $conn->executeStatement('ALTER TABLE user DROP verification_token');
        }
        if (MigrationHelper::columnExists($conn, 'user', 'is_verified')) {
            $conn->executeStatement('ALTER TABLE user DROP is_verified');
        }
    }
}

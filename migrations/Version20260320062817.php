<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Migrations\MigrationHelper;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260320062817 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'client_contact_message + services/user alignment (idempotent)';
    }

    public function up(Schema $schema): void
    {
        $conn = $this->connection;

        $conn->executeStatement(<<<'SQL'
CREATE TABLE IF NOT EXISTS client_contact_message (
    id INT AUTO_INCREMENT NOT NULL,
    name VARCHAR(150) NOT NULL,
    email VARCHAR(180) NOT NULL,
    subject VARCHAR(200) NOT NULL,
    message LONGTEXT NOT NULL,
    created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
    PRIMARY KEY(id)
) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
SQL);

        if ($this->servicesNeedsSchemaUpgrade($conn)) {
            MigrationHelper::runAlterPreservingForeignKeyOnColumn(
                $conn,
                'services',
                'created_by_id',
                'ALTER TABLE services CHANGE created_by_id created_by_id INT NOT NULL, CHANGE price price NUMERIC(10, 2) NOT NULL, CHANGE status status VARCHAR(20) NOT NULL, CHANGE delivery_time delivery_time INT NOT NULL, CHANGE category category VARCHAR(100) NOT NULL, CHANGE tools_used tools_used VARCHAR(255) DEFAULT NULL, CHANGE revision_limit revision_limit VARCHAR(50) DEFAULT NULL, CHANGE is_active is_active TINYINT(1) DEFAULT 1 NOT NULL, CHANGE pricing_unit pricing_unit VARCHAR(50) NOT NULL'
            );
        }

        $hasVerified = MigrationHelper::columnExists($conn, 'user', 'is_verified');
        $hasToken = MigrationHelper::columnExists($conn, 'user', 'verification_token');

        if (!$hasVerified && !$hasToken) {
            $conn->executeStatement('ALTER TABLE user ADD is_verified TINYINT(1) NOT NULL DEFAULT 0, ADD verification_token VARCHAR(64) DEFAULT NULL, CHANGE phone phone VARCHAR(25) DEFAULT NULL');
        } else {
            if (!$hasVerified) {
                $conn->executeStatement('ALTER TABLE user ADD is_verified TINYINT(1) NOT NULL DEFAULT 0');
            }
            if (!$hasToken) {
                $conn->executeStatement('ALTER TABLE user ADD verification_token VARCHAR(64) DEFAULT NULL');
            }
            if (MigrationHelper::columnExists($conn, 'user', 'phone')) {
                $phoneType = $conn->fetchOne(
                    "SELECT COLUMN_TYPE FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'user' AND column_name = 'phone'"
                );
                if ($phoneType && stripos((string) $phoneType, 'varchar(25)') === false) {
                    $conn->executeStatement('ALTER TABLE user CHANGE phone phone VARCHAR(25) DEFAULT NULL');
                }
            }
        }

        if (MigrationHelper::indexExists($conn, 'user', 'uniq_identifier_email')) {
            $conn->executeStatement('ALTER TABLE user RENAME INDEX uniq_identifier_email TO UNIQ_8D93D649E7927C74');
        } elseif (MigrationHelper::indexExists($conn, 'user', 'UNIQ_IDENTIFIER_EMAIL')) {
            $conn->executeStatement('ALTER TABLE user RENAME INDEX UNIQ_IDENTIFIER_EMAIL TO UNIQ_8D93D649E7927C74');
        }
    }

    private function servicesNeedsSchemaUpgrade(\Doctrine\DBAL\Connection $conn): bool
    {
        if (!MigrationHelper::tableExists($conn, 'services')) {
            return false;
        }
        $type = $conn->fetchOne(
            "SELECT COLUMN_TYPE FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'services' AND column_name = 'price'"
        );
        if ($type === false || $type === null) {
            return false;
        }

        return stripos((string) $type, 'decimal') === false;
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversible();
    }

    private function throwIrreversible(): void
    {
        throw new \RuntimeException('This migration is not safely reversible on a drifted database.');
    }
}

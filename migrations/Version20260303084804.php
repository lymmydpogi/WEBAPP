<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Migrations\MigrationHelper;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Activity log rename + order/service schema (idempotent for partially migrated DBs).
 */
final class Version20260303084804 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'activity_logs + order schema (safe if tables already exist)';
    }

    public function up(Schema $schema): void
    {
        $conn = $this->connection;

        if (!MigrationHelper::tableExists($conn, 'activity_logs')) {
            $conn->executeStatement('CREATE TABLE activity_logs (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, role VARCHAR(50) NOT NULL, action VARCHAR(50) NOT NULL, action_details VARCHAR(255) DEFAULT NULL, target_entity VARCHAR(100) DEFAULT NULL, target_entity_id INT DEFAULT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', description LONGTEXT DEFAULT NULL, INDEX IDX_F34B1DCEA76ED395 (user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        }
        if (!MigrationHelper::foreignKeyExists($conn, 'activity_logs', 'FK_F34B1DCEA76ED395')) {
            $conn->executeStatement('ALTER TABLE activity_logs ADD CONSTRAINT FK_F34B1DCEA76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        }

        MigrationHelper::dropForeignKeyIfExists($conn, 'activity_log', 'FK_FD06F647A76ED395');
        if (MigrationHelper::tableExists($conn, 'activity_log')) {
            $conn->executeStatement('DROP TABLE activity_log');
        }

        MigrationHelper::dropForeignKeyIfExists($conn, 'order_services', 'FK_92CA03D88D9F6D38');
        MigrationHelper::dropForeignKeyIfExists($conn, 'order_services', 'FK_92CA03D8AEF5A6C1');
        if (MigrationHelper::tableExists($conn, 'order_services')) {
            $conn->executeStatement('DROP TABLE order_services');
        }

        if (!MigrationHelper::columnExists($conn, 'order', 'service_id')) {
            $conn->executeStatement('ALTER TABLE `order` ADD service_id INT DEFAULT NULL, ADD payment_method VARCHAR(50) NOT NULL, ADD payment_status VARCHAR(50) NOT NULL, CHANGE created_by_id created_by_id INT NOT NULL');
        }
        if (!MigrationHelper::foreignKeyExists($conn, 'order', 'FK_F5299398ED5CA9E6') && MigrationHelper::columnExists($conn, 'order', 'service_id')) {
            $conn->executeStatement('ALTER TABLE `order` ADD CONSTRAINT FK_F5299398ED5CA9E6 FOREIGN KEY (service_id) REFERENCES services (id)');
        }
        if (!MigrationHelper::indexExists($conn, 'order', 'IDX_F5299398ED5CA9E6') && MigrationHelper::columnExists($conn, 'order', 'service_id')) {
            $conn->executeStatement('CREATE INDEX IDX_F5299398ED5CA9E6 ON `order` (service_id)');
        }

        if ($this->servicesNeedsSchemaUpgrade($conn)) {
            MigrationHelper::runAlterPreservingForeignKeyOnColumn(
                $conn,
                'services',
                'created_by_id',
                'ALTER TABLE services CHANGE created_by_id created_by_id INT NOT NULL, CHANGE price price NUMERIC(10, 2) NOT NULL, CHANGE status status VARCHAR(20) NOT NULL, CHANGE delivery_time delivery_time INT NOT NULL, CHANGE category category VARCHAR(100) NOT NULL, CHANGE tools_used tools_used VARCHAR(255) DEFAULT NULL, CHANGE revision_limit revision_limit VARCHAR(50) DEFAULT NULL, CHANGE is_active is_active TINYINT(1) DEFAULT 1 NOT NULL, CHANGE pricing_unit pricing_unit VARCHAR(50) NOT NULL'
            );
        }

        if ($this->userPhoneNeedsVarchar25($conn)) {
            $conn->executeStatement('ALTER TABLE user CHANGE phone phone VARCHAR(25) DEFAULT NULL');
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

    private function userPhoneNeedsVarchar25(\Doctrine\DBAL\Connection $conn): bool
    {
        if (!MigrationHelper::tableExists($conn, 'user')) {
            return false;
        }
        $type = $conn->fetchOne(
            "SELECT COLUMN_TYPE FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'user' AND column_name = 'phone'"
        );
        if ($type === false || $type === null) {
            return false;
        }

        return stripos((string) $type, 'varchar(25)') === false && stripos((string) $type, 'varchar(20)') !== false;
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

<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Migrations\MigrationHelper;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260301000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Bootstrap core tables (user/services/order) when missing.';
    }

    public function up(Schema $schema): void
    {
        $conn = $this->connection;

        if (!MigrationHelper::tableExists($conn, 'user')) {
            $conn->executeStatement(<<<'SQL'
CREATE TABLE user (
    id INT AUTO_INCREMENT NOT NULL,
    email VARCHAR(180) NOT NULL,
    roles JSON NOT NULL,
    password VARCHAR(255) NOT NULL,
    name VARCHAR(255) DEFAULT NULL,
    avatar VARCHAR(255) DEFAULT NULL,
    phone VARCHAR(20) DEFAULT NULL,
    address VARCHAR(255) DEFAULT NULL,
    status VARCHAR(20) DEFAULT 'active' NOT NULL,
    created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
    is_verified TINYINT(1) DEFAULT 0 NOT NULL,
    verification_token VARCHAR(64) DEFAULT NULL,
    UNIQUE INDEX uniq_identifier_email (email),
    PRIMARY KEY(id)
) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
SQL);
        }

        if (!MigrationHelper::tableExists($conn, 'services')) {
            $conn->executeStatement(<<<'SQL'
CREATE TABLE services (
    id INT AUTO_INCREMENT NOT NULL,
    created_by_id INT DEFAULT NULL,
    name VARCHAR(255) NOT NULL,
    description LONGTEXT NOT NULL,
    price DOUBLE PRECISION NOT NULL,
    status VARCHAR(255) NOT NULL,
    pricing_model VARCHAR(50) NOT NULL,
    pricing_unit VARCHAR(255) DEFAULT NULL,
    delivery_time DOUBLE PRECISION NOT NULL,
    category VARCHAR(255) NOT NULL,
    tools_used VARCHAR(255) NOT NULL,
    revision_limit LONGTEXT NOT NULL,
    is_active TINYINT(1) NOT NULL,
    INDEX IDX_7332E169B03A8386 (created_by_id),
    PRIMARY KEY(id)
) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
SQL);
        }

        if (!MigrationHelper::tableExists($conn, 'order')) {
            $conn->executeStatement(<<<'SQL'
CREATE TABLE `order` (
    id INT AUTO_INCREMENT NOT NULL,
    user_id INT NOT NULL,
    created_by_id INT DEFAULT NULL,
    client_name VARCHAR(255) NOT NULL,
    client_email VARCHAR(255) NOT NULL,
    order_date DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
    status VARCHAR(50) NOT NULL,
    total_price DOUBLE PRECISION DEFAULT NULL,
    notes LONGTEXT DEFAULT NULL,
    delivery_date DATETIME DEFAULT NULL,
    INDEX IDX_F5299398A76ED395 (user_id),
    INDEX IDX_F5299398B03A8386 (created_by_id),
    PRIMARY KEY(id)
) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
SQL);
        }

        if (!MigrationHelper::foreignKeyExists($conn, 'services', 'FK_7332E169B03A8386')) {
            $conn->executeStatement('ALTER TABLE services ADD CONSTRAINT FK_7332E169B03A8386 FOREIGN KEY (created_by_id) REFERENCES user (id)');
        }
        if (!MigrationHelper::foreignKeyExists($conn, 'order', 'FK_F5299398A76ED395')) {
            $conn->executeStatement('ALTER TABLE `order` ADD CONSTRAINT FK_F5299398A76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        }
        if (!MigrationHelper::foreignKeyExists($conn, 'order', 'FK_F5299398B03A8386')) {
            $conn->executeStatement('ALTER TABLE `order` ADD CONSTRAINT FK_F5299398B03A8386 FOREIGN KEY (created_by_id) REFERENCES user (id)');
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `order` DROP FOREIGN KEY FK_F5299398B03A8386');
        $this->addSql('ALTER TABLE `order` DROP FOREIGN KEY FK_F5299398A76ED395');
        $this->addSql('ALTER TABLE services DROP FOREIGN KEY FK_7332E169B03A8386');
        $this->addSql('DROP TABLE IF EXISTS `order`');
        $this->addSql('DROP TABLE IF EXISTS services');
        $this->addSql('DROP TABLE IF EXISTS user');
    }
}

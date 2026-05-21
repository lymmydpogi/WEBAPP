<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Migrations\MigrationHelper;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260521140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create messages table for client-admin chat';
    }

    public function up(Schema $schema): void
    {
        $conn = $this->connection;

        if (MigrationHelper::tableExists($conn, 'messages')) {
            return;
        }

        $conn->executeStatement(<<<'SQL'
CREATE TABLE messages (
    id INT AUTO_INCREMENT NOT NULL,
    user_id INT NOT NULL,
    sender_type VARCHAR(20) NOT NULL,
    message LONGTEXT NOT NULL,
    is_read TINYINT(1) DEFAULT 0 NOT NULL,
    created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
    updated_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
    INDEX IDX_DB021E96A76ED395 (user_id),
    INDEX IDX_DB021E96_CREATED_AT (created_at),
    PRIMARY KEY(id)
) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
SQL);

        if (!MigrationHelper::foreignKeyExists($conn, 'messages', 'FK_MESSAGES_USER')) {
            $conn->executeStatement(
                'ALTER TABLE messages ADD CONSTRAINT FK_MESSAGES_USER FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE'
            );
        }
    }

    public function down(Schema $schema): void
    {
        $conn = $this->connection;

        if (!MigrationHelper::tableExists($conn, 'messages')) {
            return;
        }

        MigrationHelper::dropForeignKeyIfExists($conn, 'messages', 'FK_MESSAGES_USER');
        $conn->executeStatement('DROP TABLE messages');
    }
}

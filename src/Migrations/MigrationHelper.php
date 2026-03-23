<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Connection;

/**
 * Idempotent checks for MySQL migrations (partially applied DBs / dev drift).
 */
final class MigrationHelper
{
    public static function tableExists(Connection $connection, string $table): bool
    {
        $table = str_replace('`', '', $table);

        return (int) $connection->fetchOne(
            'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?',
            [$table]
        ) > 0;
    }

    public static function columnExists(Connection $connection, string $table, string $column): bool
    {
        $table = str_replace('`', '', $table);

        return (int) $connection->fetchOne(
            'SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?',
            [$table, $column]
        ) > 0;
    }

    public static function foreignKeyExists(Connection $connection, string $table, string $constraintName): bool
    {
        $table = str_replace('`', '', $table);

        return (int) $connection->fetchOne(
            'SELECT COUNT(*) FROM information_schema.table_constraints WHERE table_schema = DATABASE() AND table_name = ? AND constraint_name = ? AND constraint_type = ?',
            [$table, $constraintName, 'FOREIGN KEY']
        ) > 0;
    }

    public static function indexExists(Connection $connection, string $table, string $indexName): bool
    {
        $table = str_replace('`', '', $table);

        return (int) $connection->fetchOne(
            'SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ?',
            [$table, $indexName]
        ) > 0;
    }

    public static function dropForeignKeyIfExists(Connection $connection, string $table, string $constraintName): void
    {
        if (self::foreignKeyExists($connection, $table, $constraintName)) {
            $connection->executeStatement(sprintf(
                'ALTER TABLE `%s` DROP FOREIGN KEY `%s`',
                str_replace('`', '', $table),
                str_replace('`', '', $constraintName)
            ));
        }
    }

    /**
     * Foreign keys on a column block CHANGE on that column — drop, run ALTER, re-add.
     *
     * @return list<array{CONSTRAINT_NAME: string, REFERENCED_TABLE_NAME: string, REFERENCED_COLUMN_NAME: string}>
     */
    public static function getForeignKeysOnColumn(Connection $connection, string $table, string $column): array
    {
        $table = str_replace('`', '', $table);
        $rows = $connection->fetchAllAssociative(
            'SELECT DISTINCT CONSTRAINT_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME
             FROM information_schema.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = ?
               AND COLUMN_NAME = ?
               AND REFERENCED_TABLE_NAME IS NOT NULL',
            [$table, $column]
        );

        return $rows;
    }

    /**
     * Run a DDL statement that modifies $column (e.g. CHANGE) while preserving FKs on that column.
     */
    public static function runAlterPreservingForeignKeyOnColumn(
        Connection $connection,
        string $table,
        string $column,
        string $alterSql
    ): void {
        $table = str_replace('`', '', $table);
        $fks = self::getForeignKeysOnColumn($connection, $table, $column);

        foreach ($fks as $fk) {
            self::dropForeignKeyIfExists($connection, $table, $fk['CONSTRAINT_NAME']);
        }

        $connection->executeStatement($alterSql);

        foreach ($fks as $fk) {
            if (self::foreignKeyExists($connection, $table, $fk['CONSTRAINT_NAME'])) {
                continue;
            }

            $connection->executeStatement(sprintf(
                'ALTER TABLE `%s` ADD CONSTRAINT `%s` FOREIGN KEY (`%s`) REFERENCES `%s` (`%s`)',
                $table,
                str_replace('`', '', $fk['CONSTRAINT_NAME']),
                $column,
                str_replace('`', '', $fk['REFERENCED_TABLE_NAME']),
                str_replace('`', '', $fk['REFERENCED_COLUMN_NAME'])
            ));
        }
    }
}

<?php

declare(strict_types=1);

namespace JardisTools\DbSchema\Exporter\Dialect;

use JardisTools\DbSchema\Exporter\Ddl\DdlDialectInterface;

/**
 * SQLite SQL dialect implementation for DDL generation.
 */
class SqLiteDialect implements DdlDialectInterface
{
    public function typeMapping(string $dbType, ?int $length, ?int $precision, ?int $scale): string
    {
        $type = strtoupper($dbType);

        // SQLite has a simplified type system (type affinity)
        // https://www.sqlite.org/datatype3.html
        return match ($type) {
            // Integer affinity
            'INT', 'INTEGER', 'TINYINT', 'SMALLINT', 'MEDIUMINT', 'BIGINT',
            'UNSIGNED BIG INT', 'INT2', 'INT8', 'SERIAL' => 'INTEGER',

            // Text affinity
            'CHARACTER', 'VARCHAR', 'VARYING CHARACTER', 'NCHAR',
            'NATIVE CHARACTER', 'NVARCHAR', 'TEXT', 'CLOB' => 'TEXT',

            // Real affinity
            'REAL', 'DOUBLE', 'DOUBLE PRECISION', 'FLOAT', 'NUMERIC' => 'REAL',

            // Blob affinity
            'BLOB', 'BYTEA' => 'BLOB',

            // Special handling for DECIMAL (TEXT in SQLite for precision)
            'DECIMAL' => $precision !== null && $scale !== null
                ? "DECIMAL({$precision},{$scale})" : 'NUMERIC',

            // Date/Time (stored as TEXT in SQLite)
            'DATE', 'DATETIME', 'TIMESTAMP', 'TIME' => 'TEXT',

            // Boolean (stored as INTEGER 0/1 in SQLite)
            'BOOL', 'BOOLEAN' => 'INTEGER',

            // Default fallback
            default => $type,
        };
    }

    public function createTableStatement(string $tableName, array $columns, array $primaryKeys): string
    {
        $columnDefinitions = [];

        foreach ($columns as $column) {
            $def = '  "' . $column['name'] . '" ';
            $def .= $this->typeMapping(
                $column['type'],
                $column['length'],
                $column['precision'],
                $column['scale']
            );

            // SQLite requires PRIMARY KEY and AUTOINCREMENT on the same line as column definition
            // for single-column primary keys
            $isSinglePrimaryKey = count($primaryKeys) === 1 && $primaryKeys[0] === $column['name'];

            if ($isSinglePrimaryKey) {
                $def .= ' PRIMARY KEY';
                if ($column['auto_increment']) {
                    $def .= ' AUTOINCREMENT';
                }
            }

            if (!$column['nullable'] && !$isSinglePrimaryKey) {
                $def .= ' NOT NULL';
            }

            if ($column['default'] !== null && !$column['auto_increment']) {
                $def .= ' DEFAULT ' . $this->quoteDefault($column['default'], $column['type']);
            }

            $columnDefinitions[] = $def;
        }

        // Add composite primary key constraint if applicable
        if (count($primaryKeys) > 1) {
            $pkColumns = array_map(fn($col) => '"' . $col . '"', $primaryKeys);
            $columnDefinitions[] = '  PRIMARY KEY (' . implode(', ', $pkColumns) . ')';
        }

        $sql = 'CREATE TABLE "' . $tableName . '" (' . "\n";
        $sql .= implode(",\n", $columnDefinitions);
        $sql .= "\n);";

        return $sql;
    }

    public function createIndexStatement(string $tableName, array $index): string
    {
        // Skip PRIMARY indexes (handled in CREATE TABLE)
        if ($index['name'] === 'PRIMARY' || ($index['index_type'] ?? '') === 'primary') {
            return '';
        }

        $indexName = $index['name'];
        $columnName = $index['column_name'];
        $isUnique = $index['is_unique'] ?? false;

        $uniqueKeyword = $isUnique ? 'UNIQUE ' : '';

        return 'CREATE ' .
            $uniqueKeyword .
            'INDEX "' .
            $indexName .
            '" ON "' .
            $tableName .
            '" ("' . $columnName . '");';
    }

    public function createForeignKeyStatement(string $tableName, array $foreignKey): string
    {
        // SQLite does not support ALTER TABLE ... ADD CONSTRAINT for foreign keys.
        // Foreign key constraints must be defined inline within CREATE TABLE.
        // Returning an empty string signals the caller to skip this statement.
        return '';
    }

    public function dropTableStatement(string $tableName): string
    {
        return 'DROP TABLE IF EXISTS "' . $tableName . '";';
    }

    public function beginTransaction(): string
    {
        return 'BEGIN TRANSACTION;';
    }

    public function commitTransaction(): string
    {
        return 'COMMIT;';
    }

    /**
     * Quote a default value appropriately for SQLite DDL.
     *
     * SQL keywords (CURRENT_TIMESTAMP, CURRENT_DATE, CURRENT_TIME, NULL, TRUE, FALSE)
     * and numeric literals are emitted unquoted; string values are single-quoted.
     */
    private function quoteDefault(string $default, string $type): string
    {
        $upper = strtoupper($default);

        // SQL keywords / functions — never quote
        if (in_array($upper, ['CURRENT_TIMESTAMP', 'CURRENT_DATE', 'CURRENT_TIME', 'NULL', 'TRUE', 'FALSE'], true)) {
            return $default;
        }

        // Numeric literals — never quote
        if (is_numeric($default)) {
            return $default;
        }

        // String values — quote
        return "'" . str_replace("'", "''", $default) . "'";
    }
}

<?php

declare(strict_types=1);

namespace JardisTools\DbSchema\Reader;

use JardisSupport\Contract\DbSchema\DbSchemaReaderInterface;
use PDO;

/**
 * SQLite database schema reader.
 *
 * This class provides schema-level read operations for SQLite databases,
 * including reading information about tables, columns, indexes, foreign keys, and types.
 */
class SqLiteReader implements DbSchemaReaderInterface
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function tables(): ?array
    {
        $stmt = $this->pdo->query("
            SELECT 
                name,
                'BASE TABLE' AS type
            FROM sqlite_master 
            WHERE type = 'table' 
              AND name NOT LIKE 'sqlite_%'
            ORDER BY name
        ");

        $results = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];

        return $results ?: [];
    }

    /**
     * @param string $table
     * @param array<string>|null $fields
     * @return array<int, array<string, mixed>>|null
     */
    public function columns(string $table, ?array $fields = null): ?array
    {
        $cols = [];
        // SQLite doesn't support placeholders in PRAGMA statements
        $stmt = $this->pdo->query("PRAGMA table_info(" . $this->pdo->quote($table) . ")");

        $raw = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];

        foreach ($raw as $col) {
            $typeRaw = strtolower($col['type']);
            $baseType = preg_replace('/\(.*?\)/', '', $typeRaw);

            $length = null;
            if (preg_match('/\((\d+)\)/', $typeRaw, $m)) {
                $length = (int) $m[1];
            }

            $precision = null;
            $scale = null;
            if (preg_match('/\((\d+),\s*(\d+)\)/', $typeRaw, $m)) {
                $precision = (int) $m[1];
                $scale = (int) $m[2];
                $length = null;
            } elseif (preg_match('/\((\d+)\)/', $typeRaw, $m) && in_array($baseType, ['decimal', 'numeric'])) {
                $precision = (int) $m[1];
                $scale = 0;
                $length = null;
            }

            $autoIncrement = ($col['pk'] == 1 && $baseType === 'integer');

            $column = [
                'name' => $col['name'],
                'type' => $baseType,
                'length' => $length,
                'precision' => $precision,
                'scale' => $scale,
                'nullable' => !$col['notnull'], // SQLite returns integer, convert to bool
                'default' => $col['dflt_value'],
                'primary' => $col['pk'] == 1,  // SQLite returns integer, convert to bool
                'auto_increment' => $autoIncrement,
            ];

            $cols[] = $this->normalizeColumnTypes($column);
        }

        $results = $cols;

        if ($fields !== null) {
            $results = array_filter(
                $results,
                fn(array $row) => in_array($row['name'], $fields)
            );

            // Sort results in the order specified by $fields
            usort($results, function ($a, $b) use ($fields) {
                $posA = array_search($a['name'], $fields);
                $posB = array_search($b['name'], $fields);
                return $posA <=> $posB;
            });
        }

        return $results;
    }

    /**
     * Normalizes the types of column attributes to ensure consistent boolean values and handles specific
     * SQLite behavior for primary keys.
     *
     * @param array<string, mixed> $column An associative array representing a database column and its attributes.
     *                      Expected keys include:
     *                      - 'nullable': Whether the column allows null values.
     *                      - 'primary': Whether the column is a primary key.
     *                      - 'auto_increment': Whether the column is auto-incrementing.
     *
     * @return array<string, mixed> An associative array representing the normalized column attributes
     *               with consistent boolean types and updated values as necessary.
     */
    private function normalizeColumnTypes(array $column): array
    {
        // Ensure consistent boolean types (SQLite uses integers 0/1)
        $column['nullable'] = (bool) $column['nullable'];
        $column['primary'] = (bool) $column['primary'];
        $column['auto_increment'] = (bool) $column['auto_increment'];

        // Special handling for SQLite PRIMARY KEY columns
        // In SQLite, PRIMARY KEY columns are implicitly NOT NULL
        if ($column['primary']) {
            $column['nullable'] = false;
        }

        return $column;
    }

    public function indexes(string $table): ?array
    {
        $results = [];

        // SQLite does not expose the primary key via PRAGMA index_list,
        // so we synthesize a primary key index from PRAGMA table_info
        $pkColumns = $this->getPrimaryKeyColumns($table);
        if (!empty($pkColumns)) {
            $seq = 1;
            foreach ($pkColumns as $pkCol) {
                $results[] = [
                    'name' => "pk_{$table}",
                    'column_name' => $pkCol,
                    'is_unique' => true,
                    'type' => 'BTREE',
                    'sequence' => $seq++,
                    'sub_part' => null,
                    'nullable' => null,
                    'index_type' => 'primary',
                ];
            }
        }

        // SQLite doesn't support placeholders in PRAGMA statements
        $stmt = $this->pdo->query("PRAGMA index_list(" . $this->pdo->quote($table) . ")");

        $indexes = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];

        foreach ($indexes as $index) {
            // Get columns for each index
            $stmt = $this->pdo->query("PRAGMA index_info(" . $this->pdo->quote($index['name']) . ")");

            $columns = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];

            foreach ($columns as $column) {
                $results[] = [
                    'name' => $index['name'],
                    'column_name' => $column['name'],
                    'is_unique' => (bool) $index['unique'],
                    'type' => 'BTREE',
                    'sequence' => $column['seqno'] + 1,
                    'sub_part' => null,
                    'nullable' => null,
                    'index_type' => $this->getSqliteIndexType((bool) $index['unique'])
                ];
            }
        }

        return $results ?: [];
    }

    public function foreignKeys(string $table): ?array
    {
        // SQLite doesn't support placeholders in PRAGMA statements
        $stmt = $this->pdo->query("PRAGMA foreign_key_list(" . $this->pdo->quote($table) . ")");

        $fks = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
        $results = [];

        foreach ($fks as $fk) {
            $results[] = [
                'container' => $table,
                'constraintName' => "fk_{$table}_{$fk['from']}_{$fk['table']}",
                'constraintCol' => $fk['from'],
                'refContainer' => $fk['table'],
                'refColumn' => $fk['to'],
                'refSchema' => 'main', // SQLite default schema
                'onUpdate' => strtoupper($fk['on_update']),
                'onDelete' => strtoupper($fk['on_delete']),
                'sequence' => $fk['seq'] + 1 // SQLite starts at 0, we want 1-based
            ];
        }

        return $results ?: [];
    }

    public function fieldType(string $fieldType): ?string
    {
        // Remove size/precision info for clean mapping
        $cleanType = strtolower(trim($fieldType));
        $cleanType = preg_replace('/\([^)]*\)/', '', $cleanType);
        $cleanType = trim($cleanType ?? '');

        $types = [
            // Integer types (SQLite has flexible typing)
            'integer' => 'int',
            'int' => 'int',
            'tinyint' => 'int',
            'smallint' => 'int',
            'mediumint' => 'int',
            'bigint' => 'int',

            // Boolean types (stored as integer in SQLite)
            'boolean' => 'bool',
            'bool' => 'bool',

            // Float/Real types
            'real' => 'float',
            'double' => 'float',
            'float' => 'float',
            'decimal' => 'float',
            'numeric' => 'float',

            // Text types (SQLite's main string type)
            'text' => 'string',
            'varchar' => 'string',
            'char' => 'string',
            'character' => 'string',
            'nchar' => 'string',
            'nvarchar' => 'string',
            'clob' => 'string',

            // Date/Time types (stored as text/integer in SQLite)
            'date' => 'date',
            'datetime' => 'datetime',
            'timestamp' => 'datetime',
            'time' => 'time',

            // Binary types
            'blob' => 'string',
            'binary' => 'string',
            'varbinary' => 'string',

            // JSON (stored as text in SQLite)
            'json' => 'string',
        ];

        return $types[$cleanType] ?? null;
    }

    /**
     * Helper method to determine SQLite index type
     */
    private function getSqliteIndexType(bool $isUnique): string
    {
        return $isUnique ? 'unique' : 'index';
    }

    /**
     * Get primary key column names from PRAGMA table_info.
     *
     * @return array<int, string>
     */
    private function getPrimaryKeyColumns(string $table): array
    {
        $stmt = $this->pdo->query("PRAGMA table_info(" . $this->pdo->quote($table) . ")");
        $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];

        $pkColumns = [];
        foreach ($rows as $row) {
            if ($row['pk'] > 0) {
                $pkColumns[(int) $row['pk']] = $row['name'];
            }
        }

        ksort($pkColumns);
        return array_values($pkColumns);
    }

    public function getDriverName(): string
    {
        return 'sqlite';
    }
}

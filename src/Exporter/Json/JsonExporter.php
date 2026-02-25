<?php

declare(strict_types=1);

namespace JardisTools\DbSchema\Exporter\Json;

use JardisPort\DbSchema\DbSchemaReaderInterface;

/**
 * Exports database schema metadata as JSON.
 *
 * This exporter creates a complete JSON representation of database schemas,
 * including tables, columns, indexes, and foreign key constraints.
 */
readonly class JsonExporter
{
    public function __construct(
        private DbSchemaReaderInterface $schema
    ) {
    }

    /**
     * Generates JSON representation of database schema.
     *
     * @param array<int, string> $tables List of table names to export
     * @param bool $prettyPrint Whether to format JSON with indentation (default: false)
     * @return string JSON string
     */
    public function generate(array $tables, bool $prettyPrint = false): string
    {
        $data = $this->generateArray($tables);

        $flags = $prettyPrint
            ? JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            : JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;

        $json = json_encode($data, $flags);

        if ($json === false) {
            throw new \RuntimeException('Failed to encode schema data as JSON: ' . json_last_error_msg());
        }

        return $json;
    }

    /**
     * Generates array representation of database schema.
     *
     * @param array<int, string> $tables List of table names to export
     * @return array<string, mixed>
     */
    public function generateArray(array $tables): array
    {
        return [
            'version' => '1.0',
            'generated' => date('Y-m-d H:i:s'),
            'tables' => $this->collectTableMetadata($tables)
        ];
    }

    /**
     * Collects metadata for all specified tables.
     *
     * @param array<int, string> $tables
     * @return array<string, array<string, mixed>>
     */
    private function collectTableMetadata(array $tables): array
    {
        $metadata = [];

        foreach ($tables as $table) {
            $columns = $this->schema->columns($table) ?? [];

            // Enrich columns with PHP type mapping
            foreach ($columns as &$column) {
                // Try columnType (MySQL), then udt_name (PostgreSQL), then type (all DBs)
                $dbType = $column['columnType'] ?? $column['udt_name'] ?? $column['type'] ?? '';
                $column['phpType'] = $this->schema->fieldType($dbType) ?? null;
            }

            $metadata[$table] = [
                'columns' => $columns,
                'indexes' => $this->schema->indexes($table) ?? [],
                'foreignKeys' => $this->schema->foreignKeys($table) ?? []
            ];
        }

        return $metadata;
    }
}

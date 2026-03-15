<?php

declare(strict_types=1);

namespace JardisTools\DbSchema;

use JardisTools\DbSchema\Exporter\Ddl\DdlDialectInterface;
use JardisTools\DbSchema\Exporter\Ddl\SqlDdlExporter;
use JardisTools\DbSchema\Exporter\Dialect\MySqlDialect;
use JardisTools\DbSchema\Exporter\Dialect\PostgresDialect;
use JardisTools\DbSchema\Exporter\Dialect\SqLiteDialect;
use JardisTools\DbSchema\Exporter\Json\JsonExporter;
use InvalidArgumentException;
use JardisPort\DbSchema\DbSchemaExporterInterface;
use JardisPort\DbSchema\DbSchemaReaderInterface;

/**
 * Exports database schema in various formats (SQL DDL, JSON, Array).
 *
 * Provides unified access to:
 * - SQL DDL export with automatic dialect detection
 * - JSON export with optional pretty-printing
 * - Array export for programmatic access
 */
class DbSchemaExporter implements DbSchemaExporterInterface
{
    private DbSchemaReaderInterface $schema;
    private ?JsonExporter $jsonExporter = null;
    private ?DdlDialectInterface $dialect = null;

    public function __construct(DbSchemaReaderInterface $schema)
    {
        $this->schema = $schema;
    }

    /**
     * Exports schema as SQL DDL script.
     *
     * @param array<int, string> $tables List of table names to export
     * @return string Complete SQL DDL script
     */
    public function toSql(array $tables): string
    {
        $dialect = $this->getDialect();
        $exporter = new SqlDdlExporter($this->schema, $dialect);
        return $exporter->generate($tables);
    }

    /**
     * Exports schema as JSON.
     *
     * @param array<int, string> $tables List of table names to export
     * @param bool $prettyPrint Whether to format JSON with indentation (default: false)
     * @return string JSON representation of schema
     */
    public function toJson(array $tables, bool $prettyPrint = false): string
    {
        return $this->getJsonExporter()->generate($tables, $prettyPrint);
    }

    /**
     * Exports schema as an array.
     *
     * @param array<int, string> $tables List of table names to export
     * @return array<string, mixed> Array representation of schema
     */
    public function toArray(array $tables): array
    {
        return $this->getJsonExporter()->generateArray($tables);
    }

    /**
     * Gets or creates the JSON exporter instance (lazy-loaded, cached).
     *
     * @return JsonExporter
     */
    private function getJsonExporter(): JsonExporter
    {
        if ($this->jsonExporter === null) {
            $this->jsonExporter = new JsonExporter($this->schema);
        }
        return $this->jsonExporter;
    }

    /**
     * Gets or creates the appropriate SQL dialect based on driver from schema reader (lazy-loaded, cached).
     *
     * @return DdlDialectInterface
     */
    private function getDialect(): DdlDialectInterface
    {
        if ($this->dialect === null) {
            $driver = $this->schema->getDriverName();

            $this->dialect = match ($driver) {
                'mysql' => new MySqlDialect(),
                'pgsql' => new PostgresDialect(),
                'sqlite' => new SqLiteDialect(),
                default => throw new InvalidArgumentException(
                    "Unsupported database driver: {$driver}. Supported: mysql, pgsql, sqlite"
                )
            };
        }
        return $this->dialect;
    }
}

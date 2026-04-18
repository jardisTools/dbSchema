---
name: tools-dbschema
description: Database schema analysis and DDL/JSON export for MySQL, PostgreSQL, SQLite. Use for schema analysis or jardistools/dbschema.
user-invocable: false
---

# DBSCHEMA_COMPONENT_SKILL
> `jardistools/dbschema` v1.0.0 | NS: `JardisTools\DbSchema` | PHP 8.2+ | Infrastructure only — Domain NEVER imports DbSchema.

## ARCHITECTURE
```
DbSchemaReader (auto-detects driver via PDO)
  → MySqlReader | PostgresReader | SqLiteReader
DbSchemaExporter (auto-detects dialect via reader)
  → SqlDdlExporter (readonly) → DdlDialectInterface → MySqlDialect | PostgresDialect | SqLiteDialect
  → JsonExporter (readonly)
  → DependencyResolver (Kahn's topological sort by FK)
```

## API
```php
use JardisTools\DbSchema\{DbSchemaReader, DbSchemaExporter};

$reader = new DbSchemaReader($pdo);
$reader->tables(): array                              // [] when none found — never null
$reader->columns(string $table, array $fields = []): array  // filtered + ordered by $fields
$reader->indexes(string $table): array
$reader->foreignKeys(string $table): array
$reader->fieldType(string $dbType): string            // e.g. 'varchar' → 'string'
$reader->getDriverName(): string                      // 'mysql'|'pgsql'|'sqlite'

$exporter = new DbSchemaExporter($reader);
$exporter->toSql(array $tables): string    // DDL with FK dependency resolution
$exporter->toJson(array $tables, bool $pretty = false): string  // enriched with phpType
$exporter->toArray(array $tables): array
```
All reader methods return `[]` when no results — never `null`.

## TYPE MAPPING
| DB Type | PHP Type |
|---------|----------|
| JSON (MySQL/PostgreSQL) | array |
| json (SQLite) | string |
| varchar, text | string |
| int, bigint | int |
| decimal, float | float |
| boolean | bool |

## ENUM SUPPORT
- **MySQL:** parses `COLUMN_TYPE` `"enum('draft','published')"` → `$column['enumValues']`
- **PostgreSQL:** queries `pg_type`/`pg_enum` for USER-DEFINED types → `$column['enumValues']`

## DDL EXPORT
- `DependencyResolver`: Kahn's topological sort by FK. Self-references skipped. Circular deps → `RuntimeException`.
- Order: DROP TABLE (reverse) → CREATE TABLE → CREATE INDEX → ALTER TABLE FK. Wrapped in transaction.
- **SQLite:** no `ALTER TABLE ... ADD CONSTRAINT` — FKs inline in CREATE TABLE. `PRAGMA foreign_keys = ON` required.
- `DdlDialectInterface` methods: `typeMapping()` · `createTableStatement()` · `createIndexStatement()` · `createForeignKeyStatement()` · `dropTableStatement()` · `beginTransaction()` · `commitTransaction()`

## EXCEPTIONS
| Class | Exception | Trigger |
|-------|-----------|---------|
| `DbSchemaReader` | `InvalidArgumentException` | Unsupported PDO driver |
| `DbSchemaExporter` | `InvalidArgumentException` | Unsupported driver |
| `DependencyResolver` | `RuntimeException` | Circular FK dependencies |
| `JsonExporter` | `RuntimeException` | JSON encoding error |

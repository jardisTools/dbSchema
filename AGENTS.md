# jardistools/dbschema

Schema analysis and DDL export for MySQL/MariaDB, PostgreSQL, and SQLite via PDO. Two-class API: `DbSchemaReader($pdo)` + `DbSchemaExporter($reader)`, both auto-detect the driver via `PDO::ATTR_DRIVER_NAME` and delegate to the appropriate implementation.

## Usage essentials

- **Reader returns are always arrays, never `null`.** `tables()`, `columns($table, ?$fields)` (order respected when `$fields` is given), `indexes($table)`, `foreignKeys($table)` return `[]` on empty results. `fieldType(string $dbType): string` maps DB types to PHP types (`varchar`/`text` → `string`, `int`/`bigint` → `int`, `decimal`/`float` → `float`, `boolean` → `bool`, MySQL/PG `JSON` → `array`, SQLite `json` → `string`).
- **Result normalization per reader:** MySQL boolean strings `'0'/'1'` → PHP `bool`, length/precision/scale via `toIntOrNull()`. MySQL ENUMs are parsed from `COLUMN_TYPE` via `extractEnumValues()`, PostgreSQL ENUMs via `fetchEnumValues()` against `pg_type`/`pg_enum` — both land in `$column['enumValues']`.
- **DDL export pipeline (`toSql()`):** `DependencyResolver` sorts tables topologically (Kahn's algorithm) by FK dependencies; self-references are skipped, circular deps throw `RuntimeException`. Output order: **DROP TABLE (reverse) → CREATE TABLE → CREATE INDEX → ALTER TABLE ADD FOREIGN KEY**, fully wrapped in `BEGIN/COMMIT`.
- **SQLite special case:** no `ALTER TABLE ... ADD CONSTRAINT` — FKs are written inline in `CREATE TABLE`, and the consumer must set `PRAGMA foreign_keys = ON` itself for constraints to take effect at runtime.
- **`DbSchemaExporter` returns three formats:** `toSql([$tables])` (DDL with dependency resolution), `toJson([$tables], bool $pretty = false)` (enriches columns with `phpType`; `JsonException` wrapped as `RuntimeException`), `toArray([$tables])`. Exporter classes (`SqlDdlExporter`, `JsonExporter`) are `readonly` (PHP 8.2+) with Constructor Injection of `DbSchemaReaderInterface` + `DdlDialectInterface`.
- **Package is Infrastructure Layer** — domain code never imports `DbSchema`. `DbSchemaReader`/`DbSchemaExporter` throw `InvalidArgumentException` for unsupported drivers (allowed: `mysql`, `pgsql`, `sqlite`).

## Full reference

https://docs.jardis.io/en/tools/dbschema

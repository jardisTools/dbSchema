# Jardis DbSchema

![Build Status](https://github.com/jardisTools/dbSchema/actions/workflows/ci.yml/badge.svg)
[![License: MIT](https://img.shields.io/badge/License-MIT-green.svg)](LICENSE.md)
[![PHP Version](https://img.shields.io/badge/PHP-%3E%3D8.2-777BB4.svg)](https://www.php.net/)
[![PHPStan Level](https://img.shields.io/badge/PHPStan-Level%208-brightgreen.svg)](phpstan.neon)
[![PSR-12](https://img.shields.io/badge/Code%20Style-PSR--12-blue.svg)](phpcs.xml)
[![Coverage](https://img.shields.io/badge/Coverage-94.23%25-brightgreen.svg)](https://github.com/jardisTools/dbSchema)

> Part of the **[Jardis Business Platform](https://jardis.io)** — Enterprise-grade PHP components for Domain-Driven Design

Database schema introspection and DDL export. Reads table structures, columns, indexes, and foreign keys from live databases via PDO. Exports to SQL (CREATE TABLE), JSON, or PHP arrays. Dialect-aware for MySQL, MariaDB, PostgreSQL, and SQLite.

---

## Features

- **Schema Introspection** — reads tables, columns, indexes, and foreign keys from a live database
- **DDL Export** — generates `CREATE TABLE` SQL scripts with dialect-correct syntax
- **JSON Export** — structured JSON output suitable for storage, diffing, or feeding the Jardis Builder
- **Array Export** — PHP array representation for programmatic processing
- **Multi-Database** — MySQL, MariaDB, PostgreSQL, and SQLite via automatic PDO driver detection
- **Abstract Type Mapping** — `fieldType()` normalises driver-specific column types to portable abstract types
- **Field Type Normalization** — consistent column metadata regardless of database vendor

---

## Installation

```bash
composer require jardistools/dbschema
```

## Quick Start

```php
use JardisTools\DbSchema\DbSchemaReader;
use JardisTools\DbSchema\DbSchemaExporter;

$pdo = new PDO('mysql:host=localhost;dbname=shop', 'user', 'pass');

$reader   = new DbSchemaReader($pdo);
$exporter = new DbSchemaExporter($reader);

// List all tables
$tables = $reader->tables();

// Export schema as JSON (suitable for Jardis Builder input)
$json = $exporter->toJson($tables, prettyPrint: true);
file_put_contents('schema.json', $json);
```

## Advanced Usage

```php
use JardisTools\DbSchema\DbSchemaReader;
use JardisTools\DbSchema\DbSchemaExporter;

$pdo    = new PDO('pgsql:host=localhost;dbname=shop', 'user', 'pass');
$reader = new DbSchemaReader($pdo);

// Inspect a single table
$columns    = $reader->columns('orders');
$indexes    = $reader->indexes('orders');
$foreignKeys = $reader->foreignKeys('orders');

// Translate a driver-specific type to a portable abstract type
$abstractType = $reader->fieldType('character varying'); // → 'string'

// Export selected tables as SQL DDL
$exporter = new DbSchemaExporter($reader);
$ddl = $exporter->toSql(['orders', 'order_lines', 'customers']);
echo $ddl;
// CREATE TABLE "orders" ( ... );
// CREATE TABLE "order_lines" ( ... );

// Export as PHP array for custom processing
$schema = $exporter->toArray(['orders', 'order_lines']);
// [
//   'version'   => '1.0',
//   'generated' => '2025-01-16 10:30:00',
//   'tables'    => ['orders' => ['columns' => [...], 'indexes' => [...], 'foreignKeys' => [...]]]
// ]

// Feed directly into the Jardis Builder
use JardisTools\Builder\Config\DatabaseSchema;
$databaseSchema = DatabaseSchema::fromArray($schema);
```

## Documentation

Full documentation, guides, and API reference:

**[docs.jardis.io/en/tools/dbschema](https://docs.jardis.io/en/tools/dbschema)**

## License

This package is licensed under the [MIT License](LICENSE.md).

---

**[Jardis](https://jardis.io)** · [Documentation](https://docs.jardis.io) · [Headgent](https://headgent.com)

<!-- BEGIN jardis/dev-skills README block — do not edit by hand -->
## KI-gestützte Entwicklung

Dieses Package liefert einen Skill für Claude Code, Cursor, Continue und Aider mit. Installation im Konsumentenprojekt:

```bash
composer require --dev jardis/dev-skills
```

Mehr Details: <https://docs.jardis.io/skills>
<!-- END jardis/dev-skills README block -->

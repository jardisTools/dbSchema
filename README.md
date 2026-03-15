# Jardis DbSchema

![Build Status](https://github.com/jardisTools/dbschema/actions/workflows/ci.yml/badge.svg)
[![License: PolyForm NC](https://img.shields.io/badge/License-PolyForm%20NC-blue.svg)](LICENSE)
[![PHP Version](https://img.shields.io/badge/PHP-%3E%3D8.2-777BB4.svg)](https://www.php.net/)
[![PHPStan Level 8](https://img.shields.io/badge/PHPStan-Level%208-brightgreen.svg)](https://phpstan.org/)
[![PSR-4](https://img.shields.io/badge/autoload-PSR--4-blue.svg)](https://www.php-fig.org/psr/psr-4/)
[![PSR-12](https://img.shields.io/badge/code%20style-PSR--12-blue.svg)](https://www.php-fig.org/psr/psr-12/)
[![Coverage](https://img.shields.io/badge/coverage->95%25-brightgreen)](https://github.com/jardisTools/dbschema)

> Part of the **[Jardis Ecosystem](https://jardis.io)** - A modular DDD framework for PHP

A powerful, developer-friendly PHP library for analyzing and managing database schemas. Works seamlessly with MySQL/MariaDB, PostgreSQL, and SQLite through one unified, PDO-based interface. Features automatic driver detection, normalized output structures, and complete DDL/JSON export capabilities.

---

## Features

- **Zero Configuration** - Just pass a PDO connection, automatic driver detection handles the rest
- **Universal Interface** - Write once, run on MySQL, MariaDB, PostgreSQL, or SQLite
- **Complete Schema Insight** - Tables, columns, indexes, foreign keys at your fingertips
- **SQL DDL Export** - Generate complete database schemas with automatic dependency resolution
- **JSON/Array Export** - Export schema metadata for documentation and API integration
- **Type-Safe** - Built with PHP 8.2+, strict types, and PHPStan level 8

---

## Installation

```bash
composer require jardistools/dbschema
```

## Quick Start

```php
use JardisTools\DbSchema\DbSchemaReader;
use JardisTools\DbSchema\DbSchemaExporter;
use PDO;

// Create PDO connection - driver auto-detected
$pdo = new PDO('mysql:host=localhost;dbname=mydb', 'user', 'pass');
$reader = new DbSchemaReader($pdo);

// Read schema information
$tables = $reader->tables();
$columns = $reader->columns('users');
$indexes = $reader->indexes('users');
$foreignKeys = $reader->foreignKeys('orders');

// Export schema
$exporter = new DbSchemaExporter($reader);
$ddl = $exporter->toSql(['users', 'orders']);             // SQL DDL
$json = $exporter->toJson(['users'], prettyPrint: true);  // JSON
$array = $exporter->toArray(['users']);                   // PHP array
```

## Documentation

Full documentation, examples and API reference:

**[jardis.io/docs/tools/dbschema](https://jardis.io/docs/tools/dbschema)**

## Jardis Ecosystem

This package is part of the Jardis Ecosystem - a collection of modular, high-quality PHP packages designed for Domain-Driven Design.

| Category    | Packages                                                     |
|-------------|--------------------------------------------------------------|
| **Core**    | Domain, Kernel, Data                                         |
| **Adapter** | Cache, Logger, Messaging, DbConnection                       |
| **Support** | DotEnv, DbQuery, Validation, Factory, ClassVersion, Workflow |
| **Tools**   | Builder, DbSchema                                            |

**[Explore all packages](https://jardis.io/docs)**

## License

This package is licensed under the [PolyForm Noncommercial License 1.0.0](LICENSE).

For commercial use, see [COMMERCIAL.md](COMMERCIAL.md).

---

**[Jardis Ecosystem](https://jardis.io)** by [Headgent Development](https://headgent.com)

<?php

declare(strict_types=1);

namespace JardisTools\DbSchema\Tests\Integration;

use JardisTools\DbSchema\DbSchemaReader;
use JardisTools\DbSchema\DbSchemaExporter;
use JardisTools\DbSchema\Tests\PdoFactory;
use JardisTools\DbSchema\Tests\SchemaBuilder;
use PHPUnit\Framework\TestCase;

class DbSchemaExporterTest extends TestCase
{
    public function testToSqlMySql(): void
    {
        // Create test schema
        $pdo = PdoFactory::createMySqlPdo();
        SchemaBuilder::dropMySqlTestSchema($pdo);
        SchemaBuilder::createMySqlTestSchema($pdo);

        // Create reader and exporter
        $reader = new DbSchemaReader($pdo);
        $exporter = new DbSchemaExporter($reader);

        // Export to SQL
        $sql = $exporter->toSql(['users', 'orders']);

        // Verify SQL structure
        $this->assertStringContainsString('SQL DDL Export', $sql);
        $this->assertStringContainsString('START TRANSACTION;', $sql);
        $this->assertStringContainsString('CREATE TABLE `users`', $sql);
        $this->assertStringContainsString('CREATE TABLE `orders`', $sql);
        $this->assertStringContainsString('FOREIGN KEY', $sql);
        $this->assertStringContainsString('COMMIT;', $sql);
    }

    public function testToSqlPostgres(): void
    {
        // Create test schema
        $pdo = PdoFactory::createPostgresPdo();
        $pdo->exec("DROP TABLE IF EXISTS orders CASCADE");
        $pdo->exec("DROP TABLE IF EXISTS users CASCADE");
        SchemaBuilder::createPostgresTestSchema($pdo);

        // Create reader and exporter
        $reader = new DbSchemaReader($pdo);
        $exporter = new DbSchemaExporter($reader);

        // Export to SQL
        $sql = $exporter->toSql(['users', 'orders']);

        // Verify SQL structure
        $this->assertStringContainsString('SQL DDL Export', $sql);
        $this->assertStringContainsString('BEGIN;', $sql);
        $this->assertStringContainsString('CREATE TABLE "users"', $sql);
        $this->assertStringContainsString('CREATE TABLE "orders"', $sql);
        $this->assertStringContainsString('COMMIT;', $sql);
    }

    public function testToSqlSqlite(): void
    {
        // Create test schema
        $pdo = PdoFactory::createSqlitePdo();
        SchemaBuilder::createSqliteTestSchema($pdo);

        // Create reader and exporter
        $reader = new DbSchemaReader($pdo);
        $exporter = new DbSchemaExporter($reader);

        // Export to SQL
        $sql = $exporter->toSql(['users', 'orders']);

        // Verify SQL structure
        $this->assertStringContainsString('SQL DDL Export', $sql);
        $this->assertStringContainsString('BEGIN TRANSACTION;', $sql);
        $this->assertStringContainsString('CREATE TABLE "users"', $sql);
        $this->assertStringContainsString('CREATE TABLE "orders"', $sql);
        $this->assertStringContainsString('COMMIT;', $sql);
    }

    public function testToJsonCompact(): void
    {
        // Create test schema
        $pdo = PdoFactory::createMySqlPdo();
        SchemaBuilder::dropMySqlTestSchema($pdo);
        SchemaBuilder::createMySqlTestSchema($pdo);

        // Create reader and exporter
        $reader = new DbSchemaReader($pdo);
        $exporter = new DbSchemaExporter($reader);

        // Export to JSON (compact)
        $json = $exporter->toJson(['users', 'orders']);

        // Verify JSON structure
        $data = json_decode($json, true);
        $this->assertIsArray($data);
        $this->assertArrayHasKey('version', $data);
        $this->assertArrayHasKey('generated', $data);
        $this->assertArrayHasKey('tables', $data);
        $this->assertArrayHasKey('users', $data['tables']);
        $this->assertArrayHasKey('orders', $data['tables']);

        // Verify compact format (no newlines)
        $this->assertStringNotContainsString("\n", $json);
    }

    public function testToJsonPretty(): void
    {
        // Create test schema
        $pdo = PdoFactory::createMySqlPdo();
        SchemaBuilder::dropMySqlTestSchema($pdo);
        SchemaBuilder::createMySqlTestSchema($pdo);

        // Create reader and exporter
        $reader = new DbSchemaReader($pdo);
        $exporter = new DbSchemaExporter($reader);

        // Export to JSON (pretty)
        $json = $exporter->toJson(['users'], prettyPrint: true);

        // Verify pretty format (has newlines and indentation)
        $this->assertStringContainsString("\n", $json);
        $this->assertStringContainsString("    ", $json);

        // Verify structure
        $data = json_decode($json, true);
        $this->assertIsArray($data);
        $this->assertArrayHasKey('users', $data['tables']);
    }

    public function testToArray(): void
    {
        // Create test schema
        $pdo = PdoFactory::createMySqlPdo();
        SchemaBuilder::dropMySqlTestSchema($pdo);
        SchemaBuilder::createMySqlTestSchema($pdo);

        // Create reader and exporter
        $reader = new DbSchemaReader($pdo);
        $exporter = new DbSchemaExporter($reader);

        // Export to array
        $array = $exporter->toArray(['users', 'orders']);

        // Verify array structure
        $this->assertIsArray($array);
        $this->assertArrayHasKey('version', $array);
        $this->assertArrayHasKey('generated', $array);
        $this->assertArrayHasKey('tables', $array);
        $this->assertArrayHasKey('users', $array['tables']);
        $this->assertArrayHasKey('orders', $array['tables']);

        // Verify table structure
        $users = $array['tables']['users'];
        $this->assertArrayHasKey('columns', $users);
        $this->assertArrayHasKey('indexes', $users);
        $this->assertArrayHasKey('foreignKeys', $users);
        $this->assertIsArray($users['columns']);
        $this->assertNotEmpty($users['columns']);
    }

    public function testMultipleExportCallsUseCachedInstances(): void
    {
        // Create test schema
        $pdo = PdoFactory::createMySqlPdo();
        SchemaBuilder::dropMySqlTestSchema($pdo);
        SchemaBuilder::createMySqlTestSchema($pdo);

        // Create reader and exporter
        $reader = new DbSchemaReader($pdo);
        $exporter = new DbSchemaExporter($reader);

        // Multiple JSON exports
        $json1 = $exporter->toJson(['users']);
        $json2 = $exporter->toJson(['orders']);
        $array = $exporter->toArray(['users']);

        // All should work (verifies caching doesn't break functionality)
        $this->assertNotEmpty($json1);
        $this->assertNotEmpty($json2);
        $this->assertNotEmpty($array);

        // Multiple SQL exports
        $sql1 = $exporter->toSql(['users']);
        $sql2 = $exporter->toSql(['orders']);

        // Both should contain valid SQL
        $this->assertStringContainsString('CREATE TABLE', $sql1);
        $this->assertStringContainsString('CREATE TABLE', $sql2);
    }

    public function testExportSingleTable(): void
    {
        // Create test schema
        $pdo = PdoFactory::createMySqlPdo();
        SchemaBuilder::dropMySqlTestSchema($pdo);
        SchemaBuilder::createMySqlTestSchema($pdo);

        // Create reader and exporter
        $reader = new DbSchemaReader($pdo);
        $exporter = new DbSchemaExporter($reader);

        // Export only users table
        $json = $exporter->toJson(['users']);
        $data = json_decode($json, true);

        // Verify only users is exported
        $this->assertArrayHasKey('users', $data['tables']);
        $this->assertArrayNotHasKey('orders', $data['tables']);
        $this->assertCount(1, $data['tables']);
    }

    public function testToArrayMatchesToJsonDecode(): void
    {
        // Create test schema
        $pdo = PdoFactory::createMySqlPdo();
        SchemaBuilder::dropMySqlTestSchema($pdo);
        SchemaBuilder::createMySqlTestSchema($pdo);

        // Create reader and exporter
        $reader = new DbSchemaReader($pdo);
        $exporter = new DbSchemaExporter($reader);

        // Get both representations
        $array = $exporter->toArray(['users']);
        $json = $exporter->toJson(['users']);
        $jsonDecoded = json_decode($json, true);

        // They should be identical
        $this->assertEquals($array, $jsonDecoded);
    }
}

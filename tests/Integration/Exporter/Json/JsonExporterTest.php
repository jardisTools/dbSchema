<?php

declare(strict_types=1);

namespace JardisTools\DbSchema\Tests\Integration\Exporter\Json;

use JardisTools\DbSchema\DbSchemaReader;
use JardisTools\DbSchema\Exporter\Json\JsonExporter;
use JardisTools\DbSchema\Tests\PdoFactory;
use JardisTools\DbSchema\Tests\SchemaBuilder;
use PHPUnit\Framework\TestCase;

class JsonExporterTest extends TestCase
{
    public function testGenerateMySqlJson(): void
    {
        // Create test schema
        $pdo = PdoFactory::createMySqlPdo();
        SchemaBuilder::dropMySqlTestSchema($pdo);
        SchemaBuilder::createMySqlTestSchema($pdo);

        // Create schema analyzer and JSON exporter
        $schema = new DbSchemaReader($pdo);
        $exporter = new JsonExporter($schema);

        // Generate JSON for both tables
        $json = $exporter->generate(['users', 'orders']);

        // Decode to verify structure
        $data = json_decode($json, true);

        $this->assertIsArray($data);
        $this->assertArrayHasKey('version', $data);
        $this->assertArrayHasKey('generated', $data);
        $this->assertArrayHasKey('tables', $data);

        // Verify metadata
        $this->assertEquals('1.0', $data['version']);

        // Verify tables structure
        $this->assertArrayHasKey('users', $data['tables']);
        $this->assertArrayHasKey('orders', $data['tables']);

        // Verify users table
        $users = $data['tables']['users'];
        $this->assertArrayHasKey('columns', $users);
        $this->assertArrayHasKey('indexes', $users);
        $this->assertArrayHasKey('foreignKeys', $users);

        // Verify users columns
        $this->assertGreaterThan(0, count($users['columns']));

        // Find id column (may not be first due to ordering)
        $idColumn = array_filter($users['columns'], fn($col) => $col['name'] === 'id');
        $idColumn = array_values($idColumn)[0];

        $this->assertEquals('id', $idColumn['name']);
        $this->assertEquals('int', $idColumn['type']);
        $this->assertTrue($idColumn['primary']);
        $this->assertTrue($idColumn['auto_increment']);

        // Verify orders foreign key
        $orders = $data['tables']['orders'];
        $this->assertNotEmpty($orders['foreignKeys']);
        $fk = $orders['foreignKeys'][0];
        $this->assertEquals('user_id', $fk['constraintCol']);
        $this->assertEquals('users', $fk['refContainer']);
        $this->assertEquals('id', $fk['refColumn']);
    }

    public function testGeneratePostgresJson(): void
    {
        // Create test schema
        $pdo = PdoFactory::createPostgresPdo();
        $pdo->exec("DROP TABLE IF EXISTS orders CASCADE");
        $pdo->exec("DROP TABLE IF EXISTS users CASCADE");
        SchemaBuilder::createPostgresTestSchema($pdo);

        // Create schema analyzer and JSON exporter
        $schema = new DbSchemaReader($pdo);
        $exporter = new JsonExporter($schema);

        // Generate JSON
        $json = $exporter->generate(['users', 'orders']);
        $data = json_decode($json, true);

        // Verify structure
        $this->assertArrayHasKey('users', $data['tables']);
        $this->assertArrayHasKey('orders', $data['tables']);

        // Verify data integrity
        $users = $data['tables']['users'];
        $this->assertNotEmpty($users['columns']);
        $this->assertIsArray($users['indexes']);
        $this->assertIsArray($users['foreignKeys']);
    }

    public function testGenerateSqliteJson(): void
    {
        // Create test schema
        $pdo = PdoFactory::createSqlitePdo();
        SchemaBuilder::createSqliteTestSchema($pdo);

        // Create schema analyzer and JSON exporter
        $schema = new DbSchemaReader($pdo);
        $exporter = new JsonExporter($schema);

        // Generate JSON
        $json = $exporter->generate(['users', 'orders']);
        $data = json_decode($json, true);

        // Verify structure
        $this->assertArrayHasKey('users', $data['tables']);
        $this->assertArrayHasKey('orders', $data['tables']);
    }

    public function testGenerateWithSelectedTables(): void
    {
        $pdo = PdoFactory::createMySqlPdo();
        SchemaBuilder::dropMySqlTestSchema($pdo);
        SchemaBuilder::createMySqlTestSchema($pdo);

        $schema = new DbSchemaReader($pdo);
        $exporter = new JsonExporter($schema);

        // Generate JSON for only users table
        $json = $exporter->generate(['users']);
        $data = json_decode($json, true);

        $this->assertArrayHasKey('users', $data['tables']);
        $this->assertArrayNotHasKey('orders', $data['tables']);
    }

    public function testPrettyPrintFormat(): void
    {
        $pdo = PdoFactory::createMySqlPdo();
        SchemaBuilder::dropMySqlTestSchema($pdo);
        SchemaBuilder::createMySqlTestSchema($pdo);

        $schema = new DbSchemaReader($pdo);
        $exporter = new JsonExporter($schema);

        // Generate with pretty print
        $prettyJson = $exporter->generate(['users'], prettyPrint: true);
        $compactJson = $exporter->generate(['users'], prettyPrint: false);

        // Pretty print should have newlines and indentation
        $this->assertStringContainsString("\n", $prettyJson);
        $this->assertStringContainsString("  ", $prettyJson);

        // Compact should be shorter
        $this->assertLessThan(strlen($prettyJson), strlen($compactJson));

        // Both should decode to same data
        $this->assertEquals(
            json_decode($prettyJson, true),
            json_decode($compactJson, true)
        );
    }

    public function testGenerateArrayMethod(): void
    {
        $pdo = PdoFactory::createMySqlPdo();
        SchemaBuilder::dropMySqlTestSchema($pdo);
        SchemaBuilder::createMySqlTestSchema($pdo);

        $schema = new DbSchemaReader($pdo);
        $exporter = new JsonExporter($schema);

        // Get array representation
        $array = $exporter->generateArray(['users']);

        $this->assertIsArray($array);
        $this->assertArrayHasKey('version', $array);
        $this->assertArrayHasKey('generated', $array);
        $this->assertArrayHasKey('tables', $array);

        // Verify it matches JSON decode
        $json = $exporter->generate(['users']);
        $this->assertEquals($array, json_decode($json, true));
    }

    public function testColumnsWithEnumValues(): void
    {
        $pdo = PdoFactory::createMySqlPdo();
        SchemaBuilder::dropMySqlTestSchema($pdo);

        // Create table with enum
        $pdo->exec("
            CREATE TABLE users (
                id INT AUTO_INCREMENT PRIMARY KEY,
                status ENUM('active', 'inactive', 'pending') NOT NULL
            ) ENGINE=InnoDB
        ");

        $schema = new DbSchemaReader($pdo);
        $exporter = new JsonExporter($schema);

        $json = $exporter->generate(['users']);
        $data = json_decode($json, true);

        // Find status column
        $statusColumn = array_filter(
            $data['tables']['users']['columns'],
            fn($col) => $col['name'] === 'status'
        );
        $statusColumn = array_values($statusColumn)[0];

        $this->assertEquals('enum', $statusColumn['type']);
        $this->assertArrayHasKey('enumValues', $statusColumn);
        $this->assertEquals(['active', 'inactive', 'pending'], $statusColumn['enumValues']);
    }

    public function testEmptyForeignKeysAndIndexes(): void
    {
        $pdo = PdoFactory::createMySqlPdo();
        SchemaBuilder::dropMySqlTestSchema($pdo);

        // Ensure table doesn't exist
        $pdo->exec("DROP TABLE IF EXISTS simple");

        // Create simple table without indexes or foreign keys
        $pdo->exec("
            CREATE TABLE simple (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(255)
            ) ENGINE=InnoDB
        ");

        $schema = new DbSchemaReader($pdo);
        $exporter = new JsonExporter($schema);

        $json = $exporter->generate(['simple']);
        $data = json_decode($json, true);

        $simple = $data['tables']['simple'];

        // Should have empty arrays, not null
        $this->assertIsArray($simple['foreignKeys']);
        $this->assertEmpty($simple['foreignKeys']);
    }

    public function testPhpTypeMappingInExport(): void
    {
        $pdo = PdoFactory::createMySqlPdo();
        SchemaBuilder::dropMySqlTestSchema($pdo);

        // Drop type_test if it exists
        $pdo->exec("DROP TABLE IF EXISTS type_test");

        // Create table with various column types
        $pdo->exec("
            CREATE TABLE type_test (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                age TINYINT UNSIGNED,
                balance DECIMAL(10,2),
                is_active BOOLEAN DEFAULT 1,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                description TEXT
            ) ENGINE=InnoDB
        ");

        $schema = new DbSchemaReader($pdo);
        $exporter = new JsonExporter($schema);

        $array = $exporter->generateArray(['type_test']);
        $columns = $array['tables']['type_test']['columns'];

        // Verify phpType is present for each column
        foreach ($columns as $column) {
            $this->assertArrayHasKey('phpType', $column, "Column '{$column['name']}' should have phpType");
        }

        // Verify specific type mappings
        $columnsByName = array_column($columns, null, 'name');

        $this->assertEquals('int', $columnsByName['id']['phpType']);
        $this->assertEquals('string', $columnsByName['name']['phpType']);
        $this->assertEquals('int', $columnsByName['age']['phpType']);
        $this->assertEquals('float', $columnsByName['balance']['phpType']);
        // MySQL stores BOOLEAN as tinyint(1), which maps to int
        $this->assertEquals('int', $columnsByName['is_active']['phpType']);
        $this->assertEquals('datetime', $columnsByName['created_at']['phpType']);
        $this->assertEquals('string', $columnsByName['description']['phpType']);
    }

    public function testPhpTypeMappingPostgres(): void
    {
        $pdo = PdoFactory::createPostgresPdo();
        $pdo->exec("DROP TABLE IF EXISTS type_test CASCADE");

        // Create table with PostgreSQL-specific types
        $pdo->exec("
            CREATE TABLE type_test (
                id SERIAL PRIMARY KEY,
                data JSONB,
                tags TEXT[],
                amount NUMERIC(10,2)
            )
        ");

        $schema = new DbSchemaReader($pdo);
        $exporter = new JsonExporter($schema);

        $array = $exporter->generateArray(['type_test']);
        $columns = $array['tables']['type_test']['columns'];

        // Verify phpType is present
        foreach ($columns as $column) {
            $this->assertArrayHasKey('phpType', $column);
        }

        $columnsByName = array_column($columns, null, 'name');

        $this->assertEquals('int', $columnsByName['id']['phpType']);
        // PostgreSQL udt_name for JSONB is 'jsonb', which maps to array
        $this->assertEquals('array', $columnsByName['data']['phpType']);
        // PostgreSQL udt_name for TEXT[] is '_text', which maps to string (not array in current implementation)
        $this->assertEquals('string', $columnsByName['tags']['phpType']);
        $this->assertEquals('float', $columnsByName['amount']['phpType']);
    }

    public function testPhpTypeMappingSqlite(): void
    {
        $pdo = PdoFactory::createSqlitePdo();

        // Create table with SQLite types
        $pdo->exec("
            CREATE TABLE type_test (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT,
                price REAL,
                count INTEGER
            )
        ");

        $schema = new DbSchemaReader($pdo);
        $exporter = new JsonExporter($schema);

        $array = $exporter->generateArray(['type_test']);
        $columns = $array['tables']['type_test']['columns'];

        // Verify phpType is present
        foreach ($columns as $column) {
            $this->assertArrayHasKey('phpType', $column);
        }

        $columnsByName = array_column($columns, null, 'name');

        $this->assertEquals('int', $columnsByName['id']['phpType']);
        $this->assertEquals('string', $columnsByName['name']['phpType']);
        $this->assertEquals('float', $columnsByName['price']['phpType']);
        $this->assertEquals('int', $columnsByName['count']['phpType']);
    }
}

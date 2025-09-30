<?php

namespace DevFramework\Core\Database;

/**
 * Database schema builder for creating tables from array definitions
 * Provides a simple way to define database tables without writing SQL
 */
class SchemaBuilder
{
    private Database $database;
    private array $supportedTypes = [
        'int' => 'INT',
        'integer' => 'INT',
        'bigint' => 'BIGINT',
        'varchar' => 'VARCHAR',
        'string' => 'VARCHAR',
        'text' => 'TEXT',
        'longtext' => 'LONGTEXT',
        'bool' => 'TINYINT(1)',
        'boolean' => 'TINYINT(1)',
        'decimal' => 'DECIMAL',
        'float' => 'FLOAT',
        'double' => 'DOUBLE',
        'date' => 'DATE',
        'datetime' => 'DATETIME',
        'timestamp' => 'INT',
        'json' => 'JSON'
    ];

    public function __construct(?Database $database = null)
    {
        $this->database = $database ?? Database::getInstance();
    }

    /**
     * Create table from schema definition
     */
    public function createTable(string $tableName, array $schema): bool
    {
        try {
            $sql = $this->buildCreateTableSQL($tableName, $schema);
            $this->database->execute($sql);
            return true;
        } catch (\Exception $e) {
            throw new DatabaseException("Failed to create table '{$tableName}': " . $e->getMessage());
        }
    }

    /**
     * Drop table if exists
     */
    public function dropTable(string $tableName): bool
    {
        try {
            $prefixedTable = $this->database->addPrefix($tableName);
            $sql = "DROP TABLE IF EXISTS {$prefixedTable}";
            $this->database->execute($sql);
            return true;
        } catch (\Exception $e) {
            throw new DatabaseException("Failed to drop table '{$tableName}': " . $e->getMessage());
        }
    }

    /**
     * Check if table exists
     */
    public function tableExists(string $tableName): bool
    {
        try {
            $prefixedTable = $this->database->addPrefix($tableName);
            $connection = $this->database->getConnection();

            // Use SHOW TABLES which is more reliable than information_schema
            $stmt = $connection->prepare("SHOW TABLES LIKE ?");
            $stmt->execute([$prefixedTable]);
            return $stmt->fetchColumn() !== false;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Build CREATE TABLE SQL from schema definition
     */
    private function buildCreateTableSQL(string $tableName, array $schema): string
    {
        $prefixedTable = $this->database->addPrefix($tableName);
        $columns = [];
        $indexes = [];
        $primaryKey = null;

        // Process fields
        if (!isset($schema['fields']) || empty($schema['fields'])) {
            throw new \InvalidArgumentException("Table schema must have 'fields' definition");
        }

        foreach ($schema['fields'] as $fieldName => $fieldDef) {
            $columns[] = $this->buildColumnDefinition($fieldName, $fieldDef);

            // Check for primary key
            if (isset($fieldDef['primary']) && $fieldDef['primary']) {
                $primaryKey = $fieldName;
            }
        }

        // Add primary key if specified
        if ($primaryKey) {
            $columns[] = "PRIMARY KEY ({$primaryKey})";
        }

        // Process indexes
        if (isset($schema['indexes'])) {
            foreach ($schema['indexes'] as $indexName => $indexDef) {
                $indexes[] = $this->buildIndexDefinition($indexName, $indexDef);
            }
        }

        // Process unique constraints
        if (isset($schema['unique'])) {
            foreach ($schema['unique'] as $uniqueName => $uniqueDef) {
                $indexes[] = $this->buildUniqueDefinition($uniqueName, $uniqueDef);
            }
        }

        // Combine all definitions
        $allDefinitions = array_merge($columns, $indexes);
        $columnsSQL = implode(",\n    ", $allDefinitions);

        $sql = "CREATE TABLE IF NOT EXISTS {$prefixedTable} (\n    {$columnsSQL}\n)";

        // Add table options
        if (isset($schema['options'])) {
            $sql .= " " . $schema['options'];
        } else {
            // Default MySQL options
            $sql .= " ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        }

        return $sql;
    }

    /**
     * Build column definition
     */
    private function buildColumnDefinition(string $fieldName, array $fieldDef): string
    {
        if (!isset($fieldDef['type'])) {
            throw new \InvalidArgumentException("Field '{$fieldName}' must have a 'type' definition");
        }

        $type = strtolower($fieldDef['type']);
        if (!isset($this->supportedTypes[$type])) {
            throw new \InvalidArgumentException("Unsupported field type '{$type}' for field '{$fieldName}'");
        }

        $sql = $fieldName . ' ' . $this->supportedTypes[$type];

        // Add length/precision
        if (isset($fieldDef['length'])) {
            $sql .= '(' . $fieldDef['length'] . ')';
        } elseif (in_array($type, ['varchar', 'string']) && !isset($fieldDef['length'])) {
            $sql .= '(255)'; // Default varchar length
        }

        // Add precision for decimal
        if ($type === 'decimal' && isset($fieldDef['precision'])) {
            $sql .= '(' . $fieldDef['precision'] . ',' . ($fieldDef['scale'] ?? 2) . ')';
        }

        // Add NOT NULL
        if (isset($fieldDef['null']) && !$fieldDef['null']) {
            $sql .= ' NOT NULL';
        }

        // Add AUTO_INCREMENT
        if (isset($fieldDef['auto_increment']) && $fieldDef['auto_increment']) {
            $sql .= ' AUTO_INCREMENT';
        }

        // Add DEFAULT value
        if (isset($fieldDef['default'])) {
            $default = $fieldDef['default'];

            // Handle boolean values for MySQL compatibility
            if (is_bool($default)) {
                $default = $default ? 1 : 0;
            } elseif (is_string($default) && $default !== 'NULL' && $default !== 'CURRENT_TIMESTAMP') {
                $default = "'{$default}'";
            }

            $sql .= ' DEFAULT ' . $default;
        }

        // Add COMMENT
        if (isset($fieldDef['comment'])) {
            $sql .= " COMMENT '{$fieldDef['comment']}'";
        }

        return $sql;
    }

    /**
     * Build index definition
     */
    private function buildIndexDefinition(string $indexName, mixed $indexDef): string
    {
        if (is_string($indexDef)) {
            // Simple case: just field name
            return "INDEX idx_{$indexName} ({$indexDef})";
        }

        if (is_array($indexDef) && isset($indexDef['fields'])) {
            $fields = is_array($indexDef['fields']) ? implode(', ', $indexDef['fields']) : $indexDef['fields'];
            return "INDEX idx_{$indexName} ({$fields})";
        }

        throw new \InvalidArgumentException("Invalid index definition for '{$indexName}'");
    }

    /**
     * Build unique constraint definition
     */
    private function buildUniqueDefinition(string $uniqueName, mixed $uniqueDef): string
    {
        if (is_string($uniqueDef)) {
            // Simple case: just field name
            return "UNIQUE KEY uk_{$uniqueName} ({$uniqueDef})";
        }

        if (is_array($uniqueDef) && isset($uniqueDef['fields'])) {
            $fields = is_array($uniqueDef['fields']) ? implode(', ', $uniqueDef['fields']) : $uniqueDef['fields'];
            return "UNIQUE KEY uk_{$uniqueName} ({$fields})";
        }

        throw new \InvalidArgumentException("Invalid unique constraint definition for '{$uniqueName}'");
    }
}

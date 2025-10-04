<?php

namespace DevFramework\Core\Database;

use Exception;
use PDO;

/**
 * Database Schema Loader
 * Handles loading and executing database schema files for consistent installation/upgrade process
 */
class SchemaLoader
{
    private Database $database;

    public function __construct()
    {
        $this->database = Database::getInstance();
    }

    /**
     * Load and execute schema from a schema file
     *
     * @param string $schemaFilePath Path to the schema file
     * @param array $context Additional context variables to pass to the schema
     * @return bool Success status
     */
    public function loadSchema(string $schemaFilePath, array $context = []): bool
    {
        try {
            if (!file_exists($schemaFilePath)) {
                error_log("SchemaLoader: Schema file not found: {$schemaFilePath}");
                return false;
            }

            error_log("SchemaLoader: Loading schema file: {$schemaFilePath}");

            // Make database utilities available to schema files
            $db = $this->database;
            $connection = $this->database->getConnection();

            // Helper function to get prefixed table name - use environment prefix directly for reliability
            $getTableName = function($tableName) use ($db) {
                // Use environment prefix directly to ensure it's available during initial installation
                $prefix = $_ENV['DB_PREFIX'] ?? 'dev_';
                $prefixedName = $prefix . $tableName;
                error_log("SchemaLoader: getTableName('{$tableName}') -> '{$prefixedName}' (using env prefix)");
                return $prefixedName;
            };

            // Helper function to execute SQL
            $executeSql = function($sql) use ($connection) {
                error_log("SchemaLoader: Executing SQL: " . substr($sql, 0, 100) . "...");
                $connection->exec($sql);
            };

            // Helper function to check if table exists
            $tableExists = function($tableName) use ($connection, $db) {
                // Use environment prefix directly to ensure consistency
                $prefix = $_ENV['DB_PREFIX'] ?? 'dev_';
                $prefixedTableName = $prefix . $tableName;
                error_log("SchemaLoader: tableExists('{$tableName}') checking '{$prefixedTableName}'");
                try {
                    // Use SHOW TABLES without parameters to avoid SQL syntax issues
                    $stmt = $connection->query("SHOW TABLES");
                    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
                    $exists = in_array($prefixedTableName, $tables);
                    error_log("SchemaLoader: tableExists('{$tableName}') -> " . ($exists ? 'true' : 'false'));
                    return $exists;
                } catch (Exception $e) {
                    error_log("SchemaLoader: tableExists error: " . $e->getMessage());
                    return false;
                }
            };

            // Extract additional context variables
            extract($context);

            // Include the schema file
            error_log("SchemaLoader: About to include schema file: {$schemaFilePath}");
            $result = include $schemaFilePath;
            error_log("SchemaLoader: Schema file included, result: " . var_export($result, true));

            // Schema files should return true on success, false on failure
            return $result !== false;

        } catch (Exception $e) {
            error_log("SchemaLoader: Error loading schema from {$schemaFilePath}: " . $e->getMessage());
            error_log("SchemaLoader: Stack trace: " . $e->getTraceAsString());
            return false;
        }
    }

    /**
     * Execute multiple schema files in order
     *
     * @param array $schemaFiles Array of schema file paths
     * @param array $context Additional context to pass to all schemas
     * @return bool Success status
     */
    public function loadMultipleSchemas(array $schemaFiles, array $context = []): bool
    {
        foreach ($schemaFiles as $schemaFile) {
            if (!$this->loadSchema($schemaFile, $context)) {
                error_log("SchemaLoader: Failed to load schema: {$schemaFile}");
                return false;
            }
        }
        return true;
    }

    /**
     * Get schema version from database
     *
     * @param string $component Component name (e.g., 'core', 'auth')
     * @return int Current version number, 0 if not found
     */
    public function getSchemaVersion(string $component): int
    {
        try {
            // Ensure schema_versions table exists
            $this->ensureSchemaVersionsTable();

            $connection = $this->database->getConnection();
            $tableName = $this->database->addPrefix('schema_versions');

            $stmt = $connection->prepare("SELECT version FROM `{$tableName}` WHERE component = ?");
            $stmt->execute([$component]);
            $result = $stmt->fetchColumn();

            return $result ? (int)$result : 0;
        } catch (Exception $e) {
            error_log("SchemaLoader: Error getting schema version for {$component}: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Update schema version in database
     *
     * @param string $component Component name
     * @param int $version New version number
     * @return bool Success status
     */
    public function updateSchemaVersion(string $component, int $version): bool
    {
        try {
            // Ensure schema_versions table exists
            $this->ensureSchemaVersionsTable();

            $connection = $this->database->getConnection();
            $tableName = $this->database->addPrefix('schema_versions');
            $currentTime = time();

            // Use INSERT ... ON DUPLICATE KEY UPDATE for MySQL
            $sql = "INSERT INTO `{$tableName}` (component, version, timecreated, timemodified) 
                    VALUES (?, ?, ?, ?) 
                    ON DUPLICATE KEY UPDATE version = VALUES(version), timemodified = VALUES(timemodified)";

            $stmt = $connection->prepare($sql);
            $result = $stmt->execute([$component, $version, $currentTime, $currentTime]);

            error_log("SchemaLoader: Updated schema version for {$component} to {$version}");
            return $result;

        } catch (Exception $e) {
            error_log("SchemaLoader: Error updating schema version for {$component}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Check if component needs upgrade
     *
     * @param string $component Component name
     * @param int $targetVersion Target version to check against
     * @return bool True if upgrade is needed
     */
    public function needsUpgrade(string $component, int $targetVersion): bool
    {
        $currentVersion = $this->getSchemaVersion($component);
        return $currentVersion < $targetVersion;
    }

    /**
     * Ensure schema_versions table exists
     */
    private function ensureSchemaVersionsTable(): void
    {
        $connection = $this->database->getConnection();
        $tableName = $this->database->addPrefix('schema_versions');

        $sql = "CREATE TABLE IF NOT EXISTS `{$tableName}` (
            component VARCHAR(100) NOT NULL COMMENT 'Component name (core, auth, etc.)',
            version INT(11) NOT NULL DEFAULT 0 COMMENT 'Current schema version',
            timecreated INT(11) NOT NULL DEFAULT 0 COMMENT 'Time created',
            timemodified INT(11) NOT NULL DEFAULT 0 COMMENT 'Time modified',
            PRIMARY KEY (component)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Schema version tracking'";

        $connection->exec($sql);
    }

    /**
     * Get all installed components and their versions
     *
     * @return array Array of component => version pairs
     */
    public function getAllSchemaVersions(): array
    {
        try {
            $this->ensureSchemaVersionsTable();

            $connection = $this->database->getConnection();
            $tableName = $this->database->addPrefix('schema_versions');

            $stmt = $connection->query("SELECT component, version FROM `{$tableName}`");
            $results = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

            return $results ?: [];
        } catch (Exception $e) {
            error_log("SchemaLoader: Error getting all schema versions: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Execute upgrade scripts from current version to target version
     *
     * @param string $component Component name
     * @param string $upgradeDir Directory containing upgrade scripts
     * @param int $targetVersion Target version to upgrade to
     * @return bool Success status
     */
    public function executeUpgrades(string $component, string $upgradeDir, int $targetVersion): bool
    {
        $currentVersion = $this->getSchemaVersion($component);

        if ($currentVersion >= $targetVersion) {
            return true; // Already at target version or higher
        }

        try {
            // Look for upgrade files in the format upgrade_X.php where X is the version
            for ($version = $currentVersion + 1; $version <= $targetVersion; $version++) {
                $upgradeFile = $upgradeDir . "/upgrade_{$version}.php";

                if (file_exists($upgradeFile)) {
                    error_log("SchemaLoader: Executing upgrade {$upgradeFile} for {$component}");

                    if (!$this->loadSchema($upgradeFile, ['from_version' => $version - 1, 'to_version' => $version])) {
                        error_log("SchemaLoader: Upgrade failed at version {$version} for {$component}");
                        return false;
                    }

                    // Update version after successful upgrade
                    $this->updateSchemaVersion($component, $version);
                } else {
                    // If no specific upgrade file exists, just update the version
                    // This allows for version bumps without schema changes
                    $this->updateSchemaVersion($component, $version);
                }
            }

            return true;
        } catch (Exception $e) {
            error_log("SchemaLoader: Error during upgrade for {$component}: " . $e->getMessage());
            return false;
        }
    }
}

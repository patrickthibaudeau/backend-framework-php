<?php

namespace DevFramework\Core\Database;

/**
 * Core Framework Installer
 * Handles installation of essential framework tables that must exist by default
 */
class CoreInstaller
{
    private Database $database;
    private SchemaLoader $schemaLoader;

    public function __construct()
    {
        $this->database = Database::getInstance();
        $this->schemaLoader = new SchemaLoader();
    }

    /**
     * Check if core tables are installed
     */
    public function areCoreTablesInstalled(): bool
    {
        try {
            return $this->tableExists('config_plugins');
        } catch (\Exception $e) {
            // If we can't check, assume they're not installed
            return false;
        }
    }

    /**
     * Install core framework tables if they don't exist
     */
    public function installCoreTablesIfNeeded(): bool
    {
        try {
            // Make sure the database connection is working
            $this->database->connect();

            // Use SchemaLoader to install core tables
            $schemaFile = __DIR__ . '/../db/install.php';
            $success = $this->schemaLoader->loadSchema($schemaFile);

            if ($success) {
                // Update schema version to 1
                $this->schemaLoader->updateSchemaVersion('core', 1);
                error_log('CoreInstaller: Core tables installed successfully via SchemaLoader');
                return true;
            } else {
                error_log('CoreInstaller: Failed to install core tables via SchemaLoader');
                return false;
            }
        } catch (\Exception $e) {
            error_log('CoreInstaller: Failed to install core tables: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Check if a table exists
     */
    private function tableExists(string $tableName): bool
    {
        try {
            $connection = $this->database->getConnection();
            if (!$connection) {
                return false;
            }

            // Use the database's addPrefix method to get the correct table name with prefix
            $prefixedTableName = $this->database->addPrefix($tableName);

            // Use SHOW TABLES to check if table exists
            $stmt = $connection->prepare("SHOW TABLES LIKE ?");
            $stmt->execute([$prefixedTableName]);
            return $stmt->fetchColumn() !== false;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get installation status
     */
    public function getInstallationStatus(): array
    {
        return [
            'config_plugins' => $this->tableExists('config_plugins'),
            'schema_version' => $this->schemaLoader->getSchemaVersion('core')
        ];
    }
}

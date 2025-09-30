<?php

namespace DevFramework\Core\Database;

/**
 * Core Framework Installer
 * Handles installation of essential framework tables that must exist by default
 */
class CoreInstaller
{
    private Database $database;

    public function __construct()
    {
        $this->database = Database::getInstance();
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

            // Install config_plugins table
            if (!$this->tableExists('config_plugins')) {
                $this->createConfigPluginsTable();
                error_log('CoreInstaller: config_plugins table created successfully');
            }

            return true;
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
     * Create the config_plugins table
     */
    private function createConfigPluginsTable(): void
    {
        // Use the database's addPrefix method to get the correct table name with prefix
        $tableName = $this->database->addPrefix('config_plugins');

        $sql = "CREATE TABLE `{$tableName}` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `plugin` varchar(100) NOT NULL COMMENT 'Plugin/module name',
            `name` varchar(100) NOT NULL COMMENT 'Configuration name',
            `value` longtext COMMENT 'Configuration value',
            `timecreated` int(11) NOT NULL DEFAULT 0 COMMENT 'Time created',
            `timemodified` int(11) NOT NULL DEFAULT 0 COMMENT 'Time modified',
            PRIMARY KEY (`id`),
            UNIQUE KEY `plugin_name` (`plugin`, `name`),
            KEY `plugin` (`plugin`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Plugin configuration storage'";

        $connection = $this->database->getConnection();
        $connection->exec($sql);
    }

    /**
     * Get installation status
     */
    public function getInstallationStatus(): array
    {
        return [
            'config_plugins' => $this->tableExists('config_plugins')
        ];
    }
}

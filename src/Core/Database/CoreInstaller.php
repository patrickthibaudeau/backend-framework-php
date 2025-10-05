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
            return $this->tableExists('config_plugins') && $this->tableExists('config');
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

            // Execute the install script directly
            $schemaFile = __DIR__ . '/../db/install.php';
            if (!file_exists($schemaFile)) {
                error_log('CoreInstaller: Core install.php not found');
                return false;
            }

            // Get PDO connection and prefix
            $pdo = $this->database->getConnection();
            $prefix = $_ENV['DB_PREFIX'] ?? 'dev_';

            // Include the install script
            include $schemaFile;

            // Set initial version for core component in config_plugins table
            $pluginsTable = $this->database->addPrefix('config_plugins');
            $stmt = $pdo->prepare("INSERT INTO `{$pluginsTable}` (plugin, name, value, timemodified, timecreated) VALUES (?, 'version', ?, ?, ?) ON DUPLICATE KEY UPDATE value = VALUES(value), timemodified = VALUES(timemodified)");
            $time = time();
            $stmt->execute(['core', '1', $time, $time]);

            error_log('CoreInstaller: Core tables installed successfully and core version set to 1');

            // Now check if we need to run upgrades to get to the latest version
            $this->checkAndRunUpgrades($pdo, $prefix);

            return true;

        } catch (\Exception $e) {
            error_log('CoreInstaller: Failed to install core tables: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Check and run upgrades for the Core component
     */
    private function checkAndRunUpgrades($pdo, $prefix): void
    {
        try {
            // Get current version from config_plugins
            $pluginsTable = $this->database->addPrefix('config_plugins');
            $stmt = $pdo->prepare("SELECT value FROM `{$pluginsTable}` WHERE plugin = ? AND name = 'version'");
            $stmt->execute(['core']);
            $currentVersion = $stmt->fetchColumn();

            // Load Core version file to get target version (if it exists)
            $coreVersionFile = __DIR__ . '/../version.php';
            $targetVersion = '1'; // Default fallback

            if (file_exists($coreVersionFile)) {
                $PLUGIN = new \stdClass();
                include $coreVersionFile;
                $targetVersion = $PLUGIN->version ?? '1';
            }

            error_log("CoreInstaller: Core version check after install - current: {$currentVersion}, target: {$targetVersion}");

            // Check if we need to upgrade
            if (version_compare($currentVersion, $targetVersion, '<')) {
                error_log("CoreInstaller: Core upgrade needed from {$currentVersion} to {$targetVersion}");

                // Run upgrade script if it exists
                $upgradeFile = __DIR__ . '/../db/upgrade.php';
                if (file_exists($upgradeFile)) {
                    // Provide variables that upgrade script expects
                    $from_version = $currentVersion;
                    $to_version = $targetVersion;

                    include $upgradeFile;

                    // Update version after successful upgrade
                    $stmt = $pdo->prepare("UPDATE `{$pluginsTable}` SET value = ?, timemodified = ? WHERE plugin = ? AND name = 'version'");
                    $stmt->execute([$targetVersion, time(), 'core']);

                    error_log("CoreInstaller: Core upgrade completed successfully to version {$targetVersion}");
                } else {
                    // No upgrade script, just update version
                    $stmt = $pdo->prepare("UPDATE `{$pluginsTable}` SET value = ?, timemodified = ? WHERE plugin = ? AND name = 'version'");
                    $stmt->execute([$targetVersion, time(), 'core']);
                    error_log("CoreInstaller: Core version updated to {$targetVersion} (no upgrade script)");
                }
            } else {
                error_log("CoreInstaller: Core is already at target version {$currentVersion}");
            }

        } catch (\Exception $e) {
            error_log("CoreInstaller: Core upgrade check error: " . $e->getMessage());
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
            'config' => $this->tableExists('config'),
            'core_tables_installed' => $this->areCoreTablesInstalled()
        ];
    }
}

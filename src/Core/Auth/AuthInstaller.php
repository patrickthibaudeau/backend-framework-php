<?php

namespace DevFramework\Core\Auth;

use DevFramework\Core\Database\Database;
use DevFramework\Core\Database\DatabaseException;
use PDO;
use Exception;

/**
 * Authentication Database Installer - Creates and manages auth-related tables
 */
class AuthInstaller
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Install authentication tables
     */
    public function install(): bool
    {
        try {
            error_log("Framework: AuthInstaller->install() called");

            // Load and execute the install schema directly
            $schemaFile = __DIR__ . '/db/install.php';
            if (!file_exists($schemaFile)) {
                error_log("Framework: Auth install.php not found");
                return false;
            }

            // Execute the install script directly
            $pdo = $this->db->getConnection();

            // Get the table prefix from environment (same as helpers.php)
            $prefix = $_ENV['DB_PREFIX'] ?? 'dev_';

            // Include the install script
            include $schemaFile;

            // Set initial version for core_auth in config_plugins table
            $pluginsTable = $this->db->addPrefix('config_plugins');
            $stmt = $pdo->prepare("INSERT INTO `{$pluginsTable}` (plugin, name, value, timemodified, timecreated) VALUES (?, 'version', ?, ?, ?) ON DUPLICATE KEY UPDATE value = VALUES(value), timemodified = VALUES(timemodified)");
            $time = time();
            $stmt->execute(['core_auth', '1', $time, $time]);

            error_log("Framework: Auth tables installed successfully and core_auth version set to 1");
            return true;

        } catch (Exception $e) {
            error_log("Auth installation failed: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());
            return false;
        }
    }

    /**
     * Uninstall authentication tables (for development/testing)
     */
    public function uninstall(): bool
    {
        try {
            $this->db->execute("DROP TABLE IF EXISTS user_sessions");
            $this->db->execute("DROP TABLE IF EXISTS users");
            return true;
        } catch (DatabaseException $e) {
            error_log("Auth uninstallation failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Check if authentication tables exist and have default users
     */
    public function isInstalled(): bool
    {
        try {
            error_log("Framework: AuthInstaller->isInstalled() called");

            // First check if the users table actually exists using a more reliable method
            $connection = $this->db->getConnection();
            $tableName = $this->db->addPrefix('users');

            $stmt = $connection->prepare("SHOW TABLES LIKE ?");
            $stmt->execute([$tableName]);
            $tableExists = $stmt->fetchColumn() !== false;

            error_log("Framework: AuthInstaller->isInstalled() - users table exists: " . ($tableExists ? 'true' : 'false'));

            if (!$tableExists) {
                return false;
            }

            // If table exists, check if it has users
            $userCount = $this->db->count_records('users');
            error_log("Framework: AuthInstaller->isInstalled() - user count: {$userCount}");

            // Consider it "installed" only if the table exists AND has users
            $isInstalled = $userCount > 0;
            error_log("Framework: AuthInstaller->isInstalled() returning: " . ($isInstalled ? 'true' : 'false'));
            return $isInstalled;
        } catch (Exception $e) {
            error_log("Framework: AuthInstaller->isInstalled() exception: " . $e->getMessage());
            // If we get any exception, return false to trigger installation
            return false;
        }
    }

    /**
     * Get installation status including schema version
     */
    public function getInstallationStatus(): array
    {
        try {
            $userCount = $this->db->count_records('users');
            return [
                'users_table_exists' => $userCount >= 0,
                'has_users' => $userCount > 0,
                'user_count' => $userCount,
                'schema_version' => 1 // Since we directly set it in config_plugins, we can return 1 here
            ];
        } catch (DatabaseException $e) {
            return [
                'users_table_exists' => false,
                'has_users' => false,
                'user_count' => 0,
                'schema_version' => 0
            ];
        }
    }
}

<?php

namespace DevFramework\Core\Auth;

use DevFramework\Core\Database\Database;
use DevFramework\Core\Database\DatabaseException;
use DevFramework\Core\Database\SchemaLoader;
use PDO;
use Exception;

/**
 * Authentication Database Installer - Creates and manages auth-related tables
 */
class AuthInstaller
{
    private Database $db;
    private SchemaLoader $schemaLoader;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->schemaLoader = new SchemaLoader();
    }

    /**
     * Install authentication tables
     */
    public function install(): bool
    {
        try {
            error_log("Framework: AuthInstaller->install() called");

            // Use SchemaLoader to install auth tables and default users
            $schemaFile = __DIR__ . '/db/install.php';
            $success = $this->schemaLoader->loadSchema($schemaFile);

            if ($success) {
                // Update schema version to 1
                $this->schemaLoader->updateSchemaVersion('auth', 1);
                error_log("Framework: Auth tables installed successfully via SchemaLoader");
                return true;
            } else {
                error_log("Framework: Failed to install auth tables via SchemaLoader");
                return false;
            }
        } catch (Exception $e) {
            error_log("Auth installation failed: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());
            return false;
        }
    }

    /**
     * @deprecated Use SchemaLoader instead. Kept for backward compatibility.
     * Create the users table with all required fields - matches User module schema exactly
     */
    private function createUsersTable(): void
    {
        // Get the connection to use the correct prefixed table name
        $connection = $this->db->getConnection();
        $prefix = $_ENV['DB_PREFIX'] ?? 'dev_';
        $tableName = $prefix . 'users';

        $sql = "
            CREATE TABLE IF NOT EXISTS `{$tableName}` (
                id INT(11) NOT NULL AUTO_INCREMENT COMMENT 'Unique user ID',
                auth VARCHAR(100) NOT NULL COMMENT 'Authentication type (e.g., manual, ldap, oauth, saml2)',
                username VARCHAR(100) NOT NULL COMMENT 'Unique username',
                email VARCHAR(255) NOT NULL COMMENT 'User email address',
                password VARCHAR(255) NOT NULL COMMENT 'Hashed password',
                firstname VARCHAR(255) NULL COMMENT 'User first name',
                lastname VARCHAR(255) NULL COMMENT 'User last name',
                idnumber VARCHAR(255) NULL COMMENT 'An id number',
                status VARCHAR(20) NOT NULL DEFAULT 'active' COMMENT 'User status: active, inactive, suspended',
                emailverified BOOLEAN NOT NULL DEFAULT 0 COMMENT 'Whether email is verified',
                lastlogin INT(11) NULL DEFAULT NULL COMMENT 'Unix timestamp of last login',
                timecreated INT(11) NOT NULL DEFAULT 0 COMMENT 'Unix timestamp when user was created',
                timemodified INT(11) NOT NULL DEFAULT 0 COMMENT 'Unix timestamp when user was last modified',
                PRIMARY KEY (id),
                INDEX username (username),
                INDEX email (email),
                INDEX status (status),
                INDEX timecreated (timecreated),
                UNIQUE KEY username_unique (username),
                UNIQUE KEY email_unique (email)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ";

        $connection->exec($sql);
        error_log("Framework: Created table '{$tableName}'");
    }

    /**
     * @deprecated Use SchemaLoader instead. Kept for backward compatibility.
     * Create user sessions table - matches User module schema exactly
     */
    private function createUserSessionsTable(): void
    {
        // Get the connection to use the correct prefixed table name
        $connection = $this->db->getConnection();
        $prefix = $_ENV['DB_PREFIX'] ?? 'dev_';
        $tableName = $prefix . 'user_sessions';

        $sql = "
            CREATE TABLE IF NOT EXISTS `{$tableName}` (
                id VARCHAR(128) NOT NULL COMMENT 'Session ID',
                userid INT(11) NULL COMMENT 'User ID (null for anonymous sessions)',
                ip_address VARCHAR(45) NULL COMMENT 'IP address (supports IPv6)',
                user_agent TEXT NULL COMMENT 'User agent string',
                data LONGTEXT NULL COMMENT 'Serialized session data',
                expires_at INT(11) NOT NULL DEFAULT 0 COMMENT 'Unix timestamp when session expires',
                timecreated INT(11) NOT NULL DEFAULT 0,
                timemodified INT(11) NOT NULL DEFAULT 0,
                PRIMARY KEY (id),
                INDEX userid (userid),
                INDEX expires_at (expires_at),
                INDEX ip_address (ip_address)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ";

        $connection->exec($sql);
        error_log("Framework: Created table '{$tableName}'");
    }

    /**
     * @deprecated Use SchemaLoader instead. Kept for backward compatibility.
     * Force insert default users - bypasses all checks
     */
    private function forceInsertDefaultUsers(): void
    {
        try {
            error_log("Framework: Checking if users exist before force insert...");

            // Use raw SQL to double-check user count with correct table name
            $connection = $this->db->getConnection();
            $prefix = $_ENV['DB_PREFIX'] ?? 'dev_';
            $tableName = $prefix . 'users';

            $stmt = $connection->prepare("SELECT COUNT(*) as count FROM `{$tableName}`");
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $userCount = (int)$result['count'];

            error_log("Framework: Current user count in '{$tableName}' (raw SQL): {$userCount}");

            if ($userCount === 0) {
                $currentTime = time();

                error_log("Framework: Creating default users in '{$tableName}' (force mode)...");

                // Create default admin user using raw SQL with correct table name
                $hashedPassword = password_hash('admin123', PASSWORD_DEFAULT);
                $adminSql = "INSERT INTO `{$tableName}` (auth, username, email, password, firstname, lastname, status, emailverified, timecreated, timemodified) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = $connection->prepare($adminSql);
                $stmt->execute([
                    'manual',
                    'admin',
                    'admin@example.com',
                    $hashedPassword,
                    'System',
                    'Administrator',
                    'active',
                    1,
                    $currentTime,
                    $currentTime
                ]);
                $adminId = $connection->lastInsertId();
                error_log("Framework: Created admin user (ID: {$adminId}) in '{$tableName}' via raw SQL");

                // Create test user using raw SQL with correct table name
                $hashedPassword = password_hash('password123', PASSWORD_DEFAULT);
                $testSql = "INSERT INTO `{$tableName}` (auth, username, email, password, firstname, lastname, status, emailverified, timecreated, timemodified) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = $connection->prepare($testSql);
                $stmt->execute([
                    'manual',
                    'testuser',
                    'test@example.com',
                    $hashedPassword,
                    'Test',
                    'User',
                    'active',
                    1,
                    $currentTime,
                    $currentTime
                ]);
                $testId = $connection->lastInsertId();
                error_log("Framework: Created test user (ID: {$testId}) in '{$tableName}' via raw SQL");

                // Verify users were created
                $stmt = $connection->prepare("SELECT COUNT(*) as count FROM `{$tableName}`");
                $stmt->execute();
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                $finalCount = (int)$result['count'];
                error_log("Framework: Final user count in '{$tableName}' after creation: {$finalCount}");

                // Also verify by listing the users
                $stmt = $connection->prepare("SELECT id, username, email FROM `{$tableName}`");
                $stmt->execute();
                $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
                foreach ($users as $user) {
                    error_log("Framework: User created - ID: {$user['id']}, Username: {$user['username']}, Email: {$user['email']}");
                }

                error_log("Framework: Default users created successfully in '{$tableName}'");
            } else {
                error_log("Framework: Users already exist in '{$tableName}' ({$userCount} found), skipping default user creation");
            }
        } catch (Exception $e) {
            error_log("Framework: Error in forceInsertDefaultUsers: " . $e->getMessage());
            error_log("Framework: Stack trace: " . $e->getTraceAsString());
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
                'schema_version' => $this->schemaLoader->getSchemaVersion('auth')
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

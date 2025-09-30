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

            // Step 1: Create tables
            error_log("Framework: Creating users table...");
            $this->createUsersTable();
            error_log("Framework: Users table created");

            error_log("Framework: Creating user sessions table...");
            $this->createUserSessionsTable();
            error_log("Framework: User sessions table created");

            // Step 2: Force insert default users (don't rely on isInstalled check)
            error_log("Framework: Force inserting default users...");
            $this->forceInsertDefaultUsers();

            return true;
        } catch (Exception $e) {
            error_log("Auth installation failed: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());
            return false;
        }
    }

    /**
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
            // Check if users table exists and has at least one user
            $userCount = $this->db->count_records('users');

            // Consider it "installed" only if the table exists AND has users
            return $userCount > 0;
        } catch (DatabaseException $e) {
            // If we get an exception (table doesn't exist), return false
            return false;
        }
    }
}

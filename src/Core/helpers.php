<?php

use DevFramework\Core\Config\Configuration;
use DevFramework\Core\Database\DatabaseFactory;
use DevFramework\Core\Module\ModuleHelper;
use DevFramework\Core\Module\ModuleManager;
use DevFramework\Core\Module\LanguageManager;
use DevFramework\Core\Notifications\NotificationManager; // Added for notification system
use DevFramework\Core\Output\Output; // Added for Mustache output system

// Load database constants first, then module constants
require_once __DIR__ . '/Database/constants.php';
require_once __DIR__ . '/Module/constants.php';

// --- Early Mustache deprecation suppression (before any Mustache class is autoloaded) ---
if (!function_exists('__df_install_mustache_deprecation_handler')) {
    function __df_install_mustache_deprecation_handler(): void
    {
        static $installed = false;
        if ($installed) { return; }
        $env = getenv('MUSTACHE_SUPPRESS_DEPRECATIONS');
        $suppress = true; // default on
        if ($env !== false) {
            $norm = strtolower(trim($env));
            if (in_array($norm, ['0','false','off','no'], true)) {
                $suppress = false;
            }
        }
        if (!$suppress) { return; }
        $prev = set_error_handler(function($errno, $errstr, $errfile = null, $errline = null) use (&$prev) {
            if (($errno & E_DEPRECATED) && ($errfile && str_contains($errfile, '/mustache/mustache/')) ) {
                return true; // swallow Mustache deprecations
            }
            if (($errno & E_DEPRECATED) && str_contains($errstr, 'Mustache_')) {
                return true;
            }
            if ($prev) { return $prev($errno, $errstr, $errfile, $errline); }
            return false; // normal handling
        });
        $installed = true;
    }
}
__df_install_mustache_deprecation_handler();
// --- End early Mustache deprecation suppression ---

// Define helper functions first before using them
if (!function_exists('config')) {
    /**
     * Get configuration value using dot notation
     *
     * @param string|null $key Configuration key (e.g., 'app.name', 'database.default')
     * @param mixed $default Default value if key not found
     * @return mixed
     */
    function config(?string $key = null, mixed $default = null): mixed
    {
        $config = Configuration::getInstance();

        if ($key === null) {
            return $config->all();
        }

        return $config->get($key, $default);
    }
}

if (!function_exists('env')) {
    /**
     * Get environment variable value
     *
     * @param string $key Environment variable name
     * @param mixed $default Default value if not found
     * @return mixed
     */
    function env(string $key, mixed $default = null): mixed
    {
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);

        if ($value === false) {
            return $default;
        }

        // Convert string representations to appropriate types
        switch (strtolower($value)) {
            case 'true':
            case '(true)':
                return true;
            case 'false':
            case '(false)':
                return false;
            case 'empty':
            case '(empty)':
                return '';
            case 'null':
            case '(null)':
                return null;
        }

        // Handle quoted strings
        if (strlen($value) > 1 && $value[0] === '"' && $value[-1] === '"') {
            return substr($value, 1, -1);
        }

        return $value;
    }
}

if (!function_exists('app_path')) {
    /**
     * Get application path
     *
     * @param string $path Additional path to append
     * @return string
     */
    function app_path(string $path = ''): string
    {
        $appPath = dirname(__DIR__, 2);
        return $path ? $appPath . DIRECTORY_SEPARATOR . ltrim($path, DIRECTORY_SEPARATOR) : $appPath;
    }
}

if (!function_exists('storage_path')) {
    /**
     * Get storage path
     *
     * @param string $path Additional path to append
     * @return string
     */
    function storage_path(string $path = ''): string
    {
        $storagePath = app_path('storage');
        return $path ? $storagePath . DIRECTORY_SEPARATOR . ltrim($path, DIRECTORY_SEPARATOR) : $storagePath;
    }
}

if (!function_exists('logger')) {
    /**
     * Simple logging function
     *
     * @param string $message Log message
     * @param string $level Log level (info, warning, error, debug)
     * @return bool
     */
    function logger(string $message, string $level = 'info'): bool
    {
        $logFile = storage_path('logs/app.log');
        $logDir = dirname($logFile);

        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }

        $timestamp = date('Y-m-d H:i:s');
        $logEntry = "[{$timestamp}] [{$level}] {$message}" . PHP_EOL;

        return file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX) !== false;
    }
}

if (!function_exists('db')) {
    /**
     * Get global database instance
     *
     * @return \DevFramework\Core\Database\Database
     */
    function db(): \DevFramework\Core\Database\Database
    {
        global $DB;

        if (!isset($DB)) {
            DatabaseFactory::createGlobal();
        }

        return $DB;
    }
}

if (!function_exists('is_framework_initialized')) {
    /**
     * Check if the framework is fully initialized
     *
     * @return bool
     */
    function is_framework_initialized(): bool
    {
        // Check if we have a database connection and core tables
        try {
            $db = db();
            $db->connect();

            // Check if core tables exist
            $coreInstaller = new \DevFramework\Core\Database\CoreInstaller();
            return $coreInstaller->areCoreTablesInstalled();
        } catch (Exception $e) {
            return false;
        }
    }
}

if (!function_exists('ensure_framework_initialized')) {
    /**
     * Ensure the framework is fully initialized
     * This function MUST be called at the start of every entry point
     *
     * @return bool
     */
    function ensure_framework_initialized(): bool
    {
        static $coreInitialized = null;

        try {
            // Step 1: Core initialization (only once)
            if ($coreInitialized === null) {
                error_log("Framework: Starting core initialization...");

                // Ensure database exists
                $dbInitializer = new \DevFramework\Core\Database\DatabaseInitializer();
                if (!$dbInitializer->ensureDatabaseExists()) {
                    error_log("Framework: Database creation failed");
                    $coreInitialized = false;
                    return false;
                }

                // Initialize global database connection
                DatabaseFactory::createGlobal();
                error_log("Framework: Database connection established");

                // Install core tables if needed
                $coreInstaller = new \DevFramework\Core\Database\CoreInstaller();
                if (!$coreInstaller->areCoreTablesInstalled()) {
                    error_log("Framework: Installing core tables...");
                    if (!$coreInstaller->installCoreTablesIfNeeded()) {
                        error_log("Framework: Core table installation failed");
                        $coreInitialized = false;
                        return false;
                    }
                    error_log("Framework: Core tables installed successfully");
                }

                // Install authentication tables if needed
                $authInstaller = new \DevFramework\Core\Auth\AuthInstaller();
                if (!$authInstaller->isInstalled()) {
                    error_log("Framework: Installing authentication tables...");
                    if (!$authInstaller->install()) {
                        error_log("Framework: Authentication table installation failed");
                        $coreInitialized = false;
                        return false;
                    }
                    error_log("Framework: Authentication tables installed successfully");
                }

                // Initialize module system
                ModuleHelper::initialize();
                error_log("Framework: Module system initialized");

                $coreInitialized = true;
                error_log("Framework: Core initialization completed");
            } else if ($coreInitialized === false) {
                error_log("Framework: Core initialization previously failed");
                return false;
            }

            // Step 2: Module processing (runs every time to catch upgrades)
            error_log("Framework: Checking modules for installations/upgrades...");

            $moduleInstaller = new \DevFramework\Core\Database\ModuleInstaller();
            $moduleManager = \DevFramework\Core\Module\ModuleManager::getInstance();
            $moduleManager->discoverModules();
            $modules = $moduleManager->getAllModules();

            error_log("Framework: Found " . count($modules) . " modules to check");

            $upgradeNeeded = false;
            foreach ($modules as $moduleName => $moduleInfo) {
                $moduleVersion = $moduleInfo['version'] ?? null;
                if (!$moduleVersion) {
                    continue;
                }

                try {
                    $currentVersion = db()->get_plugin_version($moduleName);

                    if (!$currentVersion) {
                        // Module not installed yet
                        error_log("Framework: Installing module '{$moduleName}' version {$moduleVersion}");
                        if (!$moduleInstaller->installModule($moduleName)) {
                            error_log("Framework: Failed to install module '{$moduleName}'");
                        }
                        $upgradeNeeded = true;
                    } else if (version_compare($moduleVersion, $currentVersion, '>')) {
                        // Module needs upgrade
                        error_log("Framework: Upgrading module '{$moduleName}' from {$currentVersion} to {$moduleVersion}");
                        if (!$moduleInstaller->upgradeModule($moduleName, $currentVersion, $moduleVersion)) {
                            error_log("Framework: Failed to upgrade module '{$moduleName}'");
                        }
                        $upgradeNeeded = true;
                    }
                } catch (Exception $moduleError) {
                    error_log("Framework: Error processing module '{$moduleName}': " . $moduleError->getMessage());
                }
            }

            if ($upgradeNeeded) {
                error_log("Framework: Module installations/upgrades completed");
            } else {
                error_log("Framework: All modules are up to date");
            }

            return true;

        } catch (Exception $e) {
            error_log("Framework: Critical initialization failure: " . $e->getMessage());
            $coreInitialized = false;
            return false;
        }
    }
}

if (!function_exists('ensure_framework_initialized_safe')) {
    /**
     * Safe framework initialization that avoids circular dependencies
     * This function preserves module upgrade functionality while preventing maintenance mode loops
     *
     * @return bool
     */
    function ensure_framework_initialized_safe(): bool
    {
        static $initializationResult = null;

        // Return cached result to avoid repeated expensive operations
        if ($initializationResult !== null) {
            return $initializationResult;
        }

        try {
            // Step 1: Ensure database exists using DatabaseInitializer
            try {
                $dbInitializer = new \DevFramework\Core\Database\DatabaseInitializer();
                if (!$dbInitializer->ensureDatabaseExists()) {
                    error_log("Framework: Database creation failed");
                    $initializationResult = false;
                    return false;
                }
                error_log("Framework: Database exists and is accessible");
            } catch (Exception $e) {
                error_log("Framework: Database initialization error: " . $e->getMessage());
                $initializationResult = false;
                return false;
            }

            // Step 2: Basic database connectivity check using direct PDO
            $host = $_ENV['DB_HOST'] ?? 'mysql';
            $database = $_ENV['DB_DATABASE'] ?? 'devframework';
            $username = $_ENV['DB_USERNAME'] ?? 'devframework';
            $password = $_ENV['DB_PASSWORD'] ?? 'devframework';
            $prefix = $_ENV['DB_PREFIX'] ?? 'dev_';

            $pdo = new PDO("mysql:host={$host};dbname={$database}", $username, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]);

            // Step 3: Check and create core tables if needed
            $coreTable = $prefix . 'config';
            $pluginsTable = $prefix . 'config_plugins';

            $stmt = $pdo->prepare("SHOW TABLES LIKE ?");
            $stmt->execute([$coreTable]);
            $coreTableExists = $stmt->fetchColumn() !== false;

            $stmt->execute([$pluginsTable]);
            $pluginsTableExists = $stmt->fetchColumn() !== false;

            if (!$coreTableExists || !$pluginsTableExists) {
                error_log("Framework: Core tables missing, creating them...");

                // Create core config table
                if (!$coreTableExists) {
                    $sql = "CREATE TABLE `$coreTable` (
                        id int(10) NOT NULL AUTO_INCREMENT,
                        name varchar(255) NOT NULL DEFAULT '',
                        value longtext,
                        timemodified int(10) DEFAULT NULL,
                        timecreated int(10) DEFAULT NULL,
                        PRIMARY KEY (id),
                        UNIQUE KEY name (name)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
                    $pdo->exec($sql);
                    error_log("Framework: Core config table created");
                }

                // Create plugins config table
                if (!$pluginsTableExists) {
                    $sql = "CREATE TABLE `$pluginsTable` (
                        id int(11) NOT NULL AUTO_INCREMENT,
                        plugin varchar(100) NOT NULL DEFAULT '',
                        name varchar(100) NOT NULL DEFAULT '',
                        value longtext,
                        timecreated int(11) NOT NULL DEFAULT 0,
                        timemodified int(11) NOT NULL DEFAULT 0,
                        PRIMARY KEY (id),
                        UNIQUE KEY plugin_name (plugin, name),
                        KEY plugin (plugin)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
                    $pdo->exec($sql);
                    error_log("Framework: Plugins config table created");
                }
            }

            // Step 4: Initialize database connection through framework (now that core tables exist)
            DatabaseFactory::createGlobal();

            // CRITICAL FIX: Ensure the Database instance has the correct prefix
            // This fixes the issue where schema_versions table was created without prefix
            global $DB;
            if (isset($DB)) {
                // Force the Database instance to use the correct prefix
                $reflection = new ReflectionClass($DB);
                $prefixProperty = $reflection->getProperty('tablePrefix');
                $prefixProperty->setAccessible(true);
                $prefixProperty->setValue($DB, $prefix);
                error_log("Framework: Database prefix explicitly set to '{$prefix}'");
            }

            // Step 4.5: Check if auth tables exist directly, bypass AuthInstaller caching issues
            $usersTable = $prefix . 'users';
            $sessionsTable = $prefix . 'user_sessions';

            $stmt = $pdo->prepare("SHOW TABLES LIKE ?");
            $stmt->execute([$usersTable]);
            $usersTableExists = $stmt->fetchColumn() !== false;

            $stmt->execute([$sessionsTable]);
            $sessionsTableExists = $stmt->fetchColumn() !== false;

            $hasUsers = false;
            if ($usersTableExists) {
                $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM `{$usersTable}`");
                $stmt->execute();
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                $hasUsers = (int)$result['count'] > 0;
            }

            // Force auth installation if tables don't exist or no users
            if (!$usersTableExists || !$sessionsTableExists || !$hasUsers) {
                error_log("Framework: Auth tables missing or empty - forcing installation");
                error_log("Framework: Users table exists: " . ($usersTableExists ? 'true' : 'false'));
                error_log("Framework: Sessions table exists: " . ($sessionsTableExists ? 'true' : 'false'));
                error_log("Framework: Has users: " . ($hasUsers ? 'true' : 'false'));

                try {
                    $authInstaller = new \DevFramework\Core\Auth\AuthInstaller();
                    $installResult = $authInstaller->install();
                    error_log("Framework: Force auth install result: " . ($installResult ? 'true' : 'false'));
                } catch (Exception $e) {
                    error_log("Framework: Force auth installation error: " . $e->getMessage());
                    error_log("Framework: Exception stack trace: " . $e->getTraceAsString());
                }
            } else {
                error_log("Framework: Auth tables already exist and populated");

                // Check if Auth component needs upgrade using config_plugins table
                try {
                    // Check current version for core_auth in config_plugins table
                    $stmt = $pdo->prepare("SELECT value FROM `$pluginsTable` WHERE plugin = ? AND name = 'version'");
                    $stmt->execute(['core_auth']);
                    $currentAuthVersion = $stmt->fetchColumn();

                    if (!$currentAuthVersion) {
                        // No version recorded, set initial version
                        $currentAuthVersion = '1';
                        $stmt = $pdo->prepare("INSERT INTO `$pluginsTable` (plugin, name, value, timemodified, timecreated) VALUES (?, 'version', ?, ?, ?)");
                        $time = time();
                        $stmt->execute(['core_auth', $currentAuthVersion, $time, $time]);
                        error_log("Framework: Set initial core_auth version to {$currentAuthVersion}");
                    }

                    // Load Auth version file to get target version
                    $authVersionFile = dirname(__DIR__, 2) . '/src/Core/Auth/version.php';
                    $targetVersion = '1'; // Default fallback

                    if (file_exists($authVersionFile)) {
                        $PLUGIN = new stdClass();
                        include $authVersionFile;
                        $targetVersion = $PLUGIN->version ?? '1';
                    }

                    error_log("Framework: Auth version check - current: {$currentAuthVersion}, target: {$targetVersion}");

                    if (version_compare($currentAuthVersion, $targetVersion, '<')) {
                        error_log("Framework: Auth upgrade needed from {$currentAuthVersion} to {$targetVersion}");

                        // Run upgrade script if it exists
                        $upgradeFile = dirname(__DIR__, 2) . '/src/Core/Auth/db/upgrade.php';
                        if (file_exists($upgradeFile)) {
                            try {
                                // Provide variables that upgrade script expects
                                $from_version = $currentAuthVersion;
                                $to_version = $targetVersion;

                                include $upgradeFile;

                                // Update version after successful upgrade
                                $stmt = $pdo->prepare("UPDATE `$pluginsTable` SET value = ?, timemodified = ? WHERE plugin = ? AND name = 'version'");
                                $stmt->execute([$targetVersion, time(), 'core_auth']);

                                error_log("Framework: Auth upgrade completed successfully to version {$targetVersion}");
                            } catch (Exception $e) {
                                error_log("Framework: Auth upgrade script failed: " . $e->getMessage());
                            }
                        } else {
                            // No upgrade script, just update version
                            $stmt = $pdo->prepare("UPDATE `$pluginsTable` SET value = ?, timemodified = ? WHERE plugin = ? AND name = 'version'");
                            $stmt->execute([$targetVersion, time(), 'core_auth']);
                            error_log("Framework: Auth version updated to {$targetVersion} (no upgrade script)");
                        }
                    } else {
                        error_log("Framework: Auth is up to date (version {$currentAuthVersion})");
                    }
                } catch (Exception $e) {
                    error_log("Framework: Auth upgrade check error: " . $e->getMessage());
                }
            }

            // Step 5: Module installation and upgrade detection
            $modulesDir = dirname(__DIR__, 2) . '/src/modules';
            if (!is_dir($modulesDir)) {
                error_log("Framework: Modules directory not found, skipping module setup");
                $initializationResult = true;
                return true;
            }

            // Define maturity constants for module version files
            if (!defined('MATURITY_ALPHA')) define('MATURITY_ALPHA', 'MATURITY_ALPHA');
            if (!defined('MATURITY_BETA')) define('MATURITY_BETA', 'MATURITY_BETA');
            if (!defined('MATURITY_RC')) define('MATURITY_RC', 'MATURITY_RC');
            if (!defined('MATURITY_STABLE')) define('MATURITY_STABLE', 'MATURITY_STABLE');

            $modules = array_filter(glob($modulesDir . '/*'), 'is_dir');
            $installationPerformed = false;

            foreach ($modules as $moduleDir) {
                $moduleName = basename($moduleDir);
                $versionFile = $moduleDir . '/version.php';

                if (!file_exists($versionFile)) {
                    continue;
                }

                try {
                    // Load module version safely
                    $PLUGIN = new stdClass();
                    include $versionFile;
                    $fileVersion = $PLUGIN->version ?? null;

                    if (!$fileVersion) {
                        continue;
                    }

                    // Check database version using direct SQL
                    $stmt = $pdo->prepare("SELECT value FROM `$pluginsTable` WHERE plugin = ? AND name = 'version'");
                    $stmt->execute([$moduleName]);
                    $dbVersion = $stmt->fetchColumn();

                    if (!$dbVersion) {
                        // Module not installed - install it
                        error_log("Framework: Installing module '{$moduleName}' version {$fileVersion}");

                        // Install module tables using schema if install.php exists
                        $installFile = $moduleDir . '/db/install.php';
                        if (file_exists($installFile)) {
                            try {
                                error_log("Framework: Installing database schema for module '{$moduleName}'");

                                // Get schema definition from install.php
                                $schema = include $installFile;

                                if (is_array($schema) && !empty($schema)) {
                                    // Use SchemaBuilder to create tables
                                    $schemaBuilder = new \DevFramework\Core\Database\SchemaBuilder();

                                    foreach ($schema as $tableName => $tableDefinition) {
                                        try {
                                            $schemaBuilder->createTable($tableName, $tableDefinition);
                                            error_log("Framework: Created table '{$tableName}' for module '{$moduleName}'");
                                        } catch (Exception $e) {
                                            error_log("Framework: Failed to create table '{$tableName}' for module '{$moduleName}': " . $e->getMessage());
                                        }
                                    }
                                    error_log("Framework: Module '{$moduleName}' schema installation completed");
                                } else {
                                    error_log("Framework: Module '{$moduleName}' install.php did not return a valid schema array");
                                }
                            } catch (Exception $e) {
                                error_log("Framework: Module '{$moduleName}' install failed: " . $e->getMessage());
                            }
                        } else {
                            error_log("Framework: Module '{$moduleName}' has no install.php file");
                        }

                        // Set module version in database AFTER successful table creation
                        $stmt = $pdo->prepare("INSERT INTO `$pluginsTable` (plugin, name, value, timemodified, timecreated) VALUES (?, 'version', ?, ?, ?)");
                        $time = time();
                        $stmt->execute([$moduleName, $fileVersion, $time, $time]);
                        error_log("Framework: Module '{$moduleName}' version {$fileVersion} recorded in database");

                        $installationPerformed = true;
                    } else if (version_compare($fileVersion, $dbVersion, '>')) {
                        // Module needs upgrade
                        error_log("Framework: Upgrading module '{$moduleName}' from {$dbVersion} to {$fileVersion}");
                        $stmt = $pdo->prepare("UPDATE `$pluginsTable` SET value = ?, timemodified = ? WHERE plugin = ? AND name = 'version'");
                        $stmt->execute([$fileVersion, time(), $moduleName]);

                        // Run upgrade script if it exists
                        $upgradeFile = $moduleDir . '/db/upgrade.php';
                        if (file_exists($upgradeFile)) {
                            try {
                                // Provide variables that upgrade scripts expect
                                $from_version = $dbVersion;
                                $to_version = $fileVersion;

                                include $upgradeFile;
                                error_log("Framework: Module '{$moduleName}' upgrade script executed");
                            } catch (Exception $e) {
                                error_log("Framework: Module '{$moduleName}' upgrade script failed: " . $e->getMessage());
                            }
                        }
                        $installationPerformed = true;
                    }
                } catch (Exception $e) {
                    error_log("Framework: Error processing module '{$moduleName}': " . $e->getMessage());
                }
            }

            if ($installationPerformed) {
                error_log("Framework: Module installations/upgrades completed successfully");
            }

            $initializationResult = true;
            return true;

        } catch (Exception $e) {
            error_log("Framework: Safe initialization failed: " . $e->getMessage());
            $initializationResult = false;
            return false;
        }
    }
}

// BOOTSTRAP: Ensure framework is initialized on every request
// This is the critical piece that ensures database setup works across all entry points
// FIXED: Modified to avoid circular dependencies while preserving upgrade functionality
$initResult = ensure_framework_initialized_safe();

// If initialization failed and this is not a CLI request, show maintenance page
if (!$initResult && php_sapi_name() !== 'cli') {
    http_response_code(503);
    header('Content-Type: text/html; charset=utf-8');

    $maintenanceHtml = '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Maintenance</title>
    <style>
        body { font-family: Arial, sans-serif; text-align: center; padding: 50px; background: #f5f5f5; }
        .container { max-width: 600px; margin: 0 auto; background: white; padding: 40px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #e74c3c; margin-bottom: 20px; }
        p { color: #666; line-height: 1.6; }
        .details { background: #f8f9fa; padding: 15px; border-radius: 5px; margin: 20px 0; text-align: left; }
        .retry { margin-top: 20px; }
        .retry a { background: #3498db; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; }
        .retry a:hover { background: #2980b9; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔧 System Maintenance</h1>
        <p>The system is currently being set up or updated. This usually takes just a few moments.</p>
        <div class="details">
            <strong>What\'s happening:</strong><br>
            • Creating database if needed<br>
            • Installing core tables<br>
            • Setting up modules<br>
            • Configuring system components
        </div>
        <p>If this message persists, please check the application logs or contact your system administrator.</p>
        <div class="retry">
            <a href="javascript:window.location.reload()">Try Again</a>
        </div>
    </div>
</body>
</html>';

    echo $maintenanceHtml;
    exit;
}

if (!function_exists('db_get_record')) {
    /**
     * Get a single database record
     *
     * @param string $table Table name
     * @param array $conditions WHERE conditions
     * @param string $sort ORDER BY clause
     * @param string $fields Fields to select
     * @param int $strictness IGNORE_MISSING or MUST_EXIST
     * @return object|null
     */
    function db_get_record(string $table, array $conditions = [], string $sort = '', string $fields = '*', int $strictness = IGNORE_MISSING): ?object
    {
        return db()->get_record($table, $conditions, $sort, $fields, $strictness);
    }
}

if (!function_exists('db_get_records')) {
    /**
     * Get multiple database records
     *
     * @param string $table Table name
     * @param array $conditions WHERE conditions
     * @param string $sort ORDER BY clause
     * @param string $fields Fields to select
     * @param int $limitfrom OFFSET
     * @param int $limitnum LIMIT
     * @return array
     */
    function db_get_records(string $table, array $conditions = [], string $sort = '', string $fields = '*', int $limitfrom = 0, int $limitnum = 0): array
    {
        return db()->get_records($table, $conditions, $sort, $fields, $limitfrom, $limitnum);
    }
}

if (!function_exists('db_insert_record')) {
    /**
     * Insert a database record
     *
     * @param string $table Table name
     * @param object|array $data Record data
     * @param bool $returnId Whether to return the inserted ID
     * @return int|bool
     */
    function db_insert_record(string $table, object|array $data, bool $returnId = true): int|bool
    {
        return db()->insert_record($table, $data, $returnId);
    }
}

if (!function_exists('db_update_record')) {
    /**
     * Update a database record
     *
     * @param string $table Table name
     * @param object|array $data Record data (must include 'id')
     * @return bool
     */
    function db_update_record(string $table, object|array $data): bool
    {
        return db()->update_record($table, $data);
    }
}

if (!function_exists('db_delete_records')) {
    /**
     * Delete database records
     *
     * @param string $table Table name
     * @param array $conditions WHERE conditions
     * @return bool
     */
    function db_delete_records(string $table, array $conditions): bool
    {
        return db()->delete_records($table, $conditions);
    }
}

if (!function_exists('db_count_records')) {
    /**
     * Count database records
     *
     * @param string $table Table name
     * @param array $conditions WHERE conditions
     * @return int
     */
    function db_count_records(string $table, array $conditions = []): int
    {
        return db()->count_records($table, $conditions);
    }
}

if (!function_exists('module_lang')) {
    /**
     * Get a language string from a module
     *
     * @param string $moduleName Module name
     * @param string $key Language string key
     * @param array $params Parameters for string formatting
     * @param string|null $language Language code (defaults to current language)
     * @return string
     */
    function module_lang(string $moduleName, string $key, array $params = [], ?string $language = null): string
    {
        return ModuleHelper::get_string($moduleName, $key, $params, $language);
    }
}

if (!function_exists('is_module_loaded')) {
    /**
     * Check if a module is loaded
     *
     * @param string $moduleName Module name
     * @return bool
     */
    function is_module_loaded(string $moduleName): bool
    {
        return ModuleHelper::isModuleAvailable($moduleName);
    }
}

if (!function_exists('get_module_info')) {
    /**
     * Get module information
     *
     * @param string $moduleName Module name
     * @return array|null
     */
    function get_module_info(string $moduleName): ?array
    {
        return ModuleHelper::getModuleInfo($moduleName);
    }
}

if (!function_exists('list_modules')) {
    /**
     * List all available modules
     *
     * @return array
     */
    function list_modules(): array
    {
        return ModuleHelper::listModules();
    }
}

if (!function_exists('modules_path')) {
    /**
     * Get modules path
     *
     * @param string $path Additional path to append
     * @return string
     */
    function modules_path(string $path = ''): string
    {
        $modulesPath = app_path('modules');
        return $path ? $modulesPath . DIRECTORY_SEPARATOR . ltrim($path, DIRECTORY_SEPARATOR) : $modulesPath;
    }
}

if (!function_exists('get_string')) {
    /**
     * Get a language string from a module (global helper)
     *
     * @param string $moduleName The module containing the language strings
     * @param string $key The language key to retrieve
     * @param array $params Array of parameters for string interpolation
     * @param string|null $language Optional specific language code (uses default if null)
     * @return string
     */
    function get_string(string $moduleName, string $key, array $params = [], ?string $language = null): string
    {
        return ModuleHelper::get_string($moduleName, $key, $params, $language);
    }
}

if (!function_exists('get_config')) {
    /**
     * Get plugin configuration value
     *
     * @param string $plugin Plugin name
     * @param string $name Configuration name
     * @param mixed $default Default value if not found
     * @return mixed
     */
    function get_config(string $plugin, string $name, mixed $default = null): mixed
    {
        return db()->get_config($plugin, $name, $default);
    }
}

if (!function_exists('set_config')) {
    /**
     * Set plugin configuration value
     *
     * @param string $plugin Plugin name
     * @param string $name Configuration name
     * @param mixed $value Configuration value
     * @return bool
     */
    function set_config(string $plugin, string $name, mixed $value): bool
    {
        return db()->set_config($plugin, $name, $value);
    }
}

if (!function_exists('get_plugin_version')) {
    /**
     * Get plugin version
     *
     * @param string $plugin Plugin name
     * @return string|null
     */
    function get_plugin_version(string $plugin): ?string
    {
        return db()->get_plugin_version($plugin);
    }
}

if (!function_exists('set_plugin_version')) {
    /**
     * Set plugin version
     *
     * @param string $plugin Plugin name
     * @param string $version Version string
     * @return bool
     */
    function set_plugin_version(string $plugin, string $version): bool
    {
        return db()->set_plugin_version($plugin, $version);
    }
}

if (!function_exists('get_plugin_configs')) {
    /**
     * Get all configurations for a plugin
     *
     * @param string $plugin Plugin name
     * @return array
     */
    function get_plugin_configs(string $plugin): array
    {
        return db()->get_plugin_configs($plugin);
    }
}

if (!function_exists('unset_config')) {
    /**
     * Delete plugin configuration
     *
     * @param string $plugin Plugin name
     * @param string $name Configuration name
     * @return bool
     */
    function unset_config(string $plugin, string $name): bool
    {
        return db()->unset_config($plugin, $name);
    }
}

if (!function_exists('unset_plugin_configs')) {
    /**
     * Delete all configurations for a plugin
     *
     * @param string $plugin Plugin name
     * @return bool
     */
    function unset_plugin_configs(string $plugin): bool
    {
        return db()->unset_plugin_configs($plugin);
    }
}

if (!function_exists('install_module_database')) {
    /**
     * Install database schema for a module
     *
     * @param string $moduleName Module name
     * @return bool
     */
    function install_module_database(string $moduleName): bool
    {
        $installer = new \DevFramework\Core\Database\ModuleInstaller();
        return $installer->installModule($moduleName);
    }
}

if (!function_exists('upgrade_module_database')) {
    /**
     * Upgrade database schema for a module
     *
     * @param string $moduleName Module name
     * @param string $fromVersion Current version
     * @param string $toVersion Target version
     * @return bool
     */
    function upgrade_module_database(string $moduleName, string $fromVersion, string $toVersion): bool
    {
        $installer = new \DevFramework\Core\Database\ModuleInstaller();
        return $installer->upgradeModule($moduleName, $fromVersion, $toVersion);
    }
}

if (!function_exists('uninstall_module_database')) {
    /**
     * Uninstall database schema for a module
     *
     * @param string $moduleName Module name
     * @return bool
     */
    function uninstall_module_database(string $moduleName): bool
    {
        $installer = new \DevFramework\Core\Database\ModuleInstaller();
        return $installer->uninstallModule($moduleName);
    }
}

if (!function_exists('install_all_module_databases')) {
    /**
     * Install/upgrade database schemas for all modules
     *
     * @return array Results array with module names and status
     */
    function install_all_module_databases(): array
    {
        $installer = new \DevFramework\Core\Database\ModuleInstaller();
        return $installer->installAllModules();
    }
}

if (!function_exists('create_table_from_schema')) {
    /**
     * Create a table from schema definition
     *
     * @param string $tableName Table name
     * @param array $schema Table schema definition
     * @return bool
     */
    function create_table_from_schema(string $tableName, array $schema): bool
    {
        $builder = new \DevFramework\Core\Database\SchemaBuilder();
        return $builder->createTable($tableName, $schema);
    }
}

if (!function_exists('enable_maintenance_mode')) {
    /**
     * Enable maintenance mode
     *
     * @param string $reason Reason for maintenance
     * @param int|null $duration Estimated duration in seconds
     * @return bool
     */
    function enable_maintenance_mode(string $reason = 'System maintenance', ?int $duration = null): bool
    {
        $maintenance = new \DevFramework\Core\Maintenance\MaintenanceMode();
        return $maintenance->enable($reason, $duration);
    }
}

if (!function_exists('disable_maintenance_mode')) {
    /**
     * Disable maintenance mode
     *
     * @return bool
     */
    function disable_maintenance_mode(): bool
    {
        $maintenance = new \DevFramework\Core\Maintenance\MaintenanceMode();
        return $maintenance->disable();
    }
}

if (!function_exists('is_maintenance_mode')) {
    /**
     * Check if maintenance mode is enabled
     *
     * @return bool
     */
    function is_maintenance_mode(): bool
    {
        $maintenance = new \DevFramework\Core\Maintenance\MaintenanceMode();
        return $maintenance->isEnabled();
    }
}

if (!function_exists('get_maintenance_info')) {
    /**
     * Get maintenance mode information
     *
     * @return array|null
     */
    function get_maintenance_info(): ?array
    {
        $maintenance = new \DevFramework\Core\Maintenance\MaintenanceMode();
        return $maintenance->getInfo();
    }
}

if (!function_exists('install_core_tables')) {
    /**
     * Install core framework tables (config_plugins and user tables)
     *
     * @return bool
     */
    function install_core_tables(): bool
    {
        $installer = new \DevFramework\Core\Database\CoreInstaller();
        return $installer->installCoreTablesIfNeeded();
    }
}

if (!function_exists('get_core_installation_status')) {
    /**
     * Get the status of core table installation
     *
     * @return array
     */
    function get_core_installation_status(): array
    {
        $installer = new \DevFramework\Core\Database\CoreInstaller();
        return $installer->getInstallationStatus();
    }
}

// --- Notification & Tailwind Helpers (Added) ---
if (!function_exists('notification')) {
    /**
     * Get the global NotificationManager instance
     */
    function notification(): NotificationManager
    {
        return NotificationManager::getInstance();
    }
}

if (!function_exists('render_notifications')) {
    /**
     * Render all queued notifications as Tailwind alert components
     *
     * @param bool $consume Remove notifications after rendering
     * @return string
     */
    function render_notifications(bool $consume = true): string
    {
        return notification()->render($consume);
    }
}

if (!function_exists('tailwind_cdn')) {
    /**
     * Return Tailwind CSS CDN script tag.
     * Optional configuration can be passed to adjust theme or plugins.
     *
     * @param array $config Tailwind config (merged into tailwind.config = {...})
     * @return string
     */
    function tailwind_cdn(array $config = []): string
    {
        $defaultConfig = [
            'theme' => [
                'extend' => [
                    'colors' => [
                        'brand' => [
                            '50' => '#f5f9ff',
                            '100' => '#e0efff',
                            '500' => '#1d4ed8',
                            '600' => '#1e40af',
                        ]
                    ]
                ]
            ]
        ];
        if (!empty($config)) {
            // Merge simple (shallow) arrays; for deeper merges user can provide already merged array
            $merged = array_replace_recursive($defaultConfig, $config);
        } else {
            $merged = $defaultConfig;
        }
        $json = json_encode($merged, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        return '<script src="https://cdn.tailwindcss.com"></script>' . "\n" . '<script>tailwind.config = ' . $json . ';</script>';
    }
}
// --- End Notification & Tailwind Helpers ---

// Load authentication helpers
require_once __DIR__ . '/Auth/helpers.php';

// Initialize global database instance
$DB = DatabaseFactory::initialize();

// Initialize global output renderer
if (!isset($OUTPUT)) {
    /** @var Output $OUTPUT */
    $OUTPUT = Output::getInstance();
}

if (!function_exists('output')) {
    /**
     * Get global Output instance
     */
    function output(): Output
    {
        global $OUTPUT;
        return $OUTPUT;
    }
}

if (!function_exists('render_template')) {
    /**
     * Convenience wrapper for $OUTPUT->renderFromTemplate
     * @param string $name component_template name
     * @param array|object $data
     */
    function render_template(string $name, array|object $data = []): string
    {
        return output()->renderFromTemplate($name, $data);
    }
}

// --- Output cache management helpers ---
if (!function_exists('output_cache_dir')) {
    function output_cache_dir(): ?string { return output()->getCacheDir(); }
}
if (!function_exists('output_clear_cache')) {
    function output_clear_cache(): int { return output()->clearCache(); }
}
if (!function_exists('output_disable_cache')) {
    function output_disable_cache(): void { output()->disableCache(); }
}
if (!function_exists('output_enable_cache')) {
    function output_enable_cache(): void { output()->enableCache(); }
}
// --- End output cache management helpers ---

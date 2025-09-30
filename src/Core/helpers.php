<?php

use DevFramework\Core\Config\Configuration;
use DevFramework\Core\Database\DatabaseFactory;
use DevFramework\Core\Module\ModuleHelper;
use DevFramework\Core\Module\ModuleManager;
use DevFramework\Core\Module\LanguageManager;

// Load database constants first, then module constants
require_once __DIR__ . '/Database/constants.php';
require_once __DIR__ . '/Module/constants.php';

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
            // Step 1: Basic database connectivity check using direct PDO (no framework dependencies)
            $host = $_ENV['DB_HOST'] ?? 'mysql';
            $database = $_ENV['DB_DATABASE'] ?? 'devframework';
            $username = $_ENV['DB_USERNAME'] ?? 'devframework';
            $password = $_ENV['DB_PASSWORD'] ?? 'devframework';
            $prefix = $_ENV['DB_PREFIX'] ?? 'dev_';

            // Test basic database connection without using framework classes
            $pdo = new PDO("mysql:host={$host};dbname={$database}", $username, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]);

            // Step 2: Check if core tables exist using direct SQL
            $coreTable = $prefix . 'config';
            $pluginsTable = $prefix . 'config_plugins';

            $stmt = $pdo->prepare("SHOW TABLES LIKE ?");
            $stmt->execute([$coreTable]);
            $coreTableExists = $stmt->fetchColumn() !== false;

            $stmt->execute([$pluginsTable]);
            $pluginsTableExists = $stmt->fetchColumn() !== false;

            if (!$coreTableExists || !$pluginsTableExists) {
                error_log("Framework: Core tables missing, framework not properly initialized");
                $initializationResult = false;
                return false;
            }

            // Step 3: Initialize database connection through framework (now that we know tables exist)
            DatabaseFactory::createGlobal();

            // Step 4: Module upgrade detection and processing (the key functionality)
            $modulesDir = dirname(__DIR__, 2) . '/src/modules';
            if (!is_dir($modulesDir)) {
                error_log("Framework: Modules directory not found, skipping module upgrades");
                $initializationResult = true;
                return true;
            }

            // Define maturity constants for module version files
            if (!defined('MATURITY_ALPHA')) define('MATURITY_ALPHA', 'MATURITY_ALPHA');
            if (!defined('MATURITY_BETA')) define('MATURITY_BETA', 'MATURITY_BETA');
            if (!defined('MATURITY_RC')) define('MATURITY_RC', 'MATURITY_RC');
            if (!defined('MATURITY_STABLE')) define('MATURITY_STABLE', 'MATURITY_STABLE');

            $modules = array_filter(glob($modulesDir . '/*'), 'is_dir');
            $upgradePerformed = false;

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

                    // Check database version using direct SQL to avoid framework dependencies
                    $stmt = $pdo->prepare("SELECT value FROM `$pluginsTable` WHERE plugin = ? AND name = 'version'");
                    $stmt->execute([$moduleName]);
                    $dbVersion = $stmt->fetchColumn();

                    if (!$dbVersion) {
                        // Module not installed - install it
                        error_log("Framework: Installing module '{$moduleName}' version {$fileVersion}");
                        $stmt = $pdo->prepare("INSERT INTO `$pluginsTable` (plugin, name, value, timemodified, timecreated) VALUES (?, 'version', ?, ?, ?)");
                        $time = time();
                        $stmt->execute([$moduleName, $fileVersion, $time, $time]);
                        $upgradePerformed = true;

                        // Run install script if it exists
                        $installFile = $moduleDir . '/db/install.php';
                        if (file_exists($installFile)) {
                            try {
                                include $installFile;
                                error_log("Framework: Module '{$moduleName}' install script executed");
                            } catch (Exception $e) {
                                error_log("Framework: Module '{$moduleName}' install script failed: " . $e->getMessage());
                            }
                        }
                    } else if (version_compare($fileVersion, $dbVersion, '>')) {
                        // Module needs upgrade
                        error_log("Framework: Upgrading module '{$moduleName}' from {$dbVersion} to {$fileVersion}");
                        $stmt = $pdo->prepare("UPDATE `$pluginsTable` SET value = ?, timemodified = ? WHERE plugin = ? AND name = 'version'");
                        $stmt->execute([$fileVersion, time(), $moduleName]);
                        $upgradePerformed = true;

                        // Run upgrade script if it exists
                        $upgradeFile = $moduleDir . '/db/upgrade.php';
                        if (file_exists($upgradeFile)) {
                            try {
                                // Create mock objects that upgrade scripts might expect
                                global $DB, $CFG;
                                $DB = (object)['pdo' => $pdo];
                                $CFG = (object)['dbprefix' => $prefix];

                                include $upgradeFile;
                                error_log("Framework: Module '{$moduleName}' upgrade script executed");
                            } catch (Exception $e) {
                                error_log("Framework: Module '{$moduleName}' upgrade script failed: " . $e->getMessage());
                            }
                        }
                    }
                } catch (Exception $e) {
                    error_log("Framework: Error processing module '{$moduleName}': " . $e->getMessage());
                }
            }

            if ($upgradePerformed) {
                error_log("Framework: Module upgrades completed successfully");
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

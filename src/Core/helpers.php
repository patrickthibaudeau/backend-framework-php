<?php

use DevFramework\Core\Config\Configuration;
use DevFramework\Core\Database\DatabaseFactory;
use DevFramework\Core\Module\ModuleHelper;
use DevFramework\Core\Module\ModuleManager;
use DevFramework\Core\Module\LanguageManager;

// Initialize global database connection
DatabaseFactory::createGlobal();

// Initialize module system
ModuleHelper::initialize();

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

if (!function_exists('public_path')) {
    /**
     * Get public path
     *
     * @param string $path Additional path to append
     * @return string
     */
    function public_path(string $path = ''): string
    {
        $publicPath = app_path('public');
        return $path ? $publicPath . DIRECTORY_SEPARATOR . ltrim($path, DIRECTORY_SEPARATOR) : $publicPath;
    }
}

if (!function_exists('dd')) {
    /**
     * Dump and die - useful for debugging
     *
     * @param mixed ...$vars Variables to dump
     * @return never
     */
    function dd(mixed ...$vars): never
    {
        foreach ($vars as $var) {
            var_dump($var);
        }
        die(1);
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
        return ModuleHelper::lang($moduleName, $key, $params, $language);
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

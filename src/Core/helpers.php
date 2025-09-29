<?php

use DevFramework\Core\Config\Configuration;

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

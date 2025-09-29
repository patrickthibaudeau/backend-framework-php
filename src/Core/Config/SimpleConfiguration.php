<?php

namespace DevFramework\Core\Config;

/**
 * Simple Configuration management system (without external dependencies)
 * This version works without vlucas/phpdotenv for initial testing
 */
class SimpleConfiguration
{
    private static $instance = null;
    private $config = array();
    private $loaded = false;

    private function __construct()
    {
        // Private constructor for singleton pattern
    }

    /**
     * Get the singleton instance
     */
    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Load configuration from .env file (simple parser)
     */
    public function load($basePath = null)
    {
        if ($this->loaded) {
            return;
        }

        $basePath = $basePath ? $basePath : $this->getBasePath();

        // Load .env file if it exists (simple parser)
        $envFile = $basePath . '/.env';
        if (file_exists($envFile)) {
            $this->loadEnvFile($envFile);
        }

        // Load configuration from environment variables
        $this->loadFromEnvironment();
        $this->loaded = true;
    }

    /**
     * Get configuration value by key
     */
    public function get($key, $default = null)
    {
        if (!$this->loaded) {
            $this->load();
        }
        return $this->getNestedValue($key, $default);
    }

    /**
     * Set configuration value
     */
    public function set($key, $value)
    {
        $keys = explode('.', $key);
        $config = &$this->config;

        foreach ($keys as $k) {
            if (!isset($config[$k]) || !is_array($config[$k])) {
                $config[$k] = array();
            }
            $config = &$config[$k];
        }
        $config = $value;
    }

    /**
     * Check if configuration key exists
     */
    public function has($key)
    {
        if (!$this->loaded) {
            $this->load();
        }
        return $this->getNestedValue($key) !== null;
    }

    /**
     * Get all configuration as array
     */
    public function all()
    {
        if (!$this->loaded) {
            $this->load();
        }
        return $this->config;
    }

    /**
     * Get environment variable with type casting
     */
    public function env($key, $default = null)
    {
        $value = isset($_ENV[$key]) ? $_ENV[$key] : (getenv($key) !== false ? getenv($key) : $default);

        if ($value === null || $value === false) {
            return $default;
        }

        // Type casting for common boolean and null values
        $lower = strtolower($value);
        switch ($lower) {
            case 'true':
            case '(true)':
                return true;
            case 'false':
            case '(false)':
                return false;
            case 'null':
            case '(null)':
                return null;
            case 'empty':
            case '(empty)':
                return '';
            default:
                return $value;
        }
    }

    /**
     * Simple .env file parser
     */
    private function loadEnvFile($filePath)
    {
        $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        foreach ($lines as $line) {
            $line = trim($line);

            // Skip comments and empty lines
            if (empty($line) || $line[0] === '#') {
                continue;
            }

            // Parse KEY=VALUE format
            $parts = explode('=', $line, 2);
            if (count($parts) === 2) {
                $key = trim($parts[0]);
                $value = trim($parts[1]);

                // Remove quotes if present
                if (($value[0] === '"' && substr($value, -1) === '"') ||
                    ($value[0] === "'" && substr($value, -1) === "'")) {
                    $value = substr($value, 1, -1);
                }

                $_ENV[$key] = $value;
                putenv("$key=$value");
            }
        }
    }

    /**
     * Load configuration from environment variables
     */
    private function loadFromEnvironment()
    {
        // Core framework configuration
        $this->config = array(
            'app' => array(
                'name' => $this->env('APP_NAME', 'DevFramework Application'),
                'env' => $this->env('APP_ENV', 'production'),
                'debug' => $this->env('APP_DEBUG', false),
                'url' => $this->env('APP_URL', 'http://localhost'),
                'timezone' => $this->env('APP_TIMEZONE', 'UTC'),
                'key' => $this->env('APP_KEY'),
            ),
            'database' => array(
                'default' => $this->env('DB_CONNECTION', 'mysql'),
                'connections' => array(
                    'mysql' => array(
                        'driver' => 'mysql',
                        'host' => $this->env('DB_HOST', 'localhost'),
                        'port' => $this->env('DB_PORT', 3306),
                        'database' => $this->env('DB_DATABASE'),
                        'username' => $this->env('DB_USERNAME'),
                        'password' => $this->env('DB_PASSWORD'),
                        'charset' => $this->env('DB_CHARSET', 'utf8mb4'),
                        'collation' => $this->env('DB_COLLATION', 'utf8mb4_unicode_ci'),
                    ),
                    'pgsql' => array(
                        'driver' => 'pgsql',
                        'host' => $this->env('DB_HOST', 'localhost'),
                        'port' => $this->env('DB_PORT', 5432),
                        'database' => $this->env('DB_DATABASE'),
                        'username' => $this->env('DB_USERNAME'),
                        'password' => $this->env('DB_PASSWORD'),
                        'charset' => $this->env('DB_CHARSET', 'utf8'),
                    ),
                ),
            ),
            'security' => array(
                'encryption_key' => $this->env('ENCRYPTION_KEY'),
                'hash_algo' => $this->env('HASH_ALGO', 'bcrypt'),
            ),
            'cache' => array(
                'default' => $this->env('CACHE_DRIVER', 'file'),
                'prefix' => $this->env('CACHE_PREFIX', 'devframework_cache'),
            ),
            'session' => array(
                'driver' => $this->env('SESSION_DRIVER', 'file'),
                'lifetime' => $this->env('SESSION_LIFETIME', 120),
                'encrypt' => $this->env('SESSION_ENCRYPT', false),
            ),
        );
    }

    /**
     * Get nested configuration value using dot notation
     */
    private function getNestedValue($key, $default = null)
    {
        $keys = explode('.', $key);
        $value = $this->config;

        foreach ($keys as $k) {
            if (!is_array($value) || !array_key_exists($k, $value)) {
                return $default;
            }
            $value = $value[$k];
        }

        return $value;
    }

    /**
     * Get the base path of the application
     */
    private function getBasePath()
    {
        // Try to find the base path by looking for composer.json
        $currentDir = __DIR__;

        while ($currentDir !== '/') {
            if (file_exists($currentDir . '/composer.json')) {
                return $currentDir;
            }
            $currentDir = dirname($currentDir);
        }

        // Fallback to current working directory
        return getcwd() ? getcwd() : __DIR__;
    }
}

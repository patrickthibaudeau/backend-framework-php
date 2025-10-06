<?php

namespace DevFramework\Core\Config;

use Dotenv\Dotenv;
use InvalidArgumentException;

/**
 * Configuration management system for the DevFramework
 * Handles .env file loading and configuration value access
 */
class Configuration
{
    private static ?Configuration $instance = null;
    private array $config = [];
    private bool $loaded = false;

    private function __construct()
    {
        // Private constructor for singleton pattern
    }

    /**
     * Get the singleton instance
     */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Load configuration from .env file
     */
    public function load(?string $basePath = null): void
    {
        if ($this->loaded) {
            return;
        }

        $basePath = $basePath ?? $this->getBasePath();

        // Load .env file if it exists
        if (file_exists($basePath . '/.env')) {
            $dotenv = Dotenv::createImmutable($basePath);
            $dotenv->load();
        }

        // Load configuration from environment variables
        $this->loadFromEnvironment();
        $this->loaded = true;
    }

    /**
     * Get configuration value by key
     */
    public function get(string $key, mixed $default = null): mixed
    {
        if (!$this->loaded) {
            $this->load();
        }

        return $this->getNestedValue($key) ?? $default;
    }

    /**
     * Set configuration value
     */
    public function set(string $key, mixed $value): void
    {
        $keys = explode('.', $key);
        $config = &$this->config;

        foreach ($keys as $k) {
            if (!isset($config[$k]) || !is_array($config[$k])) {
                $config[$k] = [];
            }
            $config = &$config[$k];
        }

        $config = $value;
    }

    /**
     * Check if configuration key exists
     */
    public function has(string $key): bool
    {
        if (!$this->loaded) {
            $this->load();
        }

        return $this->getNestedValue($key) !== null;
    }

    /**
     * Get all configuration as array
     */
    public function all(): array
    {
        if (!$this->loaded) {
            $this->load();
        }

        return $this->config;
    }

    /**
     * Get environment variable with type casting
     */
    public function env(string $key, mixed $default = null): mixed
    {
        $value = $_ENV[$key] ?? getenv($key) ?? $default;

        if ($value === null) {
            return $default;
        }

        // Type casting for common boolean and null values
        return match (strtolower($value)) {
            'true', '(true)' => true,
            'false', '(false)' => false,
            'null', '(null)' => null,
            'empty', '(empty)' => '',
            default => $value
        };
    }

    /**
     * Require environment variable (throws exception if not found)
     */
    public function envRequired(string $key): mixed
    {
        $value = $this->env($key);

        if ($value === null) {
            throw new InvalidArgumentException("Required environment variable '{$key}' is not set");
        }

        return $value;
    }

    /**
     * Load configuration from environment variables
     */
    private function loadFromEnvironment(): void
    {
        // Core framework configuration
        $basePath = $this->getBasePath();
        $this->config = [
            'app' => [
                'name' => $this->env('APP_NAME', 'DevFramework Application'),
                'env' => $this->env('APP_ENV', 'production'),
                'debug' => $this->env('APP_DEBUG', false),
                'url' => $this->env('APP_URL', 'http://localhost'),
                'timezone' => $this->env('APP_TIMEZONE', 'UTC'),
                'key' => $this->env('APP_KEY'),
            ],
            'database' => [
                'default' => $this->env('DB_CONNECTION', 'mysql'),
                'prefix' => $this->env('DB_PREFIX', ''),
                'connections' => [
                    'mysql' => [
                        'driver' => 'mysql',
                        'host' => $this->env('DB_HOST', 'localhost'),
                        'port' => $this->env('DB_PORT', 3306),
                        'database' => $this->env('DB_DATABASE'),
                        'username' => $this->env('DB_USERNAME'),
                        'password' => $this->env('DB_PASSWORD'),
                        'charset' => $this->env('DB_CHARSET', 'utf8mb4'),
                        'collation' => $this->env('DB_COLLATION', 'utf8mb4_unicode_ci'),
                    ],
                    'pgsql' => [
                        'driver' => 'pgsql',
                        'host' => $this->env('DB_HOST', 'localhost'),
                        'port' => $this->env('DB_PORT', 5432),
                        'database' => $this->env('DB_DATABASE'),
                        'username' => $this->env('DB_USERNAME'),
                        'password' => $this->env('DB_PASSWORD'),
                        'charset' => $this->env('DB_CHARSET', 'utf8'),
                    ],
                ],
            ],
            'security' => [
                'encryption_key' => $this->env('ENCRYPTION_KEY'),
                'hash_algo' => $this->env('HASH_ALGO', 'bcrypt'),
            ],
            'cache' => [
                'default' => $this->env('CACHE_DRIVER', 'file'),
                'prefix' => $this->env('CACHE_PREFIX', 'devframework_cache'),
            ],
            'session' => [
                'driver' => $this->env('SESSION_DRIVER', 'file'),
                'lifetime' => $this->env('SESSION_LIFETIME', 120),
                'encrypt' => $this->env('SESSION_ENCRYPT', false),
            ],
            // New mail configuration
            'mail' => [
                'driver' => $this->env('MAIL_DRIVER', 'log'), // log | mail | smtp (sendmail driver planned)
                'from' => [
                    'address' => $this->env('MAIL_FROM_ADDRESS', 'no-reply@example.test'),
                    'name' => $this->env('MAIL_FROM_NAME', 'DevFramework'),
                ],
                'reply_to' => [
                    'address' => $this->env('MAIL_REPLY_TO_ADDRESS'),
                    'name' => $this->env('MAIL_REPLY_TO_NAME'),
                ],
                'smtp' => [
                    'host' => $this->env('MAIL_SMTP_HOST'),
                    'port' => $this->env('MAIL_SMTP_PORT', 587),
                    'username' => $this->env('MAIL_SMTP_USERNAME'),
                    'password' => $this->env('MAIL_SMTP_PASSWORD'),
                    'encryption' => $this->env('MAIL_SMTP_ENCRYPTION', 'tls'), // tls | ssl | none
                    'timeout' => $this->env('MAIL_SMTP_TIMEOUT', 10),
                ],
                'sendmail_path' => $this->env('MAIL_SENDMAIL_PATH', '/usr/sbin/sendmail -t -i'),
                'log_path' => $this->env('MAIL_LOG_PATH', $basePath . '/storage/logs/mail.log'),
            ],
        ];
    }

    /**
     * Get nested configuration value using dot notation
     */
    private function getNestedValue(string $key): mixed
    {
        $keys = explode('.', $key);
        $value = $this->config;

        foreach ($keys as $k) {
            if (!is_array($value) || !array_key_exists($k, $value)) {
                return null;
            }
            $value = $value[$k];
        }

        return $value;
    }

    /**
     * Get the base path of the application
     */
    private function getBasePath(): string
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
        return getcwd() ?: __DIR__;
    }

    /**
     * Prevent cloning
     */
    private function __clone() {}

    /**
     * Prevent unserialization
     */
    public function __wakeup()
    {
        throw new \Exception("Cannot unserialize singleton");
    }
}

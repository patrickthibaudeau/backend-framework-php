<?php

namespace DevFramework\Core\Database;

use PDO;
use PDOException;

/**
 * Database Initializer
 * Handles creating the database if it doesn't exist
 * This class is completely independent and does NOT use the main Database class
 */
class DatabaseInitializer
{
    private array $config;

    public function __construct()
    {
        // Load configuration directly from environment variables
        // Don't use the Configuration class to avoid dependencies
        $this->loadEnvironmentVariables();

        $this->config = [
            'driver' => $_ENV['DB_CONNECTION'] ?? 'mysql',
            'host' => $_ENV['DB_HOST'] ?? 'mysql',
            'port' => $_ENV['DB_PORT'] ?? 3306,
            'database' => $_ENV['DB_DATABASE'] ?? 'devframework',
            'username' => $_ENV['DB_USERNAME'] ?? 'devframework',
            'password' => $_ENV['DB_PASSWORD'] ?? 'devframework',
            'charset' => 'utf8mb4',
        ];
    }

    /**
     * Load environment variables from .env file if they're not already loaded
     */
    private function loadEnvironmentVariables(): void
    {
        // If environment variables are already loaded, skip
        if (isset($_ENV['DB_HOST'])) {
            return;
        }

        // Try to load .env file
        $envFile = dirname(__DIR__, 3) . '/.env';
        if (file_exists($envFile)) {
            $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                if (strpos($line, '=') !== false && !str_starts_with(trim($line), '#')) {
                    list($key, $value) = explode('=', $line, 2);
                    $_ENV[trim($key)] = trim($value);
                }
            }
        }
    }

    /**
     * Ensure the database exists, create it if it doesn't
     */
    public function ensureDatabaseExists(): bool
    {
        if (empty($this->config['database'])) {
            error_log('DatabaseInitializer: No database name configured');
            return false;
        }

        try {
            // First, try to connect to the database directly
            if ($this->canConnectToDatabase()) {
                return true; // Database exists and is accessible
            }

            // If that fails, try to create the database
            return $this->createDatabase();
        } catch (\Exception $e) {
            error_log('DatabaseInitializer failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Check if we can connect to the target database
     */
    private function canConnectToDatabase(): bool
    {
        try {
            $dsn = $this->buildDsn();
            $pdo = new PDO($dsn, $this->config['username'], $this->config['password'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_TIMEOUT => 5
            ]);
            $pdo = null; // Close connection
            return true;
        } catch (PDOException $e) {
            return false;
        }
    }

    /**
     * Create the database
     */
    private function createDatabase(): bool
    {
        try {
            // Connect without specifying database
            $dsn = $this->buildDsnWithoutDatabase();
            $pdo = new PDO($dsn, $this->config['username'], $this->config['password'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
            ]);

            $databaseName = $this->config['database'];
            $sql = "CREATE DATABASE IF NOT EXISTS `{$databaseName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";
            $pdo->exec($sql);

            error_log("DatabaseInitializer: Database '{$databaseName}' created successfully");
            return true;
        } catch (PDOException $e) {
            error_log('DatabaseInitializer: Failed to create database: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Build DSN string
     */
    private function buildDsn(): string
    {
        $driver = $this->config['driver'];
        $host = $this->config['host'];
        $port = $this->config['port'];
        $database = $this->config['database'];
        $charset = $this->config['charset'];

        return "{$driver}:host={$host};port={$port};dbname={$database};charset={$charset}";
    }

    /**
     * Build DSN string without database name (for creating database)
     */
    private function buildDsnWithoutDatabase(): string
    {
        $driver = $this->config['driver'];
        $host = $this->config['host'];
        $port = $this->config['port'];
        $charset = $this->config['charset'];

        return "{$driver}:host={$host};port={$port};charset={$charset}";
    }

    /**
     * Get database configuration
     */
    public function getConfig(): array
    {
        return $this->config;
    }
}

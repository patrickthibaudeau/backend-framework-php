<?php

namespace DevFramework\Core\Config;

use InvalidArgumentException;

/**
 * Configuration validator to ensure required settings are properly configured
 */
class ConfigValidator
{
    private Configuration $config;
    private array $errors = [];

    public function __construct(Configuration $config = null)
    {
        $this->config = $config ?? Configuration::getInstance();
    }

    /**
     * Validate all required configuration settings
     */
    public function validate(): bool
    {
        $this->errors = [];

        $this->validateApp();
        $this->validateDatabase();
        $this->validateSecurity();

        return empty($this->errors);
    }

    /**
     * Get validation errors
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Validate application configuration
     */
    private function validateApp(): void
    {
        $appName = $this->config->get('app.name');
        if (empty($appName)) {
            $this->errors[] = 'APP_NAME is required';
        }

        $appEnv = $this->config->get('app.env');
        if (!in_array($appEnv, ['development', 'testing', 'staging', 'production'])) {
            $this->errors[] = 'APP_ENV must be one of: development, testing, staging, production';
        }

        $appUrl = $this->config->get('app.url');
        if (empty($appUrl) || !filter_var($appUrl, FILTER_VALIDATE_URL)) {
            $this->errors[] = 'APP_URL must be a valid URL';
        }

        $timezone = $this->config->get('app.timezone');
        if (!in_array($timezone, timezone_identifiers_list())) {
            $this->errors[] = 'APP_TIMEZONE must be a valid timezone identifier';
        }
    }

    /**
     * Validate database configuration
     */
    private function validateDatabase(): void
    {
        $connection = $this->config->get('database.default');
        $connectionConfig = $this->config->get("database.connections.{$connection}");

        if (!$connectionConfig) {
            $this->errors[] = "Database connection '{$connection}' is not configured";
            return;
        }

        $required = ['host', 'database', 'username'];
        foreach ($required as $field) {
            if (empty($connectionConfig[$field])) {
                $this->errors[] = "Database {$field} is required for {$connection} connection";
            }
        }

        // Validate port is numeric
        if (isset($connectionConfig['port']) && !is_numeric($connectionConfig['port'])) {
            $this->errors[] = "Database port must be numeric";
        }
    }

    /**
     * Validate security configuration
     */
    private function validateSecurity(): void
    {
        $encryptionKey = $this->config->get('security.encryption_key');
        if (empty($encryptionKey)) {
            $this->errors[] = 'ENCRYPTION_KEY is required for security';
        } elseif (strlen($encryptionKey) < 32) {
            $this->errors[] = 'ENCRYPTION_KEY must be at least 32 characters long';
        }

        $hashAlgo = $this->config->get('security.hash_algo');
        if (!in_array($hashAlgo, ['bcrypt', 'argon2i', 'argon2id'])) {
            $this->errors[] = 'HASH_ALGO must be one of: bcrypt, argon2i, argon2id';
        }
    }

    /**
     * Generate a secure encryption key
     */
    public static function generateEncryptionKey(): string
    {
        return base64_encode(random_bytes(32));
    }

    /**
     * Display validation results
     */
    public function displayResults(): void
    {
        if ($this->validate()) {
            echo "✅ Configuration validation passed!\n";
        } else {
            echo "❌ Configuration validation failed:\n";
            foreach ($this->errors as $error) {
                echo "  - {$error}\n";
            }
        }
    }
}

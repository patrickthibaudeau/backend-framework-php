<?php

namespace DevFramework\Core\Console;

use DevFramework\Core\Config\Configuration;
use DevFramework\Core\Config\ConfigValidator;

/**
 * CLI tool for configuration management
 */
class ConfigCommand
{
    private Configuration $config;

    public function __construct()
    {
        $this->config = Configuration::getInstance();
    }

    /**
     * Run the configuration command
     */
    public function run(array $args): void
    {
        if (empty($args[1])) {
            $this->showHelp();
            return;
        }

        $command = $args[1];

        match ($command) {
            'validate' => $this->validateConfig(),
            'generate-key' => $this->generateKey(),
            'show' => $this->showConfig($args[2] ?? null),
            'init' => $this->initConfig(),
            default => $this->showHelp()
        };
    }

    /**
     * Validate configuration
     */
    private function validateConfig(): void
    {
        echo "Validating configuration...\n\n";

        $this->config->load();
        $validator = new ConfigValidator($this->config);
        $validator->displayResults();
    }

    /**
     * Generate encryption key
     */
    private function generateKey(): void
    {
        $key = ConfigValidator::generateEncryptionKey();
        echo "Generated encryption key:\n";
        echo "ENCRYPTION_KEY={$key}\n\n";
        echo "Add this to your .env file.\n";
    }

    /**
     * Show configuration values
     */
    private function showConfig(?string $key = null): void
    {
        $this->config->load();

        if ($key) {
            $value = config($key);
            if ($value === null) {
                echo "Configuration key '{$key}' not found.\n";
            } else {
                echo "{$key}: " . $this->formatValue($value) . "\n";
            }
        } else {
            echo "Current configuration:\n\n";
            $this->displayConfigArray(config(), '');
        }
    }

    /**
     * Initialize configuration by copying .env.example to .env
     */
    private function initConfig(): void
    {
        $basePath = $this->getBasePath();
        $exampleFile = $basePath . '/.env.example';
        $envFile = $basePath . '/.env';

        if (!file_exists($exampleFile)) {
            echo "❌ .env.example file not found.\n";
            return;
        }

        if (file_exists($envFile)) {
            echo "⚠️  .env file already exists. Do you want to overwrite it? (y/N): ";
            $handle = fopen("php://stdin", "r");
            $response = trim(fgets($handle));
            fclose($handle);

            if (strtolower($response) !== 'y') {
                echo "Initialization cancelled.\n";
                return;
            }
        }

        if (copy($exampleFile, $envFile)) {
            echo "✅ .env file created successfully!\n";
            echo "Don't forget to:\n";
            echo "1. Update database credentials\n";
            echo "2. Generate an encryption key: php config.php generate-key\n";
            echo "3. Set your APP_URL\n";
        } else {
            echo "❌ Failed to create .env file.\n";
        }
    }

    /**
     * Display configuration array recursively
     */
    private function displayConfigArray(array $config, string $prefix): void
    {
        foreach ($config as $key => $value) {
            $fullKey = $prefix ? "{$prefix}.{$key}" : $key;

            if (is_array($value)) {
                echo "[{$fullKey}]\n";
                $this->displayConfigArray($value, $fullKey);
                echo "\n";
            } else {
                echo "{$fullKey}: " . $this->formatValue($value) . "\n";
            }
        }
    }

    /**
     * Format value for display
     */
    private function formatValue(mixed $value): string
    {
        if ($value === null) {
            return 'null';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_string($value) && empty($value)) {
            return '(empty)';
        }

        return (string) $value;
    }

    /**
     * Get base path
     */
    private function getBasePath(): string
    {
        $currentDir = __DIR__;

        while ($currentDir !== '/') {
            if (file_exists($currentDir . '/composer.json')) {
                return $currentDir;
            }
            $currentDir = dirname($currentDir);
        }

        return getcwd() ?: __DIR__;
    }

    /**
     * Show help information
     */
    private function showHelp(): void
    {
        echo "DevFramework Configuration Tool\n\n";
        echo "Usage: php config.php <command>\n\n";
        echo "Commands:\n";
        echo "  init         Copy .env.example to .env\n";
        echo "  validate     Validate current configuration\n";
        echo "  generate-key Generate encryption key\n";
        echo "  show [key]   Show configuration values\n";
        echo "  help         Show this help message\n\n";
        echo "Examples:\n";
        echo "  php config.php init\n";
        echo "  php config.php validate\n";
        echo "  php config.php generate-key\n";
        echo "  php config.php show app.name\n";
        echo "  php config.php show\n";
    }
}

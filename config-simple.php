<?php

require_once __DIR__ . '/src/Core/Config/SimpleConfiguration.php';

/**
 * Simple CLI tool for configuration management (without external dependencies)
 */
class SimpleConfigCommand
{
    private $config;

    public function __construct()
    {
        $this->config = \DevFramework\Core\Config\SimpleConfiguration::getInstance();
    }

    /**
     * Run the configuration command
     */
    public function run($args)
    {
        if (empty($args[1])) {
            $this->showHelp();
            return;
        }

        $command = $args[1];

        switch ($command) {
            case 'validate':
                $this->validateConfig();
                break;
            case 'generate-key':
                $this->generateKey();
                break;
            case 'show':
                $this->showConfig(isset($args[2]) ? $args[2] : null);
                break;
            case 'init':
                $this->initConfig();
                break;
            default:
                $this->showHelp();
        }
    }

    /**
     * Validate configuration
     */
    private function validateConfig()
    {
        echo "Validating configuration...\n\n";

        $this->config->load();
        $errors = array();

        // Basic validation
        $appName = $this->config->get('app.name');
        if (empty($appName)) {
            $errors[] = 'APP_NAME is required';
        }

        $appEnv = $this->config->get('app.env');
        if (!in_array($appEnv, array('development', 'testing', 'staging', 'production'))) {
            $errors[] = 'APP_ENV must be one of: development, testing, staging, production';
        }

        $appUrl = $this->config->get('app.url');
        if (empty($appUrl) || !filter_var($appUrl, FILTER_VALIDATE_URL)) {
            $errors[] = 'APP_URL must be a valid URL';
        }

        $encryptionKey = $this->config->get('security.encryption_key');
        if (empty($encryptionKey)) {
            $errors[] = 'ENCRYPTION_KEY is required for security';
        } elseif (strlen($encryptionKey) < 32) {
            $errors[] = 'ENCRYPTION_KEY must be at least 32 characters long';
        }

        if (empty($errors)) {
            echo "✅ Configuration validation passed!\n";
        } else {
            echo "❌ Configuration validation failed:\n";
            foreach ($errors as $error) {
                echo "  - {$error}\n";
            }
        }
    }

    /**
     * Generate encryption key
     */
    private function generateKey()
    {
        // Simple key generation (for PHP < 7.0 compatibility)
        $key = base64_encode(openssl_random_pseudo_bytes(32));
        echo "Generated encryption key:\n";
        echo "ENCRYPTION_KEY={$key}\n\n";
        echo "Add this to your .env file.\n";
    }

    /**
     * Show configuration values
     */
    private function showConfig($key = null)
    {
        $this->config->load();

        if ($key) {
            $value = $this->config->get($key);
            if ($value === null) {
                echo "Configuration key '{$key}' not found.\n";
            } else {
                echo "{$key}: " . $this->formatValue($value) . "\n";
            }
        } else {
            echo "Current configuration:\n\n";
            $this->displayConfigArray($this->config->all(), '');
        }
    }

    /**
     * Initialize configuration by copying .env.example to .env
     */
    private function initConfig()
    {
        $basePath = getcwd();
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
            echo "2. Generate an encryption key: php config-simple.php generate-key\n";
            echo "3. Set your APP_URL\n";
        } else {
            echo "❌ Failed to create .env file.\n";
        }
    }

    /**
     * Display configuration array recursively
     */
    private function displayConfigArray($config, $prefix)
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
    private function formatValue($value)
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
     * Show help information
     */
    private function showHelp()
    {
        echo "DevFramework Configuration Tool (Simple Version)\n\n";
        echo "Usage: php config-simple.php <command>\n\n";
        echo "Commands:\n";
        echo "  init         Copy .env.example to .env\n";
        echo "  validate     Validate current configuration\n";
        echo "  generate-key Generate encryption key\n";
        echo "  show [key]   Show configuration values\n";
        echo "  help         Show this help message\n\n";
        echo "Examples:\n";
        echo "  php config-simple.php init\n";
        echo "  php config-simple.php validate\n";
        echo "  php config-simple.php generate-key\n";
        echo "  php config-simple.php show app.name\n";
        echo "  php config-simple.php show\n";
    }
}

// Run the command
$command = new SimpleConfigCommand();
$command->run($argv);

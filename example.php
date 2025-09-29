<?php

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/src/Core/helpers.php';

use DevFramework\Core\Config\Configuration;
use DevFramework\Core\Config\ConfigValidator;

// Example usage of the Configuration system

echo "=== DevFramework Configuration System Demo ===\n\n";

// Load configuration
$config = Configuration::getInstance();
$config->load(__DIR__);

echo "1. Basic configuration access:\n";
echo "App Name: " . config('app.name') . "\n";
echo "Environment: " . config('app.env') . "\n";
echo "Debug Mode: " . (config('app.debug') ? 'enabled' : 'disabled') . "\n";
echo "Database: " . config('database.default') . "\n\n";

echo "2. Environment variable access:\n";
echo "APP_URL: " . env('APP_URL', 'not set') . "\n";
echo "APP_TIMEZONE: " . env('APP_TIMEZONE', 'UTC') . "\n\n";

echo "3. Nested configuration access:\n";
echo "DB Host: " . config('database.connections.mysql.host') . "\n";
echo "DB Port: " . config('database.connections.mysql.port') . "\n\n";

echo "4. Default values:\n";
echo "Non-existent key: " . config('non.existent.key', 'default value') . "\n\n";

echo "5. Configuration validation:\n";
$validator = new ConfigValidator($config);
$validator->displayResults();

if (!$validator->validate()) {
    echo "\n6. Generating encryption key:\n";
    echo "Suggested ENCRYPTION_KEY: " . ConfigValidator::generateEncryptionKey() . "\n";
}

echo "\n7. All configuration keys:\n";
$allConfig = config();
echo "Available configuration sections: " . implode(', ', array_keys($allConfig)) . "\n";

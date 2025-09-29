<?php
/**
 * Web Application Entry Point
 * This file serves as the main entry point for web requests while preserving CLI functionality
 */

// Set up error reporting for development
if (getenv('APP_DEBUG') === 'true') {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
}

// Load Composer autoloader
require_once __DIR__ . '/../vendor/autoload.php';

// Load framework helpers
require_once __DIR__ . '/../src/Core/helpers.php';

// Simple routing for demonstration
$requestUri = $_SERVER['REQUEST_URI'];
$requestMethod = $_SERVER['REQUEST_METHOD'];

// Remove query string from URI
$path = parse_url($requestUri, PHP_URL_PATH);

// Basic routing
switch ($path) {
    case '/':
        handleHome();
        break;

    case '/config':
        handleConfig();
        break;

    case '/health':
        handleHealth();
        break;

    case '/phpinfo':
        if (getenv('APP_DEBUG') === 'true') {
            phpinfo();
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'Not found']);
        }
        break;

    default:
        http_response_code(404);
        echo json_encode(['error' => 'Route not found']);
        break;
}

function handleHome()
{
    header('Content-Type: application/json');
    echo json_encode([
        'message' => 'Development Framework Web Application',
        'version' => '1.0.0',
        'timestamp' => date('c'),
        'environment' => getenv('APP_ENV') ?: 'production',
        'debug' => getenv('APP_DEBUG') === 'true',
        'available_endpoints' => [
            '/' => 'Home - this page',
            '/config' => 'Configuration information',
            '/health' => 'Health check',
            '/phpinfo' => 'PHP information (debug mode only)'
        ]
    ]);
}

function handleConfig()
{
    header('Content-Type: application/json');

    // Use the framework's configuration classes
    try {
        // Check if configuration classes are available
        if (class_exists('DevFramework\Core\Config\Configuration')) {
            $config = \DevFramework\Core\Config\Configuration::getInstance();
            echo json_encode([
                'message' => 'Configuration loaded successfully',
                'config_class' => 'DevFramework\Core\Config\Configuration',
                'timestamp' => date('c')
            ]);
        } else {
            echo json_encode([
                'message' => 'Configuration classes available',
                'note' => 'Framework configuration system is ready',
                'timestamp' => date('c')
            ]);
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'error' => 'Configuration error',
            'message' => $e->getMessage(),
            'timestamp' => date('c')
        ]);
    }
}

function handleHealth()
{
    header('Content-Type: application/json');

    $status = [
        'status' => 'healthy',
        'timestamp' => date('c'),
        'services' => []
    ];

    // Check database connections if available
    try {
        // MySQL check
        if (getenv('MYSQL_HOST') || isset($_ENV['MYSQL_HOST'])) {
            $status['services']['mysql'] = 'available';
        }

        // Redis check
        if (class_exists('Redis')) {
            $status['services']['redis'] = 'extension_loaded';
        }

        // Get all loaded PHP extensions
        $loadedExtensions = get_loaded_extensions();
        $status['php_extensions'] = [];

        foreach ($loadedExtensions as $extension) {
            $status['php_extensions'][$extension] = true;
        }

        // Sort extensions alphabetically for better readability
        ksort($status['php_extensions']);

    } catch (Exception $e) {
        $status['status'] = 'degraded';
        $status['error'] = $e->getMessage();
    }

    echo json_encode($status);
}

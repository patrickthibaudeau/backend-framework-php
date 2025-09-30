<?php
/**
 * Debug Framework Initialization
 * This page bypasses the maintenance mode to show detailed error information
 */

// Include required classes at the top
require_once __DIR__ . '/../vendor/autoload.php';

use DevFramework\Core\Config\Configuration;
use DevFramework\Core\Database\DatabaseFactory;
use DevFramework\Core\Module\ModuleHelper;
use DevFramework\Core\Module\ModuleManager;
use DevFramework\Core\Module\LanguageManager;

header('Content-Type: text/html; charset=UTF-8');

echo '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DevFramework - Debug Initialization</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 1000px; margin: 0 auto; padding: 20px; background: #f5f5f5; }
        .container { background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .success { color: #27ae60; }
        .warning { color: #f39c12; }
        .error { color: #e74c3c; }
        .test-item { margin: 10px 0; padding: 10px; border-left: 4px solid #3498db; background: #f8f9fa; }
        .test-item.success { border-left-color: #27ae60; }
        .test-item.warning { border-left-color: #f39c12; }
        .test-item.error { border-left-color: #e74c3c; }
        pre { background: #2c3e50; color: #ecf0f1; padding: 15px; border-radius: 5px; overflow-x: auto; font-size: 12px; }
        .logs { max-height: 300px; overflow-y: auto; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 DevFramework Debug Mode</h1>
        <p>This page shows detailed information about framework initialization failures.</p>';

echo '<h2>Step-by-Step Initialization Debug:</h2>';

try {
    // Step 1: Autoloader already loaded above
    echo '<div class="test-item success">✓ Autoloader loaded</div>';

    // Step 2: Load configuration
    echo '<div class="test-item">Loading configuration...</div>';
    $config = Configuration::getInstance();
    $config->load();
    echo '<div class="test-item success">✓ Configuration loaded</div>';

    // Debug: Show environment variables
    echo '<div class="test-item">Environment variables: <pre>' . json_encode([
        'DB_HOST' => $_ENV['DB_HOST'] ?? $_SERVER['DB_HOST'] ?? 'NOT SET',
        'DB_PORT' => $_ENV['DB_PORT'] ?? $_SERVER['DB_PORT'] ?? 'NOT SET',
        'DB_DATABASE' => $_ENV['DB_DATABASE'] ?? $_SERVER['DB_DATABASE'] ?? 'NOT SET',
        'DB_USERNAME' => $_ENV['DB_USERNAME'] ?? $_SERVER['DB_USERNAME'] ?? 'NOT SET',
        'DB_PASSWORD' => ($_ENV['DB_PASSWORD'] ?? $_SERVER['DB_PASSWORD'] ?? null) ? '***SET***' : 'NOT SET'
    ], JSON_PRETTY_PRINT) . '</pre></div>';

    // Show database config
    $dbConfig = [
        'host' => $config->get('database.connections.mysql.host'),
        'port' => $config->get('database.connections.mysql.port'),
        'database' => $config->get('database.connections.mysql.database'),
        'username' => $config->get('database.connections.mysql.username'),
        'password' => '***masked***'
    ];
    echo '<div class="test-item">Database config: <pre>' . json_encode($dbConfig, JSON_PRETTY_PRINT) . '</pre></div>';

    // Step 3: Test database initialization
    echo '<div class="test-item">Testing database initialization...</div>';
    $dbInitializer = new \DevFramework\Core\Database\DatabaseInitializer();
    echo '<div class="test-item success">✓ DatabaseInitializer created</div>';

    // Debug: Show the actual config being used
    $initConfig = $dbInitializer->getConfig();
    echo '<div class="test-item">DatabaseInitializer config: <pre>' . json_encode([
        'host' => $initConfig['host'],
        'port' => $initConfig['port'],
        'database' => $initConfig['database'],
        'username' => $initConfig['username'],
        'password' => '***masked***',
        'driver' => $initConfig['driver']
    ], JSON_PRETTY_PRINT) . '</pre></div>';

    echo '<div class="test-item">Checking if database exists...</div>';

    // Test database creation with more detailed error reporting
    try {
        $dbExists = $dbInitializer->ensureDatabaseExists();
        if ($dbExists) {
            echo '<div class="test-item success">✓ Database exists or was created successfully</div>';
        } else {
            echo '<div class="test-item error">❌ Failed to ensure database exists</div>';
        }
    } catch (Exception $dbInitError) {
        echo '<div class="test-item error">❌ Database initialization error: ' . htmlspecialchars($dbInitError->getMessage()) . '</div>';
        echo '<div class="test-item error">File: ' . htmlspecialchars($dbInitError->getFile()) . ':' . $dbInitError->getLine() . '</div>';
    }

    // Step 4: Test database connection
    echo '<div class="test-item">Testing database connection...</div>';
    DatabaseFactory::createGlobal();
    global $DB;
    if ($DB) {
        echo '<div class="test-item success">✓ Global database instance created</div>';
        $DB->connect();
        echo '<div class="test-item success">✓ Database connection established</div>';
    } else {
        echo '<div class="test-item error">❌ Failed to create global database instance</div>';
    }

    // Step 5: Test core installer
    echo '<div class="test-item">Testing core table installation...</div>';
    $coreInstaller = new \DevFramework\Core\Database\CoreInstaller();
    echo '<div class="test-item success">✓ CoreInstaller created</div>';

    $coreTablesExist = $coreInstaller->areCoreTablesInstalled();
    if ($coreTablesExist) {
        echo '<div class="test-item success">✓ Core tables are installed</div>';
    } else {
        echo '<div class="test-item warning">⚠ Core tables not found, attempting installation...</div>';
        $installResult = $coreInstaller->installCoreTablesIfNeeded();
        if ($installResult) {
            echo '<div class="test-item success">✓ Core tables installed successfully</div>';
        } else {
            echo '<div class="test-item error">❌ Failed to install core tables</div>';
        }
    }

    // Step 6: Test module system
    echo '<div class="test-item">Testing module system...</div>';
    ModuleHelper::initialize();
    echo '<div class="test-item success">✓ Module system initialized</div>';

    $moduleManager = ModuleManager::getInstance();
    $moduleManager->discoverModules();
    $modules = $moduleManager->getAllModules();
    echo '<div class="test-item success">✓ Found ' . count($modules) . ' modules: ' . implode(', ', array_keys($modules)) . '</div>';

    // Step 7: Test module tracking
    echo '<div class="test-item">Testing module tracking...</div>';
    foreach ($modules as $moduleName => $moduleInfo) {
        $moduleVersion = $moduleInfo['version'] ?? null;
        if ($moduleVersion) {
            $currentVersion = $DB->get_plugin_version($moduleName);
            if (!$currentVersion) {
                $DB->set_plugin_version($moduleName, $moduleVersion);
                echo '<div class="test-item success">✓ Added version tracking for module: ' . $moduleName . ' v' . $moduleVersion . '</div>';
            } else {
                echo '<div class="test-item">Module ' . $moduleName . ' already tracked: v' . $currentVersion . '</div>';
            }
        }
    }

    echo '<div class="test-item success"><strong>🎉 All initialization steps completed successfully!</strong></div>';

} catch (Exception $e) {
    echo '<div class="test-item error">❌ Critical error during initialization:</div>';
    echo '<div class="test-item error">Message: ' . htmlspecialchars($e->getMessage()) . '</div>';
    echo '<div class="test-item error">File: ' . htmlspecialchars($e->getFile()) . ':' . $e->getLine() . '</div>';
    echo '<div class="test-item error">Stack trace: <pre>' . htmlspecialchars($e->getTraceAsString()) . '</pre></div>';
}

// Show application logs if they exist
echo '<h2>Application Logs:</h2>';
$logFile = __DIR__ . '/../storage/logs/app.log';
if (file_exists($logFile)) {
    $logs = file_get_contents($logFile);
    if ($logs) {
        echo '<div class="logs"><pre>' . htmlspecialchars($logs) . '</pre></div>';
    } else {
        echo '<div class="test-item">Log file exists but is empty</div>';
    }
} else {
    echo '<div class="test-item warning">No log file found at: ' . $logFile . '</div>';
}

echo '<p><a href="javascript:window.location.reload()">🔄 Refresh Debug</a></p>';
echo '</div></body></html>';
?>

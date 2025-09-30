<?php
/**
 * Web-accessible Framework Test Page
 * This page will show if the framework initialization is working properly
 */

// Include the framework helpers which will trigger initialization
require_once __DIR__ . '/../src/Core/helpers.php';

header('Content-Type: text/html; charset=UTF-8');

echo '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DevFramework - Initialization Test</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 0 auto; padding: 20px; background: #f5f5f5; }
        .container { background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .success { color: #27ae60; }
        .warning { color: #f39c12; }
        .error { color: #e74c3c; }
        .test-item { margin: 10px 0; padding: 10px; border-left: 4px solid #3498db; background: #f8f9fa; }
        .test-item.success { border-left-color: #27ae60; }
        .test-item.warning { border-left-color: #f39c12; }
        .test-item.error { border-left-color: #e74c3c; }
        pre { background: #2c3e50; color: #ecf0f1; padding: 15px; border-radius: 5px; overflow-x: auto; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🚀 DevFramework Initialization Test</h1>
        <p>This page tests if the framework database and core tables are properly initialized.</p>
        
        <h2>Test Results:</h2>';

$testResults = [];
$allPassed = true;

try {
    // Test 1: Framework helpers loading
    echo '<div class="test-item success">✓ Framework helpers loaded successfully</div>';
    $testResults[] = "Framework helpers: OK";

    // Test 2: Database connection
    try {
        $db = db();
        $db->connect();
        echo '<div class="test-item success">✓ Database connection established</div>';
        $testResults[] = "Database connection: OK";
    } catch (Exception $e) {
        echo '<div class="test-item error">❌ Database connection failed: ' . htmlspecialchars($e->getMessage()) . '</div>';
        $testResults[] = "Database connection: FAILED - " . $e->getMessage();
        $allPassed = false;
    }

    // Test 3: Core tables installation
    try {
        $coreInstaller = new \DevFramework\Core\Database\CoreInstaller();
        if ($coreInstaller->areCoreTablesInstalled()) {
            echo '<div class="test-item success">✓ Core tables are installed and accessible</div>';
            $testResults[] = "Core tables: OK";
        } else {
            echo '<div class="test-item warning">⚠ Core tables need to be installed (this should auto-resolve)</div>';
            $testResults[] = "Core tables: INSTALLING";
            $allPassed = false;
        }
    } catch (Exception $e) {
        echo '<div class="test-item error">❌ Core tables check failed: ' . htmlspecialchars($e->getMessage()) . '</div>';
        $testResults[] = "Core tables: FAILED - " . $e->getMessage();
        $allPassed = false;
    }

    // Test 4: Configuration system
    try {
        $dbName = config('database.connections.mysql.database');
        echo '<div class="test-item success">✓ Configuration system working - Database: ' . htmlspecialchars($dbName) . '</div>';
        $testResults[] = "Configuration: OK (DB: $dbName)";
    } catch (Exception $e) {
        echo '<div class="test-item error">❌ Configuration system failed: ' . htmlspecialchars($e->getMessage()) . '</div>';
        $testResults[] = "Configuration: FAILED - " . $e->getMessage();
        $allPassed = false;
    }

    // Test 5: Module system
    try {
        $modules = list_modules();
        $moduleCount = count($modules);
        echo '<div class="test-item success">✓ Module system working - Found ' . $moduleCount . ' modules</div>';
        $testResults[] = "Module system: OK ($moduleCount modules)";

        if ($moduleCount > 0) {
            echo '<div class="test-item">📦 Available modules: ' . implode(', ', array_keys($modules)) . '</div>';
        }
    } catch (Exception $e) {
        echo '<div class="test-item error">❌ Module system failed: ' . htmlspecialchars($e->getMessage()) . '</div>';
        $testResults[] = "Module system: FAILED - " . $e->getMessage();
        $allPassed = false;
    }

    // Test 6: Plugin version tracking
    try {
        $testVersion = get_plugin_version('User');
        if ($testVersion) {
            echo '<div class="test-item success">✓ Plugin version tracking working - User module: v' . htmlspecialchars($testVersion) . '</div>';
            $testResults[] = "Version tracking: OK (User v$testVersion)";
        } else {
            echo '<div class="test-item warning">⚠ Plugin version tracking not initialized yet (will auto-resolve)</div>';
            $testResults[] = "Version tracking: INITIALIZING";
        }
    } catch (Exception $e) {
        echo '<div class="test-item error">❌ Plugin version tracking failed: ' . htmlspecialchars($e->getMessage()) . '</div>';
        $testResults[] = "Version tracking: FAILED - " . $e->getMessage();
        $allPassed = false;
    }

} catch (Exception $e) {
    echo '<div class="test-item error">❌ Critical framework error: ' . htmlspecialchars($e->getMessage()) . '</div>';
    echo '<div class="test-item error">File: ' . htmlspecialchars($e->getFile()) . ':' . $e->getLine() . '</div>';
    $testResults[] = "CRITICAL ERROR: " . $e->getMessage();
    $allPassed = false;
}

// Summary
echo '<h2>Summary:</h2>';
if ($allPassed) {
    echo '<div class="test-item success">
        <strong>🎉 All tests passed!</strong><br>
        The framework is properly initialized and should work across all entry points.
    </div>';
} else {
    echo '<div class="test-item warning">
        <strong>⚠ Some issues detected</strong><br>
        The framework may need time to complete initialization or there are configuration issues.
        Try refreshing this page in a few seconds.
    </div>';
}

// Technical details
echo '<h2>Technical Details:</h2>';
echo '<pre>' . implode("\n", $testResults) . '</pre>';

echo '<p><em>Test completed at: ' . date('Y-m-d H:i:s') . '</em></p>';
echo '<p><a href="javascript:window.location.reload()">🔄 Refresh Test</a></p>';

echo '    </div>
</body>
</html>';
?>

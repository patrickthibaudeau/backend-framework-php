<?php
/**
 * Framework Initialization Test Script
 * This script tests if the database and core tables are properly set up
 */

echo "=== DevFramework Initialization Test ===\n";
echo "Starting framework initialization test...\n\n";

try {
    // Include the framework helpers which will trigger initialization
    require_once __DIR__ . '/src/Core/helpers.php';

    echo "✓ Framework helpers loaded successfully\n";

    // Test database connection
    $db = db();
    $db->connect();
    echo "✓ Database connection established\n";

    // Test core tables installation
    $coreInstaller = new \DevFramework\Core\Database\CoreInstaller();
    if ($coreInstaller->areCoreTablesInstalled()) {
        echo "✓ Core tables are installed\n";
    } else {
        echo "⚠ Core tables need to be installed\n";
    }

    // Test configuration system
    $dbName = config('database.connections.mysql.database');
    echo "✓ Configuration system working - Database: {$dbName}\n";

    // Test module system
    $modules = list_modules();
    echo "✓ Module system working - Found " . count($modules) . " modules\n";

    // Test plugin version tracking
    $testVersion = get_plugin_version('User');
    if ($testVersion) {
        echo "✓ Plugin version tracking working - User module: v{$testVersion}\n";
    } else {
        echo "⚠ Plugin version tracking not initialized yet\n";
    }

    echo "\n=== Test Results ===\n";
    echo "✅ Framework initialization test completed successfully!\n";
    echo "The system should now work properly across all entry points.\n";

} catch (Exception $e) {
    echo "\n❌ Framework initialization test failed!\n";
    echo "Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    exit(1);
}

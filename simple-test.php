<?php

echo "=== Simple Module System Test ===\n";

// Test 1: Check if autoloader works
echo "1. Testing autoloader...\n";
require_once __DIR__ . '/vendor/autoload.php';
echo "   ✓ Autoloader loaded successfully\n";

// Test 2: Check if we can load the module classes
echo "2. Testing module classes...\n";
try {
    $moduleManager = new \DevFramework\Core\Module\ModuleManager();
    echo "   ✓ ModuleManager class loaded\n";
} catch (Exception $e) {
    echo "   ✗ Error loading ModuleManager: " . $e->getMessage() . "\n";
    exit(1);
}

// Test 3: Check basic functionality
echo "3. Testing basic module discovery...\n";
try {
    $moduleManager = \DevFramework\Core\Module\ModuleManager::getInstance();
    $moduleManager->discoverModules();
    $modules = $moduleManager->getAllModules();

    echo "   ✓ Found " . count($modules) . " modules\n";
    foreach ($modules as $module) {
        echo "     - {$module['name']} v{$module['version']}\n";
    }
} catch (Exception $e) {
    echo "   ✗ Error during module discovery: " . $e->getMessage() . "\n";
    echo "   Stack trace: " . $e->getTraceAsString() . "\n";
}

echo "\n=== Test Complete ===\n";

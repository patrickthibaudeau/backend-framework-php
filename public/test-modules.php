<?php

header('Content-Type: text/plain');

echo "=== Module System Web Test ===\n\n";

try {
    // Test 1: Check autoloader
    echo "1. Loading autoloader...\n";
    require_once __DIR__ . '/../vendor/autoload.php';
    echo "   ✓ Autoloader loaded successfully\n\n";

    // Test 2: Load module classes
    echo "2. Loading module classes...\n";
    $moduleManager = \DevFramework\Core\Module\ModuleManager::getInstance();
    echo "   ✓ ModuleManager instance created\n\n";

    // Test 3: Discover modules
    echo "3. Discovering modules...\n";
    $moduleManager->discoverModules();
    $modules = $moduleManager->getAllModules();

    echo "   ✓ Found " . count($modules) . " modules:\n";
    foreach ($modules as $module) {
        echo "     - {$module['name']} v{$module['version']} ({$module['maturity']})\n";
        echo "       Component: {$module['component']}\n";
        echo "       Path: {$module['path']}\n";
        echo "       Loaded: " . ($module['loaded'] ? 'Yes' : 'No') . "\n\n";
    }

    // Test 4: Test language functionality
    echo "4. Testing language system...\n";
    $languageManager = \DevFramework\Core\Module\LanguageManager::getInstance();

    // Load Auth module strings
    if (isset($modules['Auth'])) {
        echo "   Testing Auth module languages:\n";

        // Test English
        $authStrings = $languageManager->getModuleStrings('Auth', 'en');
        echo "     English strings: " . count($authStrings) . " found\n";
        if (isset($authStrings['login_success'])) {
            echo "       Login Success: " . $authStrings['login_success'] . "\n";
        }

        // Test French
        $authStringsFr = $languageManager->getModuleStrings('Auth', 'fr');
        echo "     French strings: " . count($authStringsFr) . " found\n";
        if (isset($authStringsFr['login_success'])) {
            echo "       Login Success (FR): " . $authStringsFr['login_success'] . "\n";
        }

        // Test Spanish
        $authStringsEs = $languageManager->getModuleStrings('Auth', 'es');
        echo "     Spanish strings: " . count($authStringsEs) . " found\n";
        if (isset($authStringsEs['login_success'])) {
            echo "       Login Success (ES): " . $authStringsEs['login_success'] . "\n";
        }
    }

    echo "\n5. Testing helper functions...\n";

    // Test if we can use the helper functions through the web interface
    if (function_exists('modules_path')) {
        echo "   ✓ modules_path() function available\n";
        echo "     Modules path: " . modules_path() . "\n";
    } else {
        echo "   ✗ modules_path() function not available\n";
    }

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}

echo "\n=== Test Complete ===\n";

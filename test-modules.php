<?php

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/src/Core/helpers.php';

echo "=== Module System Demonstration ===\n\n";

// 1. List all discovered modules
echo "1. Discovered Modules:\n";
$modules = list_modules();
foreach ($modules as $module) {
    echo "  - {$module['name']} v{$module['version']} ({$module['maturity']})\n";
    echo "    Component: {$module['component']}\n";
    echo "    Path: {$module['path']}\n";
    echo "    Loaded: " . ($module['loaded'] ? 'Yes' : 'No') . "\n\n";
}

// 2. Test module language functionality
echo "2. Testing Module Language System:\n";

// Test English (default)
echo "Auth module strings (English):\n";
try {
    echo "  - Login Success: " . module_lang('Auth', 'login_success') . "\n";
    echo "  - Access Denied: " . module_lang('Auth', 'access_denied') . "\n";
    echo "  - Account Locked: " . module_lang('Auth', 'account_locked') . "\n";
} catch (Exception $e) {
    echo "  Error: " . $e->getMessage() . "\n";
}

echo "\n";

// Test French
echo "Auth module strings (French):\n";
try {
    echo "  - Login Success: " . module_lang('Auth', 'login_success', [], 'fr') . "\n";
    echo "  - Access Denied: " . module_lang('Auth', 'access_denied', [], 'fr') . "\n";
    echo "  - Account Locked: " . module_lang('Auth', 'account_locked', [], 'fr') . "\n";
} catch (Exception $e) {
    echo "  Error: " . $e->getMessage() . "\n";
}

echo "\n";

// Test Spanish
echo "Auth module strings (Spanish):\n";
try {
    echo "  - Login Success: " . module_lang('Auth', 'login_success', [], 'es') . "\n";
    echo "  - Access Denied: " . module_lang('Auth', 'access_denied', [], 'es') . "\n";
    echo "  - Account Locked: " . module_lang('Auth', 'account_locked', [], 'es') . "\n";
} catch (Exception $e) {
    echo "  Error: " . $e->getMessage() . "\n";
}

echo "\n";

// 3. Test User module strings
echo "User module strings (English):\n";
try {
    echo "  - User Created: " . module_lang('User', 'user_created') . "\n";
    echo "  - Invalid Email: " . module_lang('User', 'invalid_email') . "\n";
    
    // Test parameter substitution
    echo "  - Password Length: " . module_lang('User', 'password_too_short', ['min_length' => 8]) . "\n";
} catch (Exception $e) {
    echo "  Error: " . $e->getMessage() . "\n";
}

echo "\n";

// 4. Test module availability checks
echo "3. Module Availability Tests:\n";
echo "  - Is 'Auth' module loaded? " . (is_module_loaded('Auth') ? 'Yes' : 'No') . "\n";
echo "  - Is 'User' module loaded? " . (is_module_loaded('User') ? 'Yes' : 'No') . "\n";
echo "  - Is 'NonExistent' module loaded? " . (is_module_loaded('NonExistent') ? 'Yes' : 'No') . "\n";

echo "\n";

// 5. Get module information
echo "4. Module Information:\n";
$authInfo = get_module_info('Auth');
if ($authInfo) {
    echo "Auth Module Details:\n";
    echo "  - Version: {$authInfo['version']}\n";
    echo "  - Release: {$authInfo['release']}\n";
    echo "  - Component: {$authInfo['component']}\n";
    echo "  - Maturity: {$authInfo['maturity']}\n";
}

echo "\n";

echo "=== Module System Demo Complete ===\n";

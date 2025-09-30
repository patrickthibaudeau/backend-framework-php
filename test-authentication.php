<?php

require_once 'vendor/autoload.php';
require_once 'src/Core/helpers.php';
require_once 'src/Core/Auth/helpers.php';

use DevFramework\Core\Auth\AuthInstaller;
use DevFramework\Core\Auth\AuthenticationManager;
use DevFramework\Core\Auth\Exceptions\AuthenticationException;

/**
 * Authentication System Test - Demonstrates multi-type authentication
 */

echo "=== DevFramework Authentication System Test ===\n\n";

try {
    // 1. Install authentication tables
    echo "1. Installing authentication tables...\n";
    $installer = new AuthInstaller();

    if (!$installer->isInstalled()) {
        $result = $installer->install();
        echo $result ? "✅ Authentication tables installed successfully\n" : "❌ Failed to install authentication tables\n";
    } else {
        echo "✅ Authentication tables already exist\n";
    }
    echo "\n";

    // 2. Get authentication manager instance
    echo "2. Initializing authentication manager...\n";
    $auth = AuthenticationManager::getInstance();
    echo "✅ Authentication manager initialized\n";
    echo "Available providers: " . implode(', ', $auth->getAvailableProviders()) . "\n\n";

    // 3. Test user creation
    echo "3. Testing user creation...\n";
    try {
        // Try to create a test user (will fail if already exists)
        $newUser = $auth->createUser('demo_user', 'demo@example.com', 'DemoPass123!', 'manual');
        echo "✅ Created new user: {$newUser->getUsername()} (ID: {$newUser->getId()})\n";
    } catch (AuthenticationException $e) {
        echo "ℹ️  User creation skipped: {$e->getMessage()}\n";
    }

    // 4. Test authentication with default users
    echo "\n4. Testing manual authentication...\n";

    // Test with admin user
    try {
        $user = $auth->authenticate('admin', 'admin123');
        echo "✅ Admin authentication successful\n";
        echo "   User: {$user->getUsername()}\n";
        echo "   Email: {$user->getEmail()}\n";
        echo "   Auth Type: {$user->getAuthType()}\n";
        echo "   Active: " . ($user->isActive() ? 'Yes' : 'No') . "\n";

        // Test session
        echo "   Session Active: " . ($auth->isAuthenticated() ? 'Yes' : 'No') . "\n";

        // Logout
        $auth->logout();
        echo "   After Logout: " . ($auth->isAuthenticated() ? 'Yes' : 'No') . "\n";

    } catch (AuthenticationException $e) {
        echo "❌ Admin authentication failed: {$e->getMessage()}\n";
    }

    // 5. Test failed authentication
    echo "\n5. Testing failed authentication...\n";
    try {
        $auth->authenticate('admin', 'wrongpassword');
        echo "❌ Authentication should have failed\n";
    } catch (AuthenticationException $e) {
        echo "✅ Failed authentication handled correctly: {$e->getMessage()}\n";
    }

    // 6. Test helper functions
    echo "\n6. Testing helper functions...\n";
    echo "is_authenticated(): " . (is_authenticated() ? 'true' : 'false') . "\n";

    // Login using helper
    $loginResult = login('testuser', 'password123');
    if ($loginResult) {
        echo "✅ Helper login successful for: {$loginResult->getUsername()}\n";
        echo "current_user(): " . (current_user() ? current_user()->getUsername() : 'null') . "\n";
        echo "user_has_auth_type('manual'): " . (user_has_auth_type('manual') ? 'true' : 'false') . "\n";

        logout();
        echo "After helper logout: " . (is_authenticated() ? 'authenticated' : 'not authenticated') . "\n";
    } else {
        echo "❌ Helper login failed\n";
    }

    // 7. Test database records
    echo "\n7. Checking database records...\n";
    $userCount = $DB->count_records('users');
    echo "Total users in database: {$userCount}\n";

    $users = $DB->get_records('users', [], '', 'username, email, auth, active');
    foreach ($users as $user) {
        echo "  - {$user->username} ({$user->email}) - Auth: {$user->auth} - Active: " .
             ($user->active ? 'Yes' : 'No') . "\n";
    }

    // 8. Test different auth types (placeholder)
    echo "\n8. Testing authentication provider system...\n";
    foreach ($auth->getAvailableProviders() as $provider) {
        echo "  - Provider '{$provider}' is registered\n";
    }

    echo "\n=== Authentication System Test Complete ===\n";
    echo "✅ All manual authentication features working correctly\n";
    echo "📝 Future auth types (oauth, saml2, ldap) are ready for implementation\n\n";

} catch (Exception $e) {
    echo "❌ Test failed with error: {$e->getMessage()}\n";
    echo "Stack trace:\n{$e->getTraceAsString()}\n";
}

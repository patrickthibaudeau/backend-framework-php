<?php
require_once '../vendor/autoload.php';
require_once '../src/Core/helpers.php';

use DevFramework\Core\Auth\AuthMiddleware;

// Set content type to JSON for API response
header('Content-Type: application/json');

// Create middleware instance
$middleware = new AuthMiddleware();

// Test API authentication
$middleware->apiAuth(function() {
    $user = current_user();

    // Return user data as JSON
    return json_encode([
        'success' => true,
        'message' => 'API authentication successful',
        'user' => $user->toArray(),
        'timestamp' => date('Y-m-d H:i:s'),
        'test_results' => [
            'authentication_working' => true,
            'session_active' => is_authenticated(),
            'user_loaded' => !is_null($user),
            'middleware_working' => true
        ]
    ]);
});
?>

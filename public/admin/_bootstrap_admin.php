<?php
// Common bootstrap for Admin UI
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../src/Core/helpers.php';
require_once __DIR__ . '/../../src/Core/Auth/helpers.php';

// Require login
require_auth('/login.php');

$currentUser = current_user();

if (!$currentUser) {
    http_response_code(401);
    echo 'Unauthorized';
    exit;
}

if (!hasCapability('rbac:manage', $currentUser->getId())) {
    http_response_code(403);
    echo 'Forbidden: Missing rbac:manage capability';
    exit;
}

// Simple CSRF token helper
if (!function_exists('admin_csrf_token')) {
    function admin_csrf_token(): string {
        if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
        if (empty($_SESSION['admin_csrf'])) {
            $_SESSION['admin_csrf'] = bin2hex(random_bytes(16));
        }
        return $_SESSION['admin_csrf'];
    }
}
if (!function_exists('admin_csrf_validate')) {
    function admin_csrf_validate(): bool {
        if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
        $token = $_POST['csrf_token'] ?? '';
        return isset($_SESSION['admin_csrf']) && hash_equals($_SESSION['admin_csrf'], $token);
    }
}

use DevFramework\Core\Access\AccessManager;
$AM = AccessManager::getInstance();
$db = db();

function admin_flash(string $type, string $msg): void {
    if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
    $_SESSION['admin_flash'][] = ['type'=>$type,'msg'=>$msg];
}
function admin_get_flashes(): array {
    if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
    $f = $_SESSION['admin_flash'] ?? [];
    unset($_SESSION['admin_flash']);
    return $f;
}


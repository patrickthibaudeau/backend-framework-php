<?php
// Login page
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../src/Core/helpers.php';

use DevFramework\Core\Auth\AuthenticationManager;
use DevFramework\Core\Auth\Exceptions\AuthenticationException;

// Ensure output instance
global $OUTPUT;

$auth = AuthenticationManager::getInstance();

// If already authenticated redirect to a sensible location
if ($auth->isAuthenticated()) {
    $user = $auth->getCurrentUser();
    $target = '/admin/index.php';
    if (!function_exists('hasCapability') || !$user || !hasCapability('rbac:manage', $user->getId())) {
        $target = '/';
    }
    header('Location: ' . $target);
    exit;
}

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'login') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    if ($username !== '' && $password !== '') {
        try {
            $auth->authenticate($username, $password, null);
            // After successful login decide redirect
            $redirect = '/admin/index.php';
            try {
                if (!hasCapability('rbac:manage', $auth->getCurrentUser()->getId())) {
                    $redirect = '/';
                }
            } catch (Throwable $e) {
                $redirect = '/';
            }
            header('Location: ' . $redirect);
            exit;
        } catch (AuthenticationException $e) {
            $error = $e->getMessage();
        } catch (Throwable $e) {
            $error = 'Unexpected authentication error';
        }
    } else {
        $error = 'Missing credentials';
    }
}

$loggedOut = isset($_GET['loggedout']);

$context = [
    'page_title'   => 'Login',
    'home_link'    => '/',
    'has_error'    => $error !== null,
    'error_message'=> $error,
    'logged_out'   => $loggedOut,
];

echo $OUTPUT->header(['page_title' => 'Login', 'site_name' => 'Admin Console', 'user' => []]);
echo $OUTPUT->renderFromTemplate('auth_login', $context);
echo $OUTPUT->footer();


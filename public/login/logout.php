<?php
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../src/Core/helpers.php';

use DevFramework\Core\Auth\AuthenticationManager;

$auth = AuthenticationManager::getInstance();
try {
    if ($auth->isAuthenticated()) {
        $auth->logout();
    }
} catch (Throwable $e) {
    // swallow errors; proceed with redirect
}
header('Location: /login/login.php?loggedout=1');
exit;


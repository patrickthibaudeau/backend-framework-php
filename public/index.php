<?php
/**
 * Web Application Entry Point
 * This file serves as the main entry point for web requests while preserving CLI functionality
 */

// Set up error reporting for development
if (getenv('APP_DEBUG') === 'true') {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
}

// Load Composer autoloader
require_once __DIR__ . '/../vendor/autoload.php';

// Load framework helpers
require_once __DIR__ . '/../src/Core/helpers.php';
require_once __DIR__ . '/../src/Core/Auth/helpers.php'; // added to ensure hasCapability is available

// Check for maintenance mode (before any other processing)
$maintenanceMode = new \DevFramework\Core\Maintenance\MaintenanceMode();
if ($maintenanceMode->isEnabled() && !$maintenanceMode->isAllowed()) {
    $maintenanceMode->displayMaintenancePage();
    // Script will exit here if in maintenance mode
}

// Simple routing for demonstration
$requestUri = $_SERVER['REQUEST_URI'];
$requestMethod = $_SERVER['REQUEST_METHOD'];

// Remove query string from URI
$path = parse_url($requestUri, PHP_URL_PATH);

// Basic routing
switch ($path) {
    case '/':
    case '/index.php':
        handleHome();
        break;

    case '/config':
        handleConfig();
        break;

    case '/health':
        handleHealth();
        break;

    case '/install-status':
        handleInstallStatus();
        break;

    case '/phpinfo':
        if (getenv('APP_DEBUG') === 'true') {
            phpinfo();
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'Not found']);
        }
        break;

    default:
        http_response_code(404);
        echo json_encode(['error' => 'Route not found']);
        break;
}

function handleHome()
{
    // Determine if JSON explicitly requested (query or Accept header)
    $acceptHeader = $_SERVER['HTTP_ACCEPT'] ?? '';
    $forceJson = (isset($_GET['format']) && $_GET['format'] === 'json') || str_contains($acceptHeader, 'application/json');

    $isAuthenticated = false; $currentUser = null; $canView = false;
    try {
        if (class_exists(\DevFramework\Core\Auth\AuthenticationManager::class)) {
            $am = \DevFramework\Core\Auth\AuthenticationManager::getInstance();
            if ($am->isAuthenticated()) {
                $isAuthenticated = true; $currentUser = $am->getCurrentUser();
                if ($currentUser && function_exists('hasCapability')) {
                    // Assumption: rbac:manage governs dashboard visibility.
                    $canView = hasCapability('rbac:manage', $currentUser->getId());
                }
            }
        }
    } catch (Throwable $e) { /* ignore auth failures */ }

    if (!$isAuthenticated) {
        if ($forceJson) {
            header('Content-Type: application/json');
            echo json_encode([
                'message' => 'Authentication required',
                'status' => 'unauthenticated',
                'login_url' => '/login/login.php',
                'timestamp' => date('c')
            ]);
            return;
        }
        header('Location: /login/login.php');
        exit;
    }

    $counts = null; global $DB;
    if ($canView && isset($DB)) { // only gather counts if user allowed to view
        try {
            $counts = [
                'roles' => $DB->count_records('roles'),
                'caps' => $DB->count_records('capabilities'),
                'users' => $DB->count_records('users'),
                'templates' => $DB->count_records('role_templates'),
            ];
        } catch (Throwable $e) { $counts = null; }
    }

    if ($forceJson) {
        header('Content-Type: application/json');
        echo json_encode([
            'message' => 'Development Framework Web Application',
            'version' => '1.0.0',
            'timestamp' => date('c'),
            'environment' => getenv('APP_ENV') ?: 'production',
            'debug' => getenv('APP_DEBUG') === 'true',
            'counts' => $canView ? $counts : null,
            'can_view' => $canView,
            'user' => $currentUser ? ['id'=>$currentUser->getId(),'username'=>$currentUser->getUsername()] : null,
            'available_endpoints' => [
                '/' => 'Home (HTML when authenticated unless ?format=json)',
                '/config' => 'Configuration information',
                '/health' => 'Health check',
                '/install-status' => 'Installation status of core tables',
                '/phpinfo' => 'PHP information (debug mode only)'
            ]
        ]);
        return;
    }

    global $OUTPUT; // initialized in helpers
    $context = [
        'environment' => getenv('APP_ENV') ?: 'production',
        'timestamp' => date('c'),
        'counts' => $counts,
        'has_counts' => $canView && is_array($counts),
        'can_view' => $canView,
    ];
    echo $OUTPUT->header([
        'page_title' => 'Welcome',
        'site_name' => 'Admin Console',
        'user' => ['username' => $currentUser ? $currentUser->getUsername() : ''],
    ]);
    echo $OUTPUT->renderFromTemplate('theme_welcome', $context);
    echo $OUTPUT->footer();
}

function handleConfig()
{
    header('Content-Type: application/json');

    // Use the framework's configuration classes
    try {
        // Check if configuration classes are available
        if (class_exists('DevFramework\Core\Config\Configuration')) {
            $config = \DevFramework\Core\Config\Configuration::getInstance();
            echo json_encode([
                'message' => 'Configuration loaded successfully',
                'config_class' => 'DevFramework\Core\Config\Configuration',
                'timestamp' => date('c')
            ]);
        } else {
            echo json_encode([
                'message' => 'Configuration classes available',
                'note' => 'Framework configuration system is ready',
                'timestamp' => date('c')
            ]);
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'error' => 'Configuration error',
            'message' => $e->getMessage(),
            'timestamp' => date('c')
        ]);
    }
}

function handleHealth()
{
    header('Content-Type: application/json');
    if (!class_exists(\DevFramework\Core\Maintenance\HealthChecker::class)) {
        // Fallback to legacy logic if class missing
        $status = [ 'status'=>'healthy','timestamp'=>date('c'),'services'=>[],'php_extensions'=>[] ];
        try {
            if (getenv('MYSQL_HOST') || isset($_ENV['MYSQL_HOST'])) { $status['services']['mysql']='available'; }
            if (class_exists('Redis')) { $status['services']['redis']='extension_loaded'; }
            foreach (get_loaded_extensions() as $ext) { $status['php_extensions'][$ext]=true; }
            ksort($status['php_extensions']);
        } catch (Exception $e) { $status['status']='degraded'; $status['error']=$e->getMessage(); }
        echo json_encode($status); return;
    }
    $data = \DevFramework\Core\Maintenance\HealthChecker::gather();
    echo json_encode($data);
}

function handleInstallStatus()
{
    header('Content-Type: application/json');

    try {
        // Get the actual core installation status
        $status = get_core_installation_status();

        echo json_encode([
            'status' => $status['all_core_installed'] ? 'complete' : 'incomplete',
            'message' => $status['all_core_installed'] ? 'All core tables are installed' : 'Some core tables are missing',
            'tables' => $status,
            'timestamp' => date('c')
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'error' => 'Installation status check failed',
            'message' => $e->getMessage(),
            'timestamp' => date('c')
        ]);
    }
}

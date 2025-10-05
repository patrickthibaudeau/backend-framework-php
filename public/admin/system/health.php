<?php
require_once dirname(__DIR__) . '/_bootstrap_admin.php';

global $OUTPUT, $currentUser;

use DevFramework\Core\Maintenance\HealthChecker;

$data = class_exists(HealthChecker::class) ? HealthChecker::gather() : [ 'status'=>'unknown','timestamp'=>date('c'),'services'=>[],'php_extensions'=>[] ];

$statusClass = match($data['status'] ?? 'unknown') {
    'healthy' => 'bg-green-100 text-green-800 border border-green-200',
    'degraded' => 'bg-amber-100 text-amber-800 border border-amber-200',
    default => 'bg-slate-100 text-slate-700 border border-slate-200'
};

$servicesView = [];
foreach (($data['services'] ?? []) as $name => $val) {
    $svcClass = in_array($val, ['available','extension_loaded']) ? 'text-green-700' : ($val==='not_loaded' ? 'text-slate-500' : 'text-amber-700');
    $servicesView[] = [ 'name'=>$name, 'value'=>$val, 'class'=>$svcClass ];
}
ksort($servicesView);

$extensions = array_keys($data['php_extensions'] ?? []);

$context = [
    'page_title' => 'System Health',
    'home_link' => '../index.php',
    'status' => $data['status'] ?? 'unknown',
    'status_class' => $statusClass,
    'generated_at' => $data['timestamp'] ?? date('c'),
    'services' => $servicesView,
    'has_services' => count($servicesView) > 0,
    'extensions' => array_map(fn($e)=>['name'=>$e], $extensions),
    'ext_count' => count($extensions),
    'has_extensions' => count($extensions) > 0,
    'has_error' => isset($data['error']),
    'error_message' => $data['error'] ?? null,
];

echo $OUTPUT->header([
    'page_title' => 'System Health',
    'site_name' => 'Admin Console',
    'user' => ['username'=>$currentUser->getUsername()],
]);
echo $OUTPUT->renderFromTemplate('admin_health', $context);
echo $OUTPUT->footer();


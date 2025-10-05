<?php
require_once __DIR__ . '/_bootstrap_admin.php';

global $DB; // use global DB

$counts = [
    'roles' => $DB->count_records('roles'),
    'caps' => $DB->count_records('capabilities'),
    'users' => $DB->count_records('users'),
    'templates' => $DB->count_records('role_templates'),
];
$flashes = admin_get_flashes();

// Transform flashes for template (assign css_class)
$flashView = array_map(function($f){
    $f['css_class'] = $f['type']==='error' ? 'bg-red-200 text-red-900' : 'bg-green-200 text-green-900';
    return $f;
}, $flashes);

global $OUTPUT;

echo $OUTPUT->header([
    'page_title' => 'Administration',
    'site_name' => 'Admin Console',
    'user' => ['username'=>$currentUser->getUsername()],
]);

echo $OUTPUT->renderFromTemplate('admin_index', [
    'counts' => $counts,
    'flashes' => $flashView,
]);

echo $OUTPUT->footer();

<?php
require_once __DIR__ . '/_bootstrap_admin.php';
use DevFramework\Core\Access\AccessManager;

if (!hasCapability('rbac:viewaudit', $currentUser->getId())) { http_response_code(403); echo 'Forbidden'; exit; }

global $OUTPUT, $DB; // use global DB

// Filters
$fAction = trim($_GET['action'] ?? '');
$fActor  = trim($_GET['actor'] ?? '');
$fRole   = trim($_GET['role'] ?? '');
$fCap    = trim($_GET['cap'] ?? '');
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 50;
$offset  = ($page - 1) * $perPage;

$logConditions = [];

// Translate filters to simple conditions we can apply directly
if ($fAction !== '') { $logConditions['action'] = $fAction; }
if ($fCap !== '') { $logConditions['capability'] = $fCap; }

// Actor filter: numeric id or username -> actorid
if ($fActor !== '') {
    if (ctype_digit($fActor)) {
        $logConditions['actorid'] = (int)$fActor;
    } else {
        $actorUser = $DB->get_record('users',['username'=>$fActor]);
        if ($actorUser) { $logConditions['actorid'] = $actorUser->id; } else { $logConditions['actorid'] = -1; }
    }
}
// Role filter: numeric id or shortname -> targetroleid
if ($fRole !== '') {
    if (ctype_digit($fRole)) {
        $logConditions['targetroleid'] = (int)$fRole;
    } else {
        $roleRec = $DB->get_record('roles',['shortname'=>$fRole]);
        if ($roleRec) { $logConditions['targetroleid'] = $roleRec->id; } else { $logConditions['targetroleid'] = -1; }
    }
}

// Total count via count_records (if sentinel -1 inserted, count will be 0 unless such id exists)
$total = $DB->count_records('role_audit_log', $logConditions);
$perPage = 50;
$pages = max(1, (int)ceil($total / $perPage));
if ($page > $pages) { $page = $pages; }
$offset = ($page - 1) * $perPage;

$rowsRaw = [];
if ($total > 0) {
    // Fetch page of rows ordered by id DESC
    $rowsRaw = $DB->get_records('role_audit_log', $logConditions, 'id DESC', '*', $offset, $perPage);
}

// Enrich rows with related data
$rowsView = [];
foreach ($rowsRaw as $r) {
    $details = $r->details ?? '';
    $short = $details !== '' ? (mb_strlen($details) > 60 ? mb_substr($details,0,60).'…' : $details) : '';
    $actorUser = $r->actorid ? $DB->get_record('users',['id'=>$r->actorid]) : null;
    $targetUser = $r->userid ? $DB->get_record('users',['id'=>$r->userid]) : null;
    $roleRec = $r->targetroleid ? $DB->get_record('roles',['id'=>$r->targetroleid]) : null;
    $rowsView[] = [
        'id' => (int)$r->id,
        'action' => $r->action,
        'actor' => $actorUser ? $actorUser->username : ($r->actorid ?? ''),
        'targetuser' => $targetUser ? $targetUser->username : ($r->userid ?? ''),
        'role' => $roleRec ? $roleRec->shortname : ($r->targetroleid ?? ''),
        'capability' => $r->capability ?? '',
        'details_short' => $short,
        'details_full' => $details,
        'ip' => $r->ip ?? '',
        'time' => date('Y-m-d H:i:s',(int)$r->timecreated),
        'time_iso' => date('c',(int)$r->timecreated)
    ];
}

$pagesView = [];
for ($p=1;$p<=$pages;$p++) {
    $query = $_GET; $query['page']=$p;
    $pagesView[] = [ 'num'=>$p, 'current'=>$p===$page, 'query'=>http_build_query($query) ];
}

$context = [
    'filters' => [ 'action'=>$fAction, 'actor'=>$fActor, 'role'=>$fRole, 'cap'=>$fCap ],
    'rows' => $rowsView,
    'pagination' => [ 'page'=>$page, 'total'=>$total, 'pages'=>$pagesView ]
];

echo $OUTPUT->header([
    'page_title' => 'Audit Log',
    'site_name' => 'Admin Console',
    'user' => ['username'=>$currentUser->getUsername()],
]);

echo $OUTPUT->renderFromTemplate('admin_audit', $context);

echo $OUTPUT->footer();

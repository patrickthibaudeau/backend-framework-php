<?php
// Dedicated role assignment page: list roles, view assignments, add/remove users.
require_once dirname(__DIR__) . '/_bootstrap_admin.php';

global $OUTPUT, $currentUser; // ensure globals recognized by static analysis

use DevFramework\Core\Access\AccessManager;

$AM = AccessManager::getInstance();

global $DB; // use global DB

function ra_fetch_roles(){ global $DB; return $DB->get_records('roles', [], 'sortorder ASC, id ASC'); }

$action = $_POST['action'] ?? null;
$roleId = isset($_GET['role']) ? (int)$_GET['role'] : null;

// Validate selected role if any
$selectedRole = $roleId ? $DB->get_record('roles', ['id'=>$roleId]) : null;

if ($action === 'assign_users_batch' && $selectedRole) {
    if (!admin_csrf_validate()) { admin_flash('error','CSRF failed'); header('Location: role_assign.php?role='.$selectedRole->id); exit; }
    $userids = $_POST['userids'] ?? [];
    if (!is_array($userids)) { $userids = []; }
    $now = time(); $added = 0;
    foreach ($userids as $uidRaw) {
        $uid = (int)$uidRaw; if ($uid <= 0) { continue; }
        $existing = $DB->get_record('role_assignment',[ 'userid'=>$uid,'roleid'=>$selectedRole->id,'component'=>null ]);
        if ($existing) { continue; }
        $DB->insert_record('role_assignment',[ 'userid'=>$uid,'roleid'=>$selectedRole->id,'component'=>null,'timecreated'=>$now,'timemodified'=>$now ]);
        $AM->logAction($currentUser->getId(),'role_assign',['userid'=>$uid],$uid,$selectedRole->id);
        $added++;
    }
    if ($added>0) { admin_flash('success', $added.' user(s) added'); } else { admin_flash('error','No users added'); }
    header('Location: role_assign.php?role='.$selectedRole->id); exit;
}

if ($action === 'unassign_user' && $selectedRole) {
    if (!admin_csrf_validate()) { admin_flash('error','CSRF failed'); header('Location: role_assign.php?role='.$selectedRole->id); exit; }
    $userid = (int)($_POST['userid'] ?? 0);
    if ($userid > 0) {
        $user = $DB->get_record('users',['id'=>$userid]);
        if ($user && $selectedRole->shortname==='admin' && $user->username==='admin') {
            admin_flash('error','Cannot unassign the primary admin user from the Administrator role.');
            header('Location: role_assign.php?role='.$selectedRole->id); exit;
        }
        $DB->delete_records('role_assignment',[ 'userid'=>$userid,'roleid'=>$selectedRole->id ]);
        $AM->logAction($currentUser->getId(),'role_unassign',['userid'=>$userid],$userid,$selectedRole->id);
        admin_flash('success','User unassigned');
    }
    header('Location: role_assign.php?role='.$selectedRole->id); exit;
}

// AJAX search for users not yet assigned to selected role
if ($selectedRole && isset($_GET['user_search']) && (int)$_GET['user_search'] === 1) {
    header('Content-Type: application/json');
    $q = trim($_GET['q'] ?? '');
    if (strlen($q) < 2) { echo json_encode([]); exit; }
    $assignments = $DB->get_records('role_assignment',['roleid'=>$selectedRole->id]);
    $assignedIds = array_map(fn($a)=>$a->userid, $assignments);
    $pattern = '%'.$q.'%';
    $params = [ ':q1'=>$pattern, ':q2'=>$pattern, ':q3'=>$pattern, ':q4'=>$pattern, ':q5'=>$pattern ];
    $notIn = '';
    if (!empty($assignedIds)) {
        $phs=[]; foreach ($assignedIds as $i=>$uid) { $ph=':u'.$i; $phs[]=$ph; $params[$ph]=$uid; }
        $notIn = ' AND id NOT IN ('.implode(',', $phs).')';
    }
    $prefixUsers = $DB->addPrefix('users');
    $sql = "SELECT id, username, email, firstname, lastname, idnumber FROM {$prefixUsers} WHERE (username LIKE :q1 OR email LIKE :q2 OR firstname LIKE :q3 OR lastname LIKE :q4 OR idnumber LIKE :q5) {$notIn} ORDER BY username ASC LIMIT 20";
    $rows = $DB->get_records_sql($sql, $params);
    $out = array_map(function($r){ $fullname = trim(($r->firstname ?? '').' '.($r->lastname ?? '')); return [ 'id'=>$r->id,'username'=>$r->username,'email'=>$r->email,'fullname'=>$fullname ?: null,'idnumber'=>$r->idnumber ?? null ]; }, $rows);
    echo json_encode($out); exit;
}

$flashes = admin_get_flashes();
$flashView = array_map(function($f){ $f['css_class'] = $f['type']==='error' ? 'bg-red-200 text-red-900' : 'bg-green-200 text-green-900'; return $f; }, $flashes);

$rolesRaw = ra_fetch_roles();
$rolesView = array_map(fn($r)=>['id'=>$r->id,'name'=>$r->name,'shortname'=>$r->shortname,'sortorder'=>(int)$r->sortorder,'selected'=>($selectedRole && $r->id==$selectedRole->id)], $rolesRaw);

$context = [
    'flashes'=>$flashView,
    'roles'=>$rolesView,
    'csrf_token'=>admin_csrf_token(),
    'home_link'=>'../index.php',
    'has_role'=> (bool)$selectedRole,
];

if ($selectedRole) {
    $assignments = $DB->get_records('role_assignment',['roleid'=>$selectedRole->id]);
    $userAssignments = [];
    foreach ($assignments as $a) { $u = $DB->get_record('users',['id'=>$a->userid]); if ($u) { $userAssignments[] = ['id'=>$u->id,'username'=>$u->username]; } }
    $context['role'] = [ 'id'=>$selectedRole->id, 'name'=>$selectedRole->name, 'shortname'=>$selectedRole->shortname ];
    $context['user_assignments'] = $userAssignments;
    $context['role_id'] = $selectedRole->id; // Added for template convenience
}

// Render
// Intentionally simpler header title

echo $OUTPUT->header([ 'page_title' => 'Assign Users to Roles','site_name' => 'Admin Console','user' => ['username'=>$currentUser->getUsername()] ]);
echo $OUTPUT->renderFromTemplate('admin_role_assign', $context);
echo $OUTPUT->footer();

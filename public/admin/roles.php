<?php
require_once __DIR__ . '/_bootstrap_admin.php';
use DevFramework\Core\Access\AccessManager;
$AM = AccessManager::getInstance();

global $DB; // ensure global DB accessible

function fetch_roles() {
    global $DB; return $DB->get_records('roles', [], 'sortorder ASC, id ASC');
}
function fetch_templates() {
    global $DB; return $DB->get_records('role_templates', [], 'shortname ASC');
}

$action = $_POST['action'] ?? null;
$roleId = isset($_GET['edit']) ? (int)$_GET['edit'] : null;

if ($action === 'create_role') {
    if (!admin_csrf_validate()) { admin_flash('error','CSRF failed'); header('Location: roles.php'); exit; }
    $shortname = trim($_POST['shortname'] ?? '');
    $name = trim($_POST['name'] ?? '');
    $sortorder = (int)($_POST['sortorder'] ?? 100);
    $description = trim($_POST['description'] ?? '');
    if ($shortname && $name) {
        if ($DB->record_exists('roles', ['shortname'=>$shortname])) {
            admin_flash('error','Shortname already exists');
        } else {
            $now=time();
            $DB->insert_record('roles', [ 'name'=>$name,'shortname'=>$shortname,'description'=>$description,'sortorder'=>$sortorder,'timecreated'=>$now,'timemodified'=>$now ]);
            $AM->logAction($currentUser->getId(), 'create_role', ['shortname'=>$shortname]);
            admin_flash('success','Role created');
        }
    } else {
        admin_flash('error','Missing required fields');
    }
    header('Location: roles.php'); exit;
}

if ($action === 'update_role_caps' && $roleId) {
    if (!admin_csrf_validate()) { admin_flash('error','CSRF failed'); header('Location: roles.php?edit='.$roleId); exit; }
    $role = $DB->get_record('roles',['id'=>$roleId]);
    if (!$role) { admin_flash('error','Role not found'); header('Location: roles.php'); exit; }
    $caps = $DB->get_records('capabilities', [], 'name ASC', 'id, name');
    $now=time();
    $changed = 0;
    foreach ($caps as $c) {
        $field = 'cap_'.md5($c->name);
        $perm = $_POST[$field] ?? 'notset';
        if (!in_array($perm,['notset','allow','prevent','prohibit'],true)) { $perm='notset'; }
        $existing = $DB->get_record('role_capabilities',['roleid'=>$roleId,'capability'=>$c->name]);
        if ($existing) {
            if ($existing->permission === $perm) { continue; }
            $DB->update_record('role_capabilities',[ 'id'=>$existing->id, 'permission'=>$perm, 'timemodified'=>$now ]);
        } else {
            $DB->insert_record('role_capabilities',[ 'roleid'=>$roleId,'capability'=>$c->name,'permission'=>$perm,'timecreated'=>$now,'timemodified'=>$now ]);
        }
        $changed++;
        $AM->logAction($currentUser->getId(),'role_capability_set',['cap'=>$c->name,'perm'=>$perm],null,$roleId,$c->name);
    }
    // Templates assignment handling
    $templates = fetch_templates();
    $selectedTemplates = $_POST['templates'] ?? [];
    $currentAssignments = $DB->get_records('role_template_assign',['roleid'=>$roleId]);
    $currentIds = array_map(fn($o)=>$o->templateid,$currentAssignments);
    foreach ($templates as $t) {
        $checked = in_array((string)$t->id,$selectedTemplates,true);
        $had = in_array($t->id,$currentIds,true);
        if ($checked && !$had) {
            $DB->insert_record('role_template_assign',[ 'roleid'=>$roleId,'templateid'=>$t->id,'timecreated'=>$now,'timemodified'=>$now ]);
            $AM->logAction($currentUser->getId(),'role_template_add',['templateid'=>$t->id],null,$roleId);
            $changed++;
        } elseif (!$checked && $had) {
            $DB->delete_records('role_template_assign',[ 'roleid'=>$roleId,'templateid'=>$t->id ]);
            $AM->logAction($currentUser->getId(),'role_template_remove',['templateid'=>$t->id],null,$roleId);
            $changed++;
        }
    }
    $DB->update_record('roles',[ 'id'=>$roleId, 'timemodified'=>$now ]);
    $AM->logAction($currentUser->getId(),'role_caps_modified',['roleid'=>$roleId,'changed'=>$changed]);
    $AM->resetCache();
    admin_flash('success', 'Role updated ('. $changed . ' changes)');
    header('Location: roles.php?edit='.$roleId); exit;
}

if ($action==='assign_user' && $roleId) {
    if (!admin_csrf_validate()) { admin_flash('error','CSRF failed'); header('Location: roles.php?edit='.$roleId); exit; }
    $userid=(int)($_POST['userid']??0);
    if ($userid>0) {
        $existing = $DB->get_record('role_assignment',[ 'userid'=>$userid,'roleid'=>$roleId,'component'=>null ]);
        $now=time();
        if ($existing) {
            $DB->update_record('role_assignment',[ 'id'=>$existing->id,'timemodified'=>$now ]);
        } else {
            $DB->insert_record('role_assignment',[ 'userid'=>$userid,'roleid'=>$roleId,'component'=>null,'timecreated'=>$now,'timemodified'=>$now ]);
        }
        $AM->logAction($currentUser->getId(),'role_assign',['userid'=>$userid],$userid,$roleId);
        admin_flash('success','User assigned');
    }
    header('Location: roles.php?edit='.$roleId); exit;
}
if ($action==='unassign_user' && $roleId) {
    if (!admin_csrf_validate()) { admin_flash('error','CSRF failed'); header('Location: roles.php?edit='.$roleId); exit; }
    $userid=(int)($_POST['userid']??0);
    if ($userid>0) {
        $role = $DB->get_record('roles',['id'=>$roleId]);
        $user = $DB->get_record('users',['id'=>$userid]);
        if ($role && $user && $role->shortname==='admin' && $user->username==='admin') {
            admin_flash('error','Cannot unassign the primary admin user from the Administrator role.');
            header('Location: roles.php?edit='.$roleId); exit;
        }
        $DB->delete_records('role_assignment',[ 'userid'=>$userid,'roleid'=>$roleId ]);
        $AM->logAction($currentUser->getId(),'role_unassign',['userid'=>$userid],$userid,$roleId);
        admin_flash('success','User unassigned');
    }
    header('Location: roles.php?edit='.$roleId); exit;
}

if ($action === 'update_role_meta' && $roleId) {
    if (!admin_csrf_validate()) { admin_flash('error','CSRF failed'); header('Location: roles.php?edit='.$roleId); exit; }
    $role = $DB->get_record('roles',['id'=>$roleId]);
    if (!$role) { admin_flash('error','Role not found'); header('Location: roles.php'); exit; }
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $sortorder = (int)($_POST['sortorder'] ?? $role->sortorder);
    if ($name === '') { admin_flash('error','Name cannot be empty'); header('Location: roles.php?edit='.$roleId); exit; }
    $DB->update_record('roles',[ 'id'=>$roleId, 'name'=>$name, 'description'=>$description, 'sortorder'=>$sortorder, 'timemodified'=>time() ]);
    $AM->logAction($currentUser->getId(),'role_update_meta',['roleid'=>$roleId,'name'=>$name]);
    admin_flash('success','Role details updated');
    $AM->resetCache();
    header('Location: roles.php?edit='.$roleId); exit;
}

$flashes = admin_get_flashes();
$editingRole = $roleId ? $DB->get_record('roles',['id'=>$roleId]) : null;

$flashView = array_map(function($f){
    $f['css_class'] = $f['type']==='error' ? 'bg-red-200 text-red-900' : 'bg-green-200 text-green-900';
    return $f;
}, $flashes);

$rolesRaw = fetch_roles();
$rolesView = array_map(fn($r)=>[
    'id'=>$r->id,
    'name'=>$r->name,
    'shortname'=>$r->shortname,
    'sortorder'=>(int)$r->sortorder
], $rolesRaw);

$context = [
    'flashes'=>$flashView,
    'roles'=>$rolesView,
    'csrf_token'=>admin_csrf_token(),
];

if ($editingRole) {
    $caps = $DB->get_records('capabilities', [], 'component ASC, name ASC');
    $roleCaps = $DB->get_records('role_capabilities',['roleid'=>$editingRole->id]);
    $roleCapMap = [];
    foreach ($roleCaps as $rc) { $roleCapMap[$rc->capability] = $rc->permission; }
    $templates = fetch_templates();
    $assignedTemplates = $DB->get_records('role_template_assign',['roleid'=>$editingRole->id]);
    $assignedTemplateIds = array_map(fn($o)=>$o->templateid,$assignedTemplates);
    // Build user assignments without join
    $assignments = $DB->get_records('role_assignment',['roleid'=>$editingRole->id]);
    $userAssignments = [];
    foreach ($assignments as $a) {
        $u = $DB->get_record('users',['id'=>$a->userid]);
        if ($u) { $userAssignments[] = (object)['id'=>$u->id,'username'=>$u->username]; }
    }

    $templatesView = array_map(function($t) use ($assignedTemplateIds){
        return [ 'id'=>$t->id, 'shortname'=>$t->shortname, 'checked'=>in_array($t->id,$assignedTemplateIds,true) ];
    }, $templates);
    $capView = array_map(function($c) use ($roleCapMap){
        $perm = $roleCapMap[$c->name] ?? 'notset';
        $options = [];
        foreach(['notset','allow','prevent','prohibit'] as $p){
            $options[] = ['value'=>$p,'selected'=>$p===$perm];
        }
        return [
            'name'=>$c->name,
            'type'=>$c->captype,
            'field'=>'cap_'.md5($c->name),
            'options'=>$options
        ];
    }, $caps);
    $usersView = array_map(fn($u)=>['id'=>$u->id,'username'=>$u->username], $userAssignments);

    $context['editing'] = true;
    $context['role'] = [
        'id'=>$editingRole->id,
        'name'=>$editingRole->name,
        'description'=>$editingRole->description ?? '',
        'sortorder'=>(int)$editingRole->sortorder
    ];
    $context['templates'] = $templatesView;
    $context['capabilities'] = $capView;
    $context['user_assignments'] = $usersView;
}

global $OUTPUT;
echo $OUTPUT->header([
    'page_title' => 'Roles',
    'site_name' => 'Admin Console',
    'user' => ['username'=>$currentUser->getUsername()],
]);

echo $OUTPUT->renderFromTemplate('admin_roles', $context);

echo $OUTPUT->footer();

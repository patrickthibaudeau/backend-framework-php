<?php
require_once __DIR__ . '/_bootstrap_admin.php';
use DevFramework\Core\Access\AccessManager;
$AM = AccessManager::getInstance();

// Additional capability gating (manage only)
if (!hasCapability('rbac:manage', $currentUser->getId())) {
    http_response_code(403); echo 'Forbidden'; exit;
}

$action = $_POST['action'] ?? null;
$roleId = isset($_GET['edit']) ? (int)$_GET['edit'] : null;

// Helper fetches
function fetch_roles() {
    return db()->get_records_sql("SELECT * FROM ".db()->addPrefix('roles')." ORDER BY sortorder ASC, id ASC");
}
function fetch_templates() {
    return db()->get_records_sql("SELECT * FROM ".db()->addPrefix('role_templates')." ORDER BY shortname ASC");
}

if ($action === 'create_role') {
    if (!admin_csrf_validate()) { admin_flash('error','CSRF failed'); header('Location: roles.php'); exit; }
    $shortname = trim($_POST['shortname'] ?? '');
    $name = trim($_POST['name'] ?? '');
    $sortorder = (int)($_POST['sortorder'] ?? 100);
    $description = trim($_POST['description'] ?? '');
    if ($shortname && $name) {
        if (db()->record_exists('roles', ['shortname'=>$shortname])) {
            admin_flash('error','Shortname already exists');
        } else {
            $now=time();
            db()->insert_record('roles', [ 'name'=>$name,'shortname'=>$shortname,'description'=>$description,'sortorder'=>$sortorder,'timecreated'=>$now,'timemodified'=>$now ]);
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
    $role = db()->get_record('roles',['id'=>$roleId]);
    if (!$role) { admin_flash('error','Role not found'); header('Location: roles.php'); exit; }
    $caps = db()->get_records_sql("SELECT name FROM ".db()->addPrefix('capabilities')." ORDER BY name");
    $roleCapsTable = db()->addPrefix('role_capabilities');
    $now=time();
    $changed = 0;
    foreach ($caps as $c) {
        $field = 'cap_'.md5($c->name);
        $perm = $_POST[$field] ?? 'notset';
        if (!in_array($perm,['notset','allow','prevent','prohibit'],true)) { $perm='notset'; }
        // Fetch existing permission once (avoid extra call if not needed)
        $existingRec = db()->get_records_sql("SELECT permission FROM {$roleCapsTable} WHERE roleid=? AND capability=?",[$roleId,$c->name]);
        $existingPerm = $existingRec ? reset($existingRec)->permission : null;
        if ($existingPerm === $perm) { continue; } // no change
        // Upsert even for 'notset' so DB reflects explicit user choice
        db()->execute(
            "INSERT INTO {$roleCapsTable} (roleid,capability,permission,timecreated,timemodified) VALUES (?,?,?,?,?) ".
            "ON DUPLICATE KEY UPDATE permission=VALUES(permission), timemodified=VALUES(timemodified)",
            [$roleId,$c->name,$perm,$now,$now]
        );
        $changed++;
        $AM->logAction($currentUser->getId(),'role_capability_set',['cap'=>$c->name,'perm'=>$perm],null,$roleId,$c->name);
    }
    // Templates assignment
    $templateAssign = db()->addPrefix('role_template_assign');
    $templates = fetch_templates();
    $selectedTemplates = $_POST['templates'] ?? [];
    $currentTemplates = db()->get_records_sql("SELECT templateid FROM {$templateAssign} WHERE roleid=?",[$roleId]);
    $currentIds = array_map(fn($o)=>$o->templateid,$currentTemplates);
    // Add new
    foreach ($templates as $t) {
        $checked = in_array((string)$t->id,$selectedTemplates,true);
        $had = in_array($t->id,$currentIds,true);
        if ($checked && !$had) {
            db()->execute("INSERT INTO {$templateAssign} (roleid, templateid, timecreated, timemodified) VALUES (?,?,?,?)",[$roleId,$t->id,$now,$now]);
            $AM->logAction($currentUser->getId(),'role_template_add',['templateid'=>$t->id],null,$roleId);
            $changed++;
        } elseif (!$checked && $had) {
            db()->execute("DELETE FROM {$templateAssign} WHERE roleid=? AND templateid=?",[$roleId,$t->id]);
            $AM->logAction($currentUser->getId(),'role_template_remove',['templateid'=>$t->id],null,$roleId);
            $changed++;
        }
    }
    db()->update_record('roles',[ 'id'=>$roleId, 'timemodified'=>time() ]);
    $AM->logAction($currentUser->getId(),'role_caps_modified',['roleid'=>$roleId,'changed'=>$changed]);
    $AM->resetCache();
    admin_flash('success', 'Role updated ('. $changed . ' changes)');
    header('Location: roles.php?edit='.$roleId); exit;
}

if ($action==='assign_user' && $roleId) {
    if (!admin_csrf_validate()) { admin_flash('error','CSRF failed'); header('Location: roles.php?edit='.$roleId); exit; }
    $userid=(int)($_POST['userid']??0);
    if ($userid>0) {
        $assignTable = db()->addPrefix('role_assignment');
        $now=time();
        db()->execute("INSERT INTO {$assignTable} (userid,roleid,component,timecreated,timemodified) VALUES (?,?,?,?,?) ON DUPLICATE KEY UPDATE timemodified=VALUES(timemodified)",[$userid,$roleId,null,$now,$now]);
        $AM->logAction($currentUser->getId(),'role_assign',['userid'=>$userid],$userid,$roleId);
        admin_flash('success','User assigned');
    }
    header('Location: roles.php?edit='.$roleId); exit;
}
if ($action==='unassign_user' && $roleId) {
    if (!admin_csrf_validate()) { admin_flash('error','CSRF failed'); header('Location: roles.php?edit='.$roleId); exit; }
    $userid=(int)($_POST['userid']??0);
    if ($userid>0) {
        $role = db()->get_record('roles',['id'=>$roleId]);
        $user = db()->get_record('users',['id'=>$userid]);
        if ($role && $user && $role->shortname==='admin' && $user->username==='admin') {
            admin_flash('error','Cannot unassign the primary admin user from the Administrator role.');
            header('Location: roles.php?edit='.$roleId); exit;
        }
        $assignTable = db()->addPrefix('role_assignment');
        db()->execute("DELETE FROM {$assignTable} WHERE userid=? AND roleid=?",[$userid,$roleId]);
        $AM->logAction($currentUser->getId(),'role_unassign',['userid'=>$userid],$userid,$roleId);
        admin_flash('success','User unassigned');
    }
    header('Location: roles.php?edit='.$roleId); exit;
}

if ($action === 'update_role_meta' && $roleId) {
    if (!admin_csrf_validate()) { admin_flash('error','CSRF failed'); header('Location: roles.php?edit='.$roleId); exit; }
    $role = db()->get_record('roles',['id'=>$roleId]);
    if (!$role) { admin_flash('error','Role not found'); header('Location: roles.php'); exit; }
    // Prevent changing shortname for admin role to avoid accidental lockouts
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $sortorder = (int)($_POST['sortorder'] ?? $role->sortorder);
    if ($name === '') { admin_flash('error','Name cannot be empty'); header('Location: roles.php?edit='.$roleId); exit; }
    db()->update_record('roles',[ 'id'=>$roleId, 'name'=>$name, 'description'=>$description, 'sortorder'=>$sortorder, 'timemodified'=>time() ]);
    $AM->logAction($currentUser->getId(),'role_update_meta',['roleid'=>$roleId,'name'=>$name]);
    admin_flash('success','Role details updated');
    $AM->resetCache();
    header('Location: roles.php?edit='.$roleId); exit;
}

$flashes = admin_get_flashes();
$editingRole = $roleId ? db()->get_record('roles',['id'=>$roleId]) : null;
?>
<!DOCTYPE html><html><head><meta charset="utf-8"><title>Roles</title><?= tailwind_cdn(); ?></head>
<body class="bg-gray-100">
<div class="max-w-7xl mx-auto p-6">
  <div class="flex items-center justify-between mb-6">
    <h1 class="text-2xl font-bold">Roles</h1>
    <a href="index.php" class="text-blue-600 hover:underline">← Admin Home</a>
  </div>
  <?php if ($flashes): foreach($flashes as $f): ?>
    <div class="mb-4 p-3 rounded <?= $f['type']==='error'?'bg-red-200 text-red-900':'bg-green-200 text-green-900' ?>"><?= htmlspecialchars($f['msg']); ?></div>
  <?php endforeach; endif; ?>
  <div class="grid md:grid-cols-<?= $editingRole? '3':'2' ?> gap-6">
    <div class="md:col-span-1 bg-white p-4 rounded shadow">
      <h2 class="font-semibold mb-3">Create Role</h2>
      <form method="post" class="space-y-3">
        <input type="hidden" name="action" value="create_role" />
        <input type="hidden" name="csrf_token" value="<?= admin_csrf_token(); ?>" />
        <div>
          <label class="block text-sm">Shortname</label>
          <input name="shortname" class="w-full border px-2 py-1 rounded" required />
        </div>
        <div>
          <label class="block text-sm">Name</label>
            <input name="name" class="w-full border px-2 py-1 rounded" required />
        </div>
        <div>
          <label class="block text-sm">Sort Order</label>
          <input name="sortorder" type="number" class="w-full border px-2 py-1 rounded" value="100" />
        </div>
        <div>
          <label class="block text-sm">Description</label>
          <textarea name="description" class="w-full border px-2 py-1 rounded" rows="3"></textarea>
        </div>
        <button class="bg-blue-600 text-white px-4 py-2 rounded">Create</button>
      </form>
    </div>
    <div class="md:col-span-1 bg-white p-4 rounded shadow max-h-[70vh] overflow-auto">
      <h2 class="font-semibold mb-3">Existing Roles</h2>
      <table class="w-full text-sm">
        <thead><tr class="text-left"><th class="py-1">Name</th><th>Short</th><th>Order</th><th></th></tr></thead>
        <tbody>
        <?php foreach(fetch_roles() as $r): ?>
          <tr class="border-t">
            <td class="py-1 font-medium"><?= htmlspecialchars($r->name); ?></td>
            <td><?= htmlspecialchars($r->shortname); ?></td>
            <td><?= (int)$r->sortorder; ?></td>
            <td><a class="text-blue-600 hover:underline" href="roles.php?edit=<?= $r->id; ?>">Edit</a></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php if ($editingRole): ?>
    <?php
      $caps = db()->get_records_sql("SELECT * FROM ".db()->addPrefix('capabilities')." ORDER BY component, name");
      $roleCaps = db()->get_records_sql("SELECT capability, permission FROM ".db()->addPrefix('role_capabilities')." WHERE roleid=?",[$editingRole->id]);
      $roleCapMap = []; foreach($roleCaps as $rc){ $roleCapMap[$rc->capability]=$rc->permission; }
      $templates = fetch_templates();
      $assignedTemplates = db()->get_records_sql("SELECT templateid FROM ".db()->addPrefix('role_template_assign')." WHERE roleid=?",[$editingRole->id]);
      $assignedTemplateIds = array_map(fn($o)=>$o->templateid,$assignedTemplates);
      $userAssignments = db()->get_records_sql("SELECT u.id,u.username FROM ".db()->addPrefix('users')." u JOIN ".db()->addPrefix('role_assignment')." ra ON ra.userid=u.id WHERE ra.roleid=? ORDER BY u.username",[$editingRole->id]);
    ?>
    <div class="md:col-span-1 bg-white p-4 rounded shadow overflow-auto max-h-[80vh]">
      <h2 class="font-semibold mb-3">Edit Role: <?= htmlspecialchars($editingRole->name); ?></h2>
      <form method="post" class="mb-6 space-y-3">
        <input type="hidden" name="action" value="update_role_meta" />
        <input type="hidden" name="csrf_token" value="<?= admin_csrf_token(); ?>" />
        <div>
          <label class="block text-xs font-medium">Name</label>
          <input name="name" value="<?= htmlspecialchars($editingRole->name); ?>" class="w-full border rounded px-2 py-1 text-sm" required />
        </div>
        <div>
          <label class="block text-xs font-medium">Description</label>
          <textarea name="description" rows="2" class="w-full border rounded px-2 py-1 text-sm"><?= htmlspecialchars($editingRole->description ?? ''); ?></textarea>
        </div>
        <div>
          <label class="block text-xs font-medium">Sort Order</label>
          <input type="number" name="sortorder" value="<?= (int)$editingRole->sortorder; ?>" class="w-full border rounded px-2 py-1 text-sm" />
        </div>
        <button class="bg-indigo-600 text-white px-4 py-2 rounded text-sm">Update Role Info</button>
      </form>
      <form method="post">
        <input type="hidden" name="action" value="update_role_caps" />
        <input type="hidden" name="csrf_token" value="<?= admin_csrf_token(); ?>" />
        <div class="mb-4">
          <h3 class="font-semibold mb-2">Templates</h3>
          <?php if ($templates): foreach($templates as $t): $chk = in_array($t->id,$assignedTemplateIds,true); ?>
            <label class="flex items-center space-x-2 text-sm mb-1">
              <input type="checkbox" name="templates[]" value="<?= $t->id; ?>" <?= $chk?'checked':''; ?> />
              <span><?= htmlspecialchars($t->shortname); ?></span>
            </label>
          <?php endforeach; else: ?>
            <p class="text-xs text-gray-500">No templates defined.</p>
          <?php endif; ?>
        </div>
        <div class="mb-4">
          <h3 class="font-semibold mb-2">Capabilities</h3>
          <table class="w-full text-xs">
            <thead><tr><th class="text-left">Capability</th><th>Type</th><th>Permission</th></tr></thead>
            <tbody>
            <?php foreach($caps as $c): $perm = $roleCapMap[$c->name] ?? 'notset'; ?>
              <tr class="border-t">
                <td class="py-1 pr-2"><?= htmlspecialchars($c->name); ?></td>
                <td class="py-1 pr-2 text-gray-500"><?= htmlspecialchars($c->captype); ?></td>
                <td class="py-1">
                  <select name="cap_<?= md5($c->name); ?>" class="border rounded px-1 py-0.5">
                    <?php foreach(['notset','allow','prevent','prohibit'] as $p): ?>
                      <option value="<?= $p; ?>" <?= $p===$perm?'selected':''; ?>><?= $p; ?></option>
                    <?php endforeach; ?>
                  </select>
                </td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <button class="bg-green-600 text-white px-4 py-2 rounded">Save Changes</button>
      </form>
      <div class="mt-6">
        <h3 class="font-semibold mb-2">Assigned Users</h3>
        <ul class="text-sm mb-3 space-y-1">
          <?php if ($userAssignments): foreach($userAssignments as $ua): ?>
            <li class="flex items-center justify-between bg-gray-50 px-2 py-1 rounded">
              <span><?= htmlspecialchars($ua->username); ?></span>
              <form method="post" onsubmit="return confirm('Unassign user?');">
                <input type="hidden" name="action" value="unassign_user" />
                <input type="hidden" name="userid" value="<?= $ua->id; ?>" />
                <input type="hidden" name="csrf_token" value="<?= admin_csrf_token(); ?>" />
                <button class="text-red-600 text-xs">Remove</button>
              </form>
            </li>
          <?php endforeach; else: ?>
            <li class="text-xs text-gray-500">No users assigned.</li>
          <?php endif; ?>
        </ul>
        <form method="post" class="flex space-x-2 items-end">
          <input type="hidden" name="action" value="assign_user" />
          <input type="hidden" name="csrf_token" value="<?= admin_csrf_token(); ?>" />
          <div>
            <label class="block text-xs">User ID</label>
            <input name="userid" type="number" class="border rounded px-2 py-1 w-28" />
          </div>
          <button class="bg-blue-600 text-white px-3 py-1 rounded text-sm">Assign</button>
        </form>
      </div>
    </div>
    <?php endif; ?>
  </div>
</div>
</body></html>

<?php
require_once __DIR__ . '/_bootstrap_admin.php';
use DevFramework\Core\Access\AccessManager;
$AM = AccessManager::getInstance();

if (!hasCapability('rbac:importexport', $currentUser->getId())) { http_response_code(403); echo 'Forbidden'; exit; }

$action = $_POST['action'] ?? null;
$flashes = [];

function export_roles_payload(bool $includeAdmin = true): array {
    $db = db();
    $roles = $db->get_records_sql("SELECT * FROM ".$db->addPrefix('roles')." ORDER BY sortorder ASC, id ASC");
    $result = [];
    foreach ($roles as $r) {
        if (!$includeAdmin && $r->shortname === 'admin') { continue; }
        $caps = $db->get_records_sql("SELECT capability, permission FROM ".$db->addPrefix('role_capabilities')." WHERE roleid=?", [$r->id]);
        $templates = $db->get_records_sql("SELECT t.shortname FROM ".$db->addPrefix('role_template_assign')." rta JOIN ".$db->addPrefix('role_templates')." t ON t.id=rta.templateid WHERE rta.roleid=?", [$r->id]);
        $result[] = [
            'shortname' => $r->shortname,
            'name' => $r->name,
            'description' => $r->description,
            'sortorder' => (int)$r->sortorder,
            'capabilities' => array_map(fn($c)=>['name'=>$c->capability,'permission'=>$c->permission], $caps),
            'templates' => array_map(fn($t)=>$t->shortname, $templates)
        ];
    }
    return [
        'exported_at' => date('c'),
        'include_admin' => $includeAdmin,
        'roles' => $result
    ];
}

function find_template_id_by_shortname(string $shortname): ?int {
    $rec = db()->get_record('role_templates', ['shortname'=>$shortname]);
    return $rec? (int)$rec->id : null;
}

if ($action === 'export') {
    if (!admin_csrf_validate()) { admin_flash('error','CSRF failed'); header('Location: import_export.php'); exit; }
    $includeAdmin = isset($_POST['include_admin']);
    $payload = export_roles_payload($includeAdmin);
    $json = json_encode($payload, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
    $AM->logAction($currentUser->getId(), 'role_export', ['count'=>count($payload['roles'])]);
    header('Content-Type: application/json');
    header('Content-Disposition: attachment; filename="role-export-'.date('Ymd-His').'.json"');
    echo $json; exit;
}

if ($action === 'import') {
    if (!admin_csrf_validate()) { admin_flash('error','CSRF failed'); header('Location: import_export.php'); exit; }
    $mode = $_POST['mode'] ?? 'merge'; // merge|replace
    $jsonInput = '';
    if (!empty($_FILES['import_file']['tmp_name'])) {
        $jsonInput = file_get_contents($_FILES['import_file']['tmp_name']);
    } else {
        $jsonInput = $_POST['import_json'] ?? '';
    }
    if (!$jsonInput) { admin_flash('error','No import data provided'); header('Location: import_export.php'); exit; }
    $data = json_decode($jsonInput, true);
    if (!is_array($data) || !isset($data['roles'])) { admin_flash('error','Invalid JSON structure'); header('Location: import_export.php'); exit; }

    $db = db();
    $rolesTable = $db->addPrefix('roles');
    $capsTable = $db->addPrefix('role_capabilities');
    $templateAssign = $db->addPrefix('role_template_assign');
    $templatesTable = $db->addPrefix('role_templates');
    $now = time();

    foreach ($data['roles'] as $r) {
        if (!isset($r['shortname'], $r['name'])) { continue; }
        $short = $r['shortname'];
        // Never remove or downgrade the admin role assignment; skip modifications to admin capabilities if present? We'll still merge but keep ensure afterwards.
        $existing = $db->get_record('roles', ['shortname'=>$short]);
        if (!$existing) {
            $roleId = $db->insert_record('roles', [
                'name'=>$r['name'],
                'shortname'=>$short,
                'description'=>$r['description'] ?? '',
                'sortorder'=>$r['sortorder'] ?? 100,
                'timecreated'=>$now,'timemodified'=>$now
            ]);
            $AM->logAction($currentUser->getId(), 'role_import_create', ['shortname'=>$short]);
        } else {
            $roleId = (int)$existing->id;
            // Update base fields
            $db->update_record('roles', [ 'id'=>$roleId, 'name'=>$r['name'], 'description'=>$r['description'] ?? $existing->description, 'sortorder'=>$r['sortorder'] ?? $existing->sortorder, 'timemodified'=>$now ]);
            $AM->logAction($currentUser->getId(), 'role_import_update', ['shortname'=>$short]);
            if ($mode === 'replace') {
                $db->execute("DELETE FROM {$capsTable} WHERE roleid=?", [$roleId]);
                $db->execute("DELETE FROM {$templateAssign} WHERE roleid=?", [$roleId]);
            }
        }
        // Capabilities
        if (!empty($r['capabilities']) && is_array($r['capabilities'])) {
            foreach ($r['capabilities'] as $cap) {
                if (!isset($cap['name'],$cap['permission'])) { continue; }
                $perm = $cap['permission'];
                if (!in_array($perm,['allow','prevent','prohibit','notset'],true)) { continue; }
                if ($perm==='notset') { continue; }
                $db->execute("INSERT INTO {$capsTable} (roleid,capability,permission,timecreated,timemodified) VALUES (?,?,?,?,?) ON DUPLICATE KEY UPDATE permission=VALUES(permission), timemodified=VALUES(timemodified)", [$roleId,$cap['name'],$perm,$now,$now]);
            }
        }
        // Templates
        if (!empty($r['templates']) && is_array($r['templates'])) {
            foreach ($r['templates'] as $ts) {
                $tid = find_template_id_by_shortname($ts);
                if ($tid) {
                    $db->execute("INSERT INTO {$templateAssign} (roleid, templateid, timecreated, timemodified) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE timemodified=VALUES(timemodified)", [$roleId,$tid,$now,$now]);
                }
            }
        }
    }
    $AM->resetCache();
    $AM->logAction($currentUser->getId(),'role_import',['mode'=>$mode,'role_count'=>count($data['roles'])]);
    // Re-ensure admin role assignment
    $AM->syncAllCapabilities();
    admin_flash('success','Import processed');
    header('Location: import_export.php'); exit;
}

$flashes = admin_get_flashes();
?>
<!DOCTYPE html><html><head><meta charset="utf-8"><title>Import / Export Roles</title><?= tailwind_cdn(); ?></head>
<body class="bg-gray-100">
<div class="max-w-6xl mx-auto p-6 space-y-8">
  <div class="flex items-center justify-between">
    <h1 class="text-2xl font-bold">Import / Export Role Profiles</h1>
    <a href="index.php" class="text-blue-600 hover:underline">← Admin Home</a>
  </div>
  <?php if ($flashes): foreach($flashes as $f): ?>
    <div class="p-3 rounded <?= $f['type']==='error'?'bg-red-200 text-red-900':'bg-green-200 text-green-900' ?>"><?= htmlspecialchars($f['msg']); ?></div>
  <?php endforeach; endif; ?>
  <div class="grid md:grid-cols-2 gap-8">
    <div class="bg-white p-6 rounded shadow">
      <h2 class="font-semibold mb-4">Export Roles</h2>
      <form method="post">
        <input type="hidden" name="action" value="export" />
        <input type="hidden" name="csrf_token" value="<?= admin_csrf_token(); ?>" />
        <label class="flex items-center space-x-2 mb-4 text-sm">
          <input type="checkbox" name="include_admin" checked />
          <span>Include Administrator role</span>
        </label>
        <button class="bg-blue-600 text-white px-4 py-2 rounded">Download Export JSON</button>
      </form>
      <p class="text-xs text-gray-500 mt-4">Exports role definitions with capabilities and template inheritance references.</p>
    </div>
    <div class="bg-white p-6 rounded shadow">
      <h2 class="font-semibold mb-4">Import Roles</h2>
      <form method="post" enctype="multipart/form-data" class="space-y-4">
        <input type="hidden" name="action" value="import" />
        <input type="hidden" name="csrf_token" value="<?= admin_csrf_token(); ?>" />
        <div>
          <label class="block text-sm font-medium mb-1">JSON File</label>
          <input type="file" name="import_file" accept="application/json" class="text-sm" />
          <p class="text-xs text-gray-400 mt-1">Or paste JSON below.</p>
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">JSON Content</label>
          <textarea name="import_json" rows="8" class="w-full border rounded px-2 py-1 text-xs font-mono" placeholder="{ ... }"></textarea>
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">Mode</label>
          <select name="mode" class="border rounded px-2 py-1 text-sm">
            <option value="merge">Merge (update / add only)</option>
            <option value="replace">Replace (overwrite caps & templates per role)</option>
          </select>
        </div>
        <button class="bg-green-600 text-white px-4 py-2 rounded">Import</button>
      </form>
      <p class="text-xs text-gray-500 mt-4">Import respects existing roles by shortname. In replace mode, previous capabilities and template assignments for listed roles are cleared first (except admin role always remains assigned to admin user).</p>
    </div>
  </div>
</div>
</body></html>

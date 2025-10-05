<?php
require_once __DIR__ . '/_bootstrap_admin.php';
use DevFramework\Core\Access\AccessManager;
$AM = AccessManager::getInstance();

if (!hasCapability('rbac:manage', $currentUser->getId())) { http_response_code(403); echo 'Forbidden'; exit; }

$action = $_POST['action'] ?? null;
$templateId = isset($_GET['edit']) ? (int)$_GET['edit'] : null;

function fetch_templates() {
    return db()->get_records_sql("SELECT * FROM ".db()->addPrefix('role_templates')." ORDER BY shortname ASC");
}

if ($action==='create_template') {
    if (!admin_csrf_validate()) { admin_flash('error','CSRF failed'); header('Location: templates.php'); exit; }
    $shortname = trim($_POST['shortname']??'');
    $name = trim($_POST['name']??'');
    $description = trim($_POST['description']??'');
    if ($shortname && $name) {
        if (db()->record_exists('role_templates',['shortname'=>$shortname])) {
            admin_flash('error','Template shortname exists');
        } else {
            $now=time();
            $id = db()->insert_record('role_templates',[ 'name'=>$name,'shortname'=>$shortname,'description'=>$description,'timecreated'=>$now,'timemodified'=>$now ]);
            $AM->logAction($currentUser->getId(),'create_template',['templateid'=>$id,'shortname'=>$shortname]);
            admin_flash('success','Template created');
        }
    } else {
        admin_flash('error','Missing fields');
    }
    header('Location: templates.php'); exit;
}

if ($action==='update_template_caps' && $templateId) {
    if (!admin_csrf_validate()) { admin_flash('error','CSRF failed'); header('Location: templates.php?edit='.$templateId); exit; }
    $template = db()->get_record('role_templates',['id'=>$templateId]);
    if (!$template) { admin_flash('error','Template not found'); header('Location: templates.php'); exit; }
    $caps = db()->get_records_sql("SELECT name FROM ".db()->addPrefix('capabilities')." ORDER BY name");
    $tcTable = db()->addPrefix('role_template_capabilities');
    $now=time();
    foreach ($caps as $c) {
        $field = 'cap_'.md5($c->name);
        $perm = $_POST[$field] ?? '';
        if (!in_array($perm,['','allow','prevent','prohibit'],true)) { $perm=''; }
        $exists = db()->record_exists_sql("SELECT 1 FROM {$tcTable} WHERE templateid=? AND capability=?",[$templateId,$c->name]);
        if ($perm==='' && $exists) {
            db()->execute("DELETE FROM {$tcTable} WHERE templateid=? AND capability=?",[$templateId,$c->name]);
            $AM->logAction($currentUser->getId(),'template_capability_remove',['cap'=>$c->name],null,null,$c->name);
        } elseif ($perm!=='') {
            db()->execute("INSERT INTO {$tcTable} (templateid,capability,permission,timecreated,timemodified) VALUES (?,?,?,?,?) ON DUPLICATE KEY UPDATE permission=VALUES(permission),timemodified=VALUES(timemodified)",[$templateId,$c->name,$perm,$now,$now]);
            $AM->logAction($currentUser->getId(),'template_capability_set',['cap'=>$c->name,'perm'=>$perm],null,null,$c->name);
        }
    }
    $AM->resetCache();
    admin_flash('success','Template updated');
    header('Location: templates.php?edit='.$templateId); exit;
}

if ($action==='delete_template' && $templateId) {
    if (!admin_csrf_validate()) { admin_flash('error','CSRF failed'); header('Location: templates.php'); exit; }
    $template = db()->get_record('role_templates',['id'=>$templateId]);
    if ($template) {
        db()->execute("DELETE FROM ".db()->addPrefix('role_templates')." WHERE id=?",[$templateId]);
        $AM->logAction($currentUser->getId(),'delete_template',['templateid'=>$templateId]);
        admin_flash('success','Template deleted');
    }
    header('Location: templates.php'); exit;
}

$flashes = admin_get_flashes();
$editingTemplate = $templateId ? db()->get_record('role_templates',['id'=>$templateId]) : null;
?>
<!DOCTYPE html><html><head><meta charset="utf-8"><title>Templates</title><?= tailwind_cdn(); ?></head>
<body class="bg-gray-100">
<div class="max-w-7xl mx-auto p-6">
  <div class="flex items-center justify-between mb-6">
    <h1 class="text-2xl font-bold">Role Templates</h1>
    <a href="index.php" class="text-blue-600 hover:underline">← Admin Home</a>
  </div>
  <?php if ($flashes): foreach($flashes as $f): ?>
    <div class="mb-4 p-3 rounded <?= $f['type']==='error'?'bg-red-200 text-red-900':'bg-green-200 text-green-900' ?>"><?= htmlspecialchars($f['msg']); ?></div>
  <?php endforeach; endif; ?>
  <div class="grid md:grid-cols-<?= $editingTemplate? '3':'2' ?> gap-6">
    <div class="bg-white p-4 rounded shadow">
      <h2 class="font-semibold mb-3">Create Template</h2>
      <form method="post" class="space-y-3">
        <input type="hidden" name="action" value="create_template" />
        <input type="hidden" name="csrf_token" value="<?= admin_csrf_token(); ?>" />
        <div><label class="block text-sm">Shortname</label><input name="shortname" class="w-full border px-2 py-1 rounded" required /></div>
        <div><label class="block text-sm">Name</label><input name="name" class="w-full border px-2 py-1 rounded" required /></div>
        <div><label class="block text-sm">Description</label><textarea name="description" class="w-full border px-2 py-1 rounded" rows="3"></textarea></div>
        <button class="bg-blue-600 text-white px-4 py-2 rounded">Create</button>
      </form>
    </div>
    <div class="bg-white p-4 rounded shadow max-h-[70vh] overflow-auto">
      <h2 class="font-semibold mb-3">Templates</h2>
      <table class="w-full text-sm">
        <thead><tr class="text-left"><th>Name</th><th>Short</th><th></th></tr></thead>
        <tbody>
        <?php foreach(fetch_templates() as $t): ?>
          <tr class="border-t"><td class="py-1 font-medium"><?= htmlspecialchars($t->name); ?></td><td><?= htmlspecialchars($t->shortname); ?></td><td class="text-right space-x-2">
            <a class="text-blue-600 hover:underline" href="templates.php?edit=<?= $t->id; ?>">Edit</a>
            <form method="post" style="display:inline" onsubmit="return confirm('Delete template?');">
              <input type="hidden" name="action" value="delete_template" />
              <input type="hidden" name="csrf_token" value="<?= admin_csrf_token(); ?>" />
              <input type="hidden" name="id" value="<?= $t->id; ?>" />
              <button name="template" value="<?= $t->id; ?>" class="text-red-600 text-xs">Delete</button>
            </form>
          </td></tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php if ($editingTemplate): ?>
    <?php
      $caps = db()->get_records_sql("SELECT * FROM ".db()->addPrefix('capabilities')." ORDER BY component,name");
      $templateCaps = db()->get_records_sql("SELECT capability,permission FROM ".db()->addPrefix('role_template_capabilities')." WHERE templateid=?",[$editingTemplate->id]);
      $tMap=[]; foreach($templateCaps as $tc){ $tMap[$tc->capability]=$tc->permission; }
    ?>
    <div class="bg-white p-4 rounded shadow max-h-[80vh] overflow-auto">
      <h2 class="font-semibold mb-3">Edit Template: <?= htmlspecialchars($editingTemplate->name); ?></h2>
      <form method="post">
        <input type="hidden" name="action" value="update_template_caps" />
        <input type="hidden" name="csrf_token" value="<?= admin_csrf_token(); ?>" />
        <table class="w-full text-xs">
          <thead><tr><th class="text-left">Capability</th><th>Type</th><th>Perm</th></tr></thead>
          <tbody>
          <?php foreach($caps as $c): $perm=$tMap[$c->name]??''; ?>
            <tr class="border-t">
              <td class="py-1 pr-2"><?= htmlspecialchars($c->name); ?></td>
              <td class="py-1 pr-2 text-gray-500"><?= htmlspecialchars($c->captype); ?></td>
              <td class="py-1">
                <select name="cap_<?= md5($c->name); ?>" class="border rounded px-1 py-0.5">
                  <option value="" <?= $perm===''?'selected':''; ?>>inherit-none</option>
                  <?php foreach(['allow','prevent','prohibit'] as $p): ?>
                    <option value="<?= $p; ?>" <?= $p===$perm?'selected':''; ?>><?= $p; ?></option>
                  <?php endforeach; ?>
                </select>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
        <button class="mt-3 bg-green-600 text-white px-4 py-2 rounded">Save Template</button>
      </form>
    </div>
    <?php endif; ?>
  </div>
</div>
</body></html>

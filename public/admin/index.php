<?php
require_once __DIR__ . '/_bootstrap_admin.php';
$capsTable = $db->addPrefix('capabilities');
$rolesTable = $db->addPrefix('roles');
$usersTable = $db->addPrefix('users');
$templatesTable = $db->addPrefix('role_templates');
$counts = [
    'roles' => (int)$db->execute("SELECT COUNT(*) FROM {$rolesTable}")->fetchColumn(),
    'caps' => (int)$db->execute("SELECT COUNT(*) FROM {$capsTable}")->fetchColumn(),
    'users' => (int)$db->execute("SELECT COUNT(*) FROM {$usersTable}")->fetchColumn(),
    'templates' => (int)$db->execute("SELECT COUNT(*) FROM {$templatesTable}")->fetchColumn(),
];
$flashes = admin_get_flashes();
?>
<!DOCTYPE html><html><head><meta charset="utf-8"><title>Admin - RBAC</title><?= tailwind_cdn(); ?></head><body class="bg-gray-100 text-gray-900">
<div class="max-w-6xl mx-auto p-6">
  <h1 class="text-3xl font-bold mb-6">Administration</h1>
  <?php if ($flashes): foreach ($flashes as $f): ?>
    <div class="mb-4 p-4 rounded <?= $f['type']==='error'?'bg-red-100 text-red-700':'bg-green-100 text-green-700' ?>"><?= htmlspecialchars($f['msg']); ?></div>
  <?php endforeach; endif; ?>
  <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-8">
    <div class="bg-white shadow p-4 rounded"><div class="text-sm text-gray-500">Roles</div><div class="text-2xl font-semibold"><?= $counts['roles'] ?></div></div>
    <div class="bg-white shadow p-4 rounded"><div class="text-sm text-gray-500">Capabilities</div><div class="text-2xl font-semibold"><?= $counts['caps'] ?></div></div>
    <div class="bg-white shadow p-4 rounded"><div class="text-sm text-gray-500">Users</div><div class="text-2xl font-semibold"><?= $counts['users'] ?></div></div>
    <div class="bg-white shadow p-4 rounded"><div class="text-sm text-gray-500">Templates</div><div class="text-2xl font-semibold"><?= $counts['templates'] ?></div></div>
  </div>
  <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
    <div class="bg-white p-6 rounded shadow">
      <h2 class="font-semibold text-lg mb-4">Role & Capability Management</h2>
      <ul class="space-y-2 list-disc ml-5 text-blue-700">
        <li><a href="roles.php" class="hover:underline">Manage Roles</a></li>
        <li><a href="templates.php" class="hover:underline">Manage Templates (Inheritance)</a></li>
        <li><a href="import_export.php" class="hover:underline">Import / Export Role Profiles</a></li>
        <li><a href="audit.php" class="hover:underline">Audit Log</a></li>
      </ul>
    </div>
    <div class="bg-white p-6 rounded shadow">
      <h2 class="font-semibold text-lg mb-4">Quick Info</h2>
      <p class="text-sm leading-relaxed">This interface lets you create roles, assign capabilities or inherited templates, batch import/export role definitions, and review a complete audit trail of permission changes.</p>
      <p class="text-sm mt-2">Administrator account is auto-assigned the top-level admin role.</p>
    </div>
  </div>
</div>
</body></html>


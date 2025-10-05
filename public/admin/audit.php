<?php
require_once __DIR__ . '/_bootstrap_admin.php';
use DevFramework\Core\Access\AccessManager;

if (!hasCapability('rbac:viewaudit', $currentUser->getId())) { http_response_code(403); echo 'Forbidden'; exit; }

$db = db();
$logTable = $db->addPrefix('role_audit_log');
$rolesTable = $db->addPrefix('roles');
$usersTable = $db->addPrefix('users');

// Filters
$fAction = trim($_GET['action'] ?? '');
$fActor  = trim($_GET['actor'] ?? ''); // id or username
$fRole   = trim($_GET['role'] ?? '');  // role id or shortname
$fCap    = trim($_GET['cap'] ?? '');
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 50;
$offset  = ($page - 1) * $perPage;

$params = [];
$where  = [];

if ($fAction !== '') { $where[] = 'l.action = ?'; $params[] = $fAction; }
if ($fCap !== '') { $where[] = 'l.capability = ?'; $params[] = $fCap; }
if ($fActor !== '') {
    if (ctype_digit($fActor)) { $where[] = 'l.actorid = ?'; $params[] = (int)$fActor; }
    else { $where[] = 'ua.username = ?'; $params[] = $fActor; }
}
if ($fRole !== '') {
    if (ctype_digit($fRole)) { $where[] = 'l.targetroleid = ?'; $params[] = (int)$fRole; }
    else { $where[] = 'r.shortname = ?'; $params[] = $fRole; }
}

$whereSQL = $where ? ('WHERE ' . implode(' AND ', $where)) : '';
$countSQL = "SELECT COUNT(*) FROM {$logTable} l LEFT JOIN {$rolesTable} r ON r.id=l.targetroleid LEFT JOIN {$usersTable} ua ON ua.id=l.actorid {$whereSQL}";
$total = (int)$db->execute($countSQL, $params)->fetchColumn();

$sql = "SELECT l.*, r.shortname as roleshort, ua.username as actorusername, tu.username as targetusername
        FROM {$logTable} l
        LEFT JOIN {$rolesTable} r ON r.id = l.targetroleid
        LEFT JOIN {$usersTable} ua ON ua.id = l.actorid
        LEFT JOIN {$usersTable} tu ON tu.id = l.userid
        {$whereSQL}
        ORDER BY l.id DESC
        LIMIT {$perPage} OFFSET {$offset}";
$rows = $db->get_records_sql($sql, $params);

$pages = (int)ceil($total / $perPage);
?>
<!DOCTYPE html><html><head><meta charset="utf-8"><title>Audit Log</title><?= tailwind_cdn(); ?></head>
<body class="bg-gray-100">
<div class="max-w-7xl mx-auto p-6 space-y-6">
  <div class="flex items-center justify-between">
    <h1 class="text-2xl font-bold">RBAC Audit Log</h1>
    <a href="index.php" class="text-blue-600 hover:underline">← Admin Home</a>
  </div>
  <form method="get" class="bg-white p-4 rounded shadow grid md:grid-cols-6 gap-4 text-sm">
    <div>
      <label class="block mb-1 text-xs font-medium">Action</label>
      <input name="action" value="<?= htmlspecialchars($fAction); ?>" class="w-full border rounded px-2 py-1" placeholder="create_role" />
    </div>
    <div>
      <label class="block mb-1 text-xs font-medium">Actor (id/username)</label>
      <input name="actor" value="<?= htmlspecialchars($fActor); ?>" class="w-full border rounded px-2 py-1" placeholder="admin" />
    </div>
    <div>
      <label class="block mb-1 text-xs font-medium">Role (id/shortname)</label>
      <input name="role" value="<?= htmlspecialchars($fRole); ?>" class="w-full border rounded px-2 py-1" placeholder="editor" />
    </div>
    <div>
      <label class="block mb-1 text-xs font-medium">Capability</label>
      <input name="cap" value="<?= htmlspecialchars($fCap); ?>" class="w-full border rounded px-2 py-1" placeholder="auth:add" />
    </div>
    <div>
      <label class="block mb-1 text-xs font-medium">Page</label>
      <input name="page" type="number" value="<?= $page; ?>" class="w-full border rounded px-2 py-1" />
    </div>
    <div class="flex items-end">
      <button class="bg-blue-600 text-white px-4 py-2 rounded w-full">Filter</button>
    </div>
  </form>

  <div class="bg-white rounded shadow overflow-auto">
    <table class="min-w-full text-xs">
      <thead class="bg-gray-50">
        <tr>
          <th class="px-3 py-2 text-left">ID</th>
          <th class="px-3 py-2 text-left">Action</th>
          <th class="px-3 py-2 text-left">Actor</th>
          <th class="px-3 py-2 text-left">Target User</th>
          <th class="px-3 py-2 text-left">Role</th>
          <th class="px-3 py-2 text-left">Capability</th>
          <th class="px-3 py-2 text-left">Details</th>
          <th class="px-3 py-2 text-left">IP</th>
          <th class="px-3 py-2 text-left">Time</th>
        </tr>
      </thead>
      <tbody>
      <?php if ($rows): foreach ($rows as $r): ?>
        <tr class="border-t hover:bg-gray-50">
          <td class="px-3 py-1"><?= (int)$r->id; ?></td>
          <td class="px-3 py-1 font-medium"><?= htmlspecialchars($r->action); ?></td>
          <td class="px-3 py-1"><?= htmlspecialchars($r->actorusername ?? ($r->actorid??'')); ?></td>
          <td class="px-3 py-1"><?= htmlspecialchars($r->targetusername ?? ($r->userid??'')); ?></td>
          <td class="px-3 py-1"><?= htmlspecialchars($r->roleshort ?? ($r->targetroleid??'')); ?></td>
          <td class="px-3 py-1"><?= htmlspecialchars($r->capability ?? ''); ?></td>
          <td class="px-3 py-1 max-w-xs truncate" title="<?= htmlspecialchars($r->details ?? ''); ?>"><?php if ($r->details) { echo htmlspecialchars(substr($r->details,0,60)); if (strlen($r->details)>60) echo '…'; } ?></td>
          <td class="px-3 py-1"><?= htmlspecialchars($r->ip ?? ''); ?></td>
          <td class="px-3 py-1" title="<?= date('c',(int)$r->timecreated); ?>"><?= date('Y-m-d H:i:s',(int)$r->timecreated); ?></td>
        </tr>
      <?php endforeach; else: ?>
        <tr><td colspan="9" class="px-3 py-4 text-center text-gray-500">No audit entries found.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>

  <div class="flex justify-between items-center text-xs">
    <div>Total: <?= $total; ?> entries</div>
    <div class="space-x-1">
      <?php for($p=1;$p<=$pages;$p++): $is=$p===$page; ?>
        <a class="px-2 py-1 rounded <?= $is?'bg-blue-600 text-white':'bg-white border text-blue-700' ?>" href="?<?= http_build_query(array_merge($_GET,['page'=>$p])); ?>"><?= $p; ?></a>
      <?php endfor; ?>
    </div>
  </div>
</div>
</body></html>

<?php
// Users listing page (Admin)
require_once dirname(__DIR__) . '/_bootstrap_admin.php';

global $OUTPUT; // ensure output renderer available (set in helpers)

use DevFramework\Core\Database\Database;

global $DB; // ensure global DB from bootstrap helpers (if set) else fallback
if (!isset($DB)) { $DB = Database::getInstance(); }

$action = $_POST['action'] ?? null;

// Handle delete action
if ($action === 'delete_user') {
    if (!admin_csrf_validate()) { admin_flash('error','CSRF failed'); header('Location: index.php'); exit; }
    $userid = (int)($_POST['userid'] ?? 0);
    if ($userid > 0) {
        $user = $DB->get_record('users', ['id' => $userid]);
        if ($user) {
            if ($user->username === 'admin') {
                admin_flash('error', 'Cannot delete primary admin user');
            } elseif ($user->status === 'deleted') {
                admin_flash('error', 'User already deleted');
            } else {
                $DB->update_record('users', [ 'id' => $userid, 'status' => 'deleted', 'timemodified' => time() ]);
                admin_flash('success', 'User deleted');
            }
        } else { admin_flash('error','User not found'); }
    }
    header('Location: index.php'); exit;
}

// Filtering & pagination
$filter = trim($_GET['q'] ?? '');
$showDeleted = isset($_GET['show_deleted']) && (int)$_GET['show_deleted'] === 1;
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20; // default page size
$offset = ($page - 1) * $perPage;

// Build SQL dynamically for filtering
$prefixUsers = $DB->addPrefix('users');
$where = '';
$params = [];
if ($filter !== '') {
    $where = "WHERE (username LIKE :q1 OR email LIKE :q2 OR firstname LIKE :q3 OR lastname LIKE :q4)";
    if (!$showDeleted) { $where .= " AND status <> 'deleted'"; }
    $pattern = '%' . $filter . '%';
    $params = [ ':q1' => $pattern, ':q2' => $pattern, ':q3' => $pattern, ':q4' => $pattern ];
} else {
    if (!$showDeleted) { $where = "WHERE status <> 'deleted'"; }
}

// Count total
$countSql = "SELECT COUNT(*) AS cnt FROM {$prefixUsers} {$where}";
$totalObj = $DB->get_record_sql($countSql, $params);
$total = $totalObj ? (int)$totalObj->cnt : 0;
$totalPages = $total > 0 ? (int)ceil($total / $perPage) : 1;
if ($page > $totalPages) { $page = $totalPages; $offset = ($page - 1) * $perPage; }

// Fetch users (avoid binding limit/offset due to MySQL prepared stmt restrictions)
$userSql = "SELECT id, username, email, auth, firstname, lastname, status, emailverified, lastlogin, timecreated FROM {$prefixUsers} {$where} ORDER BY id ASC LIMIT {$perPage} OFFSET {$offset}";
$rows = $DB->get_records_sql($userSql, $params);

// Build return base (preserve filters & page) for edit links
$returnParams = [];
if ($filter !== '') { $returnParams['q'] = $filter; }
if ($page > 1) { $returnParams['page'] = $page; }
if ($showDeleted) { $returnParams['show_deleted'] = 1; }
$returnUrl = 'index.php' . (!empty($returnParams) ? '?' . http_build_query($returnParams) : '');

// Transform for view
$usersView = array_map(function($u) use ($returnUrl){
    $fmt = function($ts){ return $ts ? date('Y-m-d H:i', (int)$ts) : '-'; };
    return [
        'id' => $u->id,
        'username' => htmlspecialchars($u->username, ENT_QUOTES, 'UTF-8'),
        'email' => htmlspecialchars($u->email, ENT_QUOTES, 'UTF-8'),
        'name' => trim(($u->firstname ?? '') . ' ' . ($u->lastname ?? '')) ?: '-',
        'auth' => $u->auth,
        'status' => $u->status,
        'lastlogin' => $fmt($u->lastlogin),
        'created' => $fmt($u->timecreated),
        'is_admin' => $u->username === 'admin',
        'is_deleted' => $u->status === 'deleted',
        'edit_url' => 'edit.php?id=' . (int)$u->id . '&return=' . urlencode($returnUrl)
    ];
}, $rows);

$flashes = admin_get_flashes();
$flashView = array_map(function($f){ $f['css_class'] = $f['type']==='error' ? 'bg-red-200 text-red-900' : 'bg-green-200 text-green-900'; return $f; }, $flashes);

// Pagination URLs
$queryBase = function($p) use ($filter, $showDeleted){ $params = ['page'=>$p]; if ($filter !== '') { $params['q']=$filter; } if ($showDeleted) { $params['show_deleted']=1; } return 'index.php?' . http_build_query($params); };

// Build toggle deleted URL
$toggleParams = [];
if ($filter !== '') { $toggleParams['q'] = $filter; }
if ($page > 1) { $toggleParams['page'] = $page; }
if (!$showDeleted) { $toggleParams['show_deleted'] = 1; }
$toggleUrl = 'index.php' . (!empty($toggleParams) ? '?' . http_build_query($toggleParams) : '');
// Clear filters URL (preserve show_deleted if active)
$clearParams = [];
if ($showDeleted) { $clearParams['show_deleted'] = 1; }
$clearFiltersUrl = 'index.php' . (!empty($clearParams) ? '?' . http_build_query($clearParams) : '');

// Context for rendering
$context = [
    'flashes' => $flashView,
    'users' => $usersView,
    'has_users' => count($usersView) > 0,
    'csrf_token' => admin_csrf_token(),
    'filter_value' => $filter,
    'has_filter' => $filter !== '',
    'show_pagination' => $totalPages > 1,
    'current_page' => $page,
    'total_pages' => $totalPages,
    'home_link' => '../index.php',
    'has_prev' => $page > 1,
    'has_next' => $page < $totalPages,
    'prev_url' => $page > 1 ? $queryBase($page - 1) : null,
    'next_url' => $page < $totalPages ? $queryBase($page + 1) : null,
    'show_deleted' => $showDeleted,
    'toggle_deleted_url' => $toggleUrl,
    'clear_filters_url' => $clearFiltersUrl,
];

echo $OUTPUT->header([
    'page_title' => 'Users',
    'site_name' => 'Admin Console',
    'user' => ['username' => $currentUser->getUsername()],
]);
echo $OUTPUT->renderFromTemplate('admin_users', $context);
echo $OUTPUT->footer();

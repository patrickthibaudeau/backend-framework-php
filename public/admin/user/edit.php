<?php
require_once dirname(__DIR__) . '/_bootstrap_admin.php';

global $OUTPUT, $DB, $currentUser;

use DevFramework\Core\Database\Database;
if (!isset($DB)) { $DB = Database::getInstance(); }

// Validate and normalise return URL (only allow index.php with optional query string)
$rawReturn = $_GET['return'] ?? ($_POST['return'] ?? 'index.php');
if (!preg_match('/^index\.php(\?.*)?$/', $rawReturn)) { $rawReturn = 'index.php'; }
$returnUrl = $rawReturn;

$userid = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$creating = ($userid === 0);
if (!$creating) {
    if ($userid <= 0) { admin_flash('error','Missing user id'); header('Location: ' . $returnUrl); exit; }
    $user = $DB->get_record('users',['id'=>$userid]);
    if (!$user) { admin_flash('error','User not found'); header('Location: ' . $returnUrl); exit; }
} else {
    // stub user object for template compatibility
    $user = (object) [
        'id' => 0,
        'username' => '',
        'firstname' => '',
        'lastname' => '',
        'email' => '',
        'status' => 'active',
        'auth' => 'manual'
    ];
}

$action = $_POST['action'] ?? null;

if ($action === 'save_user') {
    if (!admin_csrf_validate()) { admin_flash('error','CSRF failed'); header('Location: edit.php?id='.$userid.'&return='.urlencode($returnUrl)); exit; }

    $firstname = trim($_POST['firstname'] ?? '');
    $lastname = trim($_POST['lastname'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $status = trim($_POST['status'] ?? ($user->status ?? 'active'));
    $auth = trim($_POST['auth'] ?? ($user->auth ?? 'manual'));
    $username = trim($_POST['username'] ?? ($user->username ?? ''));
    $passwordRaw = $_POST['password'] ?? '';

    $errors = [];

    if ($creating) {
        if ($username === '') { $errors[] = 'Username required'; }
        elseif (!preg_match('/^[a-zA-Z0-9._-]{3,}$/', $username)) { $errors[] = 'Invalid username format'; }
        else {
            $existsU = $DB->get_record_sql('SELECT id FROM '.$DB->addPrefix('users').' WHERE username = :u LIMIT 1', [':u'=>$username]);
            if ($existsU) { $errors[] = 'Username already in use'; }
        }
    }

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) { $errors[] = 'Invalid email address'; }
    else {
        $paramsEmail = [':e'=>$email];
        $emailSql = 'SELECT id FROM '.$DB->addPrefix('users').' WHERE email = :e';
        if (!$creating) { $emailSql .= ' AND id <> :id'; $paramsEmail[':id'] = $user->id; }
        $emailSql .= ' LIMIT 1';
        $existing = $DB->get_record_sql($emailSql, $paramsEmail);
        if ($existing) { $errors[] = 'Email already in use'; }
    }

    $allowedStatuses = ['active','inactive','suspended','deleted'];
    $allowedAuth = ['manual','ldap','oauth','saml2'];
    if (!in_array($status,$allowedStatuses,true)) { $status = $creating ? 'active' : $user->status; }
    if ($auth === '' || !in_array($auth,$allowedAuth,true)) { $auth = $creating ? 'manual' : ($user->auth ?? 'manual'); }

    if ($creating) {
        if ($auth === 'manual') {
            if (strlen($passwordRaw) < 6) { $errors[] = 'Password must be at least 6 characters'; }
        }
    }

    if (!$creating && $user->username === 'admin') {
        if ($status === 'deleted') { $status = $user->status; }
    }

    if (empty($errors)) {
        if ($creating) {
            $now = time();
            $record = [
                'auth' => $auth,
                'username' => $username,
                'email' => $email,
                'password' => $auth === 'manual' ? password_hash($passwordRaw, PASSWORD_DEFAULT) : '',
                'firstname' => $firstname ?: null,
                'lastname' => $lastname ?: null,
                'status' => $status ?: 'active',
                'emailverified' => 0,
                'lastlogin' => null,
                'timecreated' => $now,
                'timemodified' => $now
            ];
            $newId = $DB->insert_record('users', $record, true);
            admin_flash('success','User created');
            header('Location: ' . $returnUrl); exit;
        } else {
            $update = [ 'id'=>$user->id, 'firstname'=>$firstname ?: null, 'lastname'=>$lastname ?: null, 'email'=>$email, 'status'=>$status, 'auth'=>$auth, 'timemodified'=>time() ];
            $DB->update_record('users',$update);
            admin_flash('success','User updated');
            header('Location: ' . $returnUrl); exit;
        }
    } else {
        foreach ($errors as $e) { admin_flash('error',$e); }
        $redirId = $creating ? 0 : $user->id;
        header('Location: edit.php?id='.$redirId.'&return='.urlencode($returnUrl)); exit;
    }
}

$flashes = admin_get_flashes();
$flashView = array_map(function($f){ $f['css_class'] = $f['type']==='error' ? 'bg-red-200 text-red-900' : 'bg-green-200 text-green-900'; return $f; }, $flashes);

$isAdminPrimary = !$creating && $user->username === 'admin';
$isDeleted = !$creating && $user->status === 'deleted';

$statuses = [
    ['value'=>'active','label_key'=>'user_status_option_active'],
    ['value'=>'inactive','label_key'=>'user_status_option_inactive'],
    ['value'=>'suspended','label_key'=>'user_status_option_suspended'],
    ['value'=>'deleted','label_key'=>'user_status_option_deleted'],
];
// Build auth provider options
$authProviders = [
    ['value'=>'manual','label_key'=>'user_auth_option_manual'],
    ['value'=>'ldap','label_key'=>'user_auth_option_ldap'],
    ['value'=>'oauth','label_key'=>'user_auth_option_oauth'],
    ['value'=>'saml2','label_key'=>'user_auth_option_saml2'],
];
$authOptions = [];
$currentAuth = $user->auth ?? 'manual';
foreach ($authProviders as $p) {
    $authOptions[] = [ 'value'=>$p['value'], 'label_key'=>$p['label_key'], 'selected'=>$currentAuth === $p['value'] ];
}

$statusOptions = [];
foreach ($statuses as $s) {
    if ($isAdminPrimary && $s['value']==='deleted') { continue; }
    $statusOptions[] = [
        'value'=>$s['value'],
        'label_key'=>$s['label_key'],
        'selected'=>$user->status === $s['value']
    ];
}

$context = [
    'create_mode' => $creating,
    'flashes'=>$flashView,
    'user'=>[
        'id'=>$user->id,
        'username'=>$user->username,
        'firstname'=>htmlspecialchars($user->firstname ?? '',ENT_QUOTES,'UTF-8'),
        'lastname'=>htmlspecialchars($user->lastname ?? '',ENT_QUOTES,'UTF-8'),
        'email'=>htmlspecialchars($user->email,ENT_QUOTES,'UTF-8'),
        'status'=>$user->status,
        'auth'=>$currentAuth,
        'is_admin_primary'=>$isAdminPrimary,
        'is_deleted'=>$isDeleted,
    ],
    'status_options'=>$statusOptions,
    'auth_options'=>$authOptions,
    'csrf_token'=>admin_csrf_token(),
    'return_url'=>$returnUrl,
    'home_link'=>'../index.php'
];

echo $OUTPUT->header([
    'page_title'=> $creating ? 'Create User' : 'Edit User',
    'site_name'=>'Admin Console',
    'user'=>['username'=>$currentUser->getUsername()],
]);
echo $OUTPUT->renderFromTemplate('admin_user_edit',$context);
echo $OUTPUT->footer();

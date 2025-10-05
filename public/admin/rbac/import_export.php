<?php
require_once dirname(__DIR__) . '/_bootstrap_admin.php';
use DevFramework\Core\Access\AccessManager;
$AM = AccessManager::getInstance();

global $OUTPUT, $DB, $currentUser;
if (!hasCapability('rbac:importexport', $currentUser->getId())) { http_response_code(403); echo 'Forbidden'; exit; }

$action = $_POST['action'] ?? null;

function export_roles_payload(bool $includeAdmin = true): array {
    global $DB;
    $roles = $DB->get_records('roles', [], 'sortorder ASC, id ASC');
    $result = [];
    foreach ($roles as $r) {
        if (!$includeAdmin && $r->shortname === 'admin') { continue; }
        $capsRecs = $DB->get_records('role_capabilities', ['roleid'=>$r->id]);
        $caps = array_map(fn($c)=>(object)['capability'=>$c->capability,'permission'=>$c->permission], $capsRecs);
        $templateAssign = $DB->get_records('role_template_assign',['roleid'=>$r->id]);
        $templates = [];
        foreach ($templateAssign as $ta) { $tpl = $DB->get_record('role_templates',['id'=>$ta->templateid]); if ($tpl) { $templates[] = (object)['shortname'=>$tpl->shortname]; } }
        $result[] = [ 'shortname'=>$r->shortname,'name'=>$r->name,'description'=>$r->description,'sortorder'=>(int)$r->sortorder,'capabilities'=>array_map(fn($c)=>['name'=>$c->capability,'permission'=>$c->permission], $caps),'templates'=>array_map(fn($t)=>$t->shortname, $templates) ];
    }
    return [ 'exported_at'=>date('c'),'include_admin'=>$includeAdmin,'roles'=>$result ];
}

function find_template_id_by_shortname(string $shortname): ?int { global $DB; $rec = $DB->get_record('role_templates',['shortname'=>$shortname]); return $rec? (int)$rec->id : null; }

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
    $mode = $_POST['mode'] ?? 'merge';
    $jsonInput = '';
    if (!empty($_FILES['import_file']['tmp_name'])) { $jsonInput = file_get_contents($_FILES['import_file']['tmp_name']); }
    else { $jsonInput = $_POST['import_json'] ?? ''; }
    if (!$jsonInput) { admin_flash('error','No import data provided'); header('Location: import_export.php'); exit; }
    $data = json_decode($jsonInput, true);
    if (!is_array($data) || !isset($data['roles'])) { admin_flash('error','Invalid JSON structure'); header('Location: import_export.php'); exit; }
    $now = time();
    foreach ($data['roles'] as $r) {
        if (!isset($r['shortname'],$r['name'])) { continue; }
        $short = $r['shortname'];
        $existing = $DB->get_record('roles',['shortname'=>$short]);
        if (!$existing) {
            $roleId = $DB->insert_record('roles',[ 'name'=>$r['name'],'shortname'=>$short,'description'=>$r['description'] ?? '','sortorder'=>$r['sortorder'] ?? 100,'timecreated'=>$now,'timemodified'=>$now ]);
            $AM->logAction($currentUser->getId(),'role_import_create',['shortname'=>$short]);
        } else {
            $roleId = (int)$existing->id;
            $DB->update_record('roles',[ 'id'=>$roleId,'name'=>$r['name'],'description'=>$r['description'] ?? $existing->description,'sortorder'=>$r['sortorder'] ?? $existing->sortorder,'timemodified'=>$now ]);
            $AM->logAction($currentUser->getId(),'role_import_update',['shortname'=>$short]);
            if ($mode==='replace') {
                foreach ($DB->get_records('role_capabilities',['roleid'=>$roleId]) as $ec) { $DB->delete_records('role_capabilities',['id'=>$ec->id]); }
                foreach ($DB->get_records('role_template_assign',['roleid'=>$roleId]) as $ea) { $DB->delete_records('role_template_assign',['id'=>$ea->id]); }
            }
        }
        if (!empty($r['capabilities']) && is_array($r['capabilities'])) {
            foreach ($r['capabilities'] as $cap) {
                if (!isset($cap['name'],$cap['permission'])) { continue; }
                $perm = $cap['permission']; if (!in_array($perm,['allow','prevent','prohibit','notset'],true) || $perm==='notset') { continue; }
                $existingCap = $DB->get_record('role_capabilities',[ 'roleid'=>$roleId,'capability'=>$cap['name'] ]);
                if ($existingCap) { if ($existingCap->permission !== $perm) { $DB->update_record('role_capabilities',[ 'id'=>$existingCap->id,'permission'=>$perm,'timemodified'=>$now ]); } }
                else { $DB->insert_record('role_capabilities',[ 'roleid'=>$roleId,'capability'=>$cap['name'],'permission'=>$perm,'timecreated'=>$now,'timemodified'=>$now ]); }
            }
        }
        if (!empty($r['templates']) && is_array($r['templates'])) {
            foreach ($r['templates'] as $ts) {
                $tid = find_template_id_by_shortname($ts);
                if ($tid) { $existingAssign = $DB->get_record('role_template_assign',['roleid'=>$roleId,'templateid'=>$tid]); if ($existingAssign) { $DB->update_record('role_template_assign',[ 'id'=>$existingAssign->id,'timemodified'=>$now ]); } else { $DB->insert_record('role_template_assign',[ 'roleid'=>$roleId,'templateid'=>$tid,'timecreated'=>$now,'timemodified'=>$now ]); } }
            }
        }
    }
    $AM->resetCache();
    $AM->logAction($currentUser->getId(),'role_import',['mode'=>$mode,'role_count'=>count($data['roles'])]);
    $AM->syncAllCapabilities();
    admin_flash('success','Import processed');
    header('Location: import_export.php'); exit;
}

$flashes = admin_get_flashes();
$flashView = array_map(fn($f)=>['css_class'=>$f['type']==='error'?'bg-red-200 text-red-900':'bg-green-200 text-green-900','msg'=>$f['msg']], $flashes);

echo $OUTPUT->header(['page_title'=>'Import / Export Roles','site_name'=>'Admin Console','user'=>['username'=>$currentUser->getUsername()]]);
echo $OUTPUT->renderFromTemplate('admin_import_export',[ 'flashes'=>$flashView,'csrf_token'=>admin_csrf_token(),'home_link'=>'../index.php' ]);
echo $OUTPUT->footer();


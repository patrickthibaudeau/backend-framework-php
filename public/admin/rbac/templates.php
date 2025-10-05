<?php
require_once dirname(__DIR__) . '/_bootstrap_admin.php';
use DevFramework\Core\Access\AccessManager;
$AM = AccessManager::getInstance();

global $DB, $OUTPUT, $currentUser;

if (!hasCapability('rbac:manage', $currentUser->getId())) { http_response_code(403); echo 'Forbidden'; exit; }

$action = $_POST['action'] ?? null;
$templateId = isset($_GET['edit']) ? (int)$_GET['edit'] : null;

function fetch_templates() { global $DB; return $DB->get_records('role_templates', [], 'shortname ASC'); }

if ($action==='create_template') {
    if (!admin_csrf_validate()) { admin_flash('error','CSRF failed'); header('Location: templates.php'); exit; }
    $shortname = trim($_POST['shortname']??'');
    $name = trim($_POST['name']??'');
    $description = trim($_POST['description']??'');
    if ($shortname && $name) {
        if ($DB->record_exists('role_templates',['shortname'=>$shortname])) { admin_flash('error','Template shortname exists'); }
        else { $now=time(); $id = $DB->insert_record('role_templates',[ 'name'=>$name,'shortname'=>$shortname,'description'=>$description,'timecreated'=>$now,'timemodified'=>$now ]); $AM->logAction($currentUser->getId(),'create_template',[ 'templateid'=>$id,'shortname'=>$shortname ]); admin_flash('success','Template created'); }
    } else { admin_flash('error','Missing fields'); }
    header('Location: templates.php'); exit;
}

if ($action==='update_template_caps' && $templateId) {
    if (!admin_csrf_validate()) { admin_flash('error','CSRF failed'); header('Location: templates.php?edit='.$templateId); exit; }
    $template = $DB->get_record('role_templates',['id'=>$templateId]);
    if (!$template) { admin_flash('error','Template not found'); header('Location: templates.php'); exit; }
    $caps = $DB->get_records('capabilities', [], 'name ASC', 'id, name, captype');
    $now=time();
    foreach ($caps as $c) {
        $field = 'cap_'.md5($c->name); $perm = $_POST[$field] ?? ''; if (!in_array($perm,['','allow','prevent','prohibit'],true)) { $perm=''; }
        $existing = $DB->get_record('role_template_capabilities',['templateid'=>$templateId,'capability'=>$c->name]);
        if ($perm==='') { if ($existing) { $DB->delete_records('role_template_capabilities',[ 'templateid'=>$templateId,'capability'=>$c->name ]); $AM->logAction($currentUser->getId(),'template_capability_remove',['cap'=>$c->name],null,null,$c->name); } }
        else { if ($existing) { if ($existing->permission !== $perm) { $DB->update_record('role_template_capabilities',[ 'id'=>$existing->id,'permission'=>$perm,'timemodified'=>$now ]); $AM->logAction($currentUser->getId(),'template_capability_set',['cap'=>$c->name,'perm'=>$perm],null,null,$c->name); } } else { $DB->insert_record('role_template_capabilities',[ 'templateid'=>$templateId,'capability'=>$c->name,'permission'=>$perm,'timecreated'=>$now,'timemodified'=>$now ]); $AM->logAction($currentUser->getId(),'template_capability_set',['cap'=>$c->name,'perm'=>$perm],null,null,$c->name); } }
    }
    $AM->resetCache(); admin_flash('success','Template updated'); header('Location: templates.php?edit='.$templateId); exit;
}

if ($action==='delete_template' && $templateId) {
    if (!admin_csrf_validate()) { admin_flash('error','CSRF failed'); header('Location: templates.php'); exit; }
    $template = $DB->get_record('role_templates',['id'=>$templateId]);
    if ($template) { $DB->delete_records('role_templates',[ 'id'=>$templateId ]); $AM->logAction($currentUser->getId(),'delete_template',['templateid'=>$templateId]); admin_flash('success','Template deleted'); }
    header('Location: templates.php'); exit;
}

$flashes = admin_get_flashes();
$editingTemplate = $templateId ? $DB->get_record('role_templates',['id'=>$templateId]) : null;

$flashView = array_map(fn($f)=>['css_class'=>$f['type']==='error'?'bg-red-200 text-red-900':'bg-green-200 text-green-900','msg'=>$f['msg']], $flashes);
$templates = fetch_templates();
$templatesView = array_map(fn($t)=>['id'=>$t->id,'name'=>$t->name,'shortname'=>$t->shortname], $templates);

// Added home_link for template rendering consistency with updated Mustache templates
$context = [ 'flashes'=>$flashView,'templates'=>$templatesView,'csrf_token'=>admin_csrf_token(), 'home_link'=>'../index.php' ];

if ($editingTemplate) {
    $caps = $DB->get_records('capabilities', [], 'component ASC, name ASC');
    $templateCaps = $DB->get_records('role_template_capabilities',['templateid'=>$editingTemplate->id]);
    $tMap=[]; foreach($templateCaps as $tc){ $tMap[$tc->capability]=$tc->permission; }
    $capView = array_map(function($c) use ($tMap){ $perm=$tMap[$c->name]??''; $options = array_merge([[ 'value'=>'','label'=>'inherit-none','selected'=>$perm==='']], array_map(fn($p)=>['value'=>$p,'label'=>$p,'selected'=>$p===$perm], ['allow','prevent','prohibit'])); return ['name'=>$c->name,'type'=>$c->captype,'field'=>'cap_'.md5($c->name),'options'=>$options]; }, $caps);
    $context['editing']=true; $context['template']=['id'=>$editingTemplate->id,'name'=>$editingTemplate->name]; $context['capabilities']=$capView; $context['home_link'] = '../index.php';
}

echo $OUTPUT->header(['page_title'=>'Role Templates','site_name'=>'Admin Console','user'=>['username'=>$currentUser->getUsername()]]);
echo $OUTPUT->renderFromTemplate('admin_templates', $context);
echo $OUTPUT->footer();


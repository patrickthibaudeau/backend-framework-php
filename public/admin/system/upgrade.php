<?php
require_once dirname(__DIR__) . '/_bootstrap_admin.php';

use DevFramework\Core\Module\ModuleManager;
use DevFramework\Core\Database\ModuleInstaller;
use DevFramework\Core\Access\AccessManager;

global $DB, $OUTPUT, $currentUser;

$results = [];
$nowTs = time();
$addResult = function(string $component, string $type, string $status, string $message, ?string $from = null, ?string $to = null) use (&$results) {
    $results[] = [
        'component' => $component,
        'type' => $type,
        'status' => $status,
        'message' => $message,
        'from_version' => $from,
        'to_version' => $to,
        'status_class' => $status === 'success' ? 'bg-green-100 text-green-800' : ($status === 'skipped' ? 'bg-slate-100 text-slate-600' : 'bg-red-100 text-red-800')
    ];
};

// Core upgrade
try {
    $coreVersionFile = dirname(__DIR__, 3) . '/src/Core/version.php';
    if (file_exists($coreVersionFile)) {
        if (!defined('MATURITY_STABLE')) { require_once dirname(__DIR__, 3) . '/src/Core/Module/constants.php'; }
        $PLUGIN = new stdClass();
        include $coreVersionFile;
        $targetVersion = $PLUGIN->version ?? null;
        $fromVersion = $DB->get_plugin_version('core');
        if ($targetVersion) {
            if (!$fromVersion || version_compare($targetVersion, $fromVersion, '>')) {
                $upgradeScript = dirname(__DIR__, 3) . '/src/Core/db/upgrade.php';
                $ok = true;
                if (file_exists($upgradeScript)) {
                    $pdo = $DB->get_connection();
                    $prefix = $_ENV['DB_PREFIX'] ?? '';
                    $from_version = $fromVersion ?? '0';
                    $to_version = $targetVersion;
                    try { $ok = include $upgradeScript; } catch (Throwable $e) { $ok = false; }
                }
                if ($ok) { $DB->set_plugin_version('core', $targetVersion); $addResult('core','core','success','Upgraded core framework',$fromVersion ?? 'none',$targetVersion); }
                else { $addResult('core','core','error','Core upgrade script failed',$fromVersion ?? 'none',$targetVersion); }
            } else { $addResult('core','core','skipped','Already up to date',$fromVersion,$targetVersion); }
        } else { $addResult('core','core','error','Core version file missing version value'); }
    } else { $addResult('core','core','error','Core version file not found'); }
} catch (Throwable $e) { $addResult('core','core','error','Exception: '.$e->getMessage()); }

// Theme upgrade
try {
    $themeName='default';
    $themeBase = dirname(__DIR__, 3).'/src/Core/Theme/'.$themeName;
    $themeVersionFile = $themeBase.'/version.php';
    $pluginKey = 'core_theme_'.$themeName;
    if (file_exists($themeVersionFile)) {
        if (!defined('MATURITY_STABLE')) { require_once dirname(__DIR__, 3) . '/src/Core/Module/constants.php'; }
        $PLUGIN = new stdClass(); include $themeVersionFile;
        $targetVersion = $PLUGIN->version ?? null; $fromVersion = $DB->get_plugin_version($pluginKey);
        if ($targetVersion) {
            if (!$fromVersion || version_compare($targetVersion,$fromVersion,'>')) {
                $upgradeFile = $themeBase.'/db/upgrade.php'; $ok = true;
                if (file_exists($upgradeFile)) { $pdo=$DB->get_connection(); $prefix=$_ENV['DB_PREFIX']??''; $from_version=$fromVersion ?? '0'; $to_version=$targetVersion; try { $ok = include $upgradeFile; } catch (Throwable $e) { $ok=false; } }
                if ($ok) { $DB->set_plugin_version($pluginKey,$targetVersion); $addResult($pluginKey,'theme','success','Theme upgraded',$fromVersion ?? 'none',$targetVersion); }
                else { $addResult($pluginKey,'theme','error','Theme upgrade failed',$fromVersion ?? 'none',$targetVersion); }
            } else { $addResult($pluginKey,'theme','skipped','Already up to date',$fromVersion,$targetVersion); }
        } else { $addResult($pluginKey,'theme','error','Theme version missing'); }
    } else { $addResult('core_theme_default','theme','error','Theme version file not found'); }
} catch (Throwable $e) { $addResult('core_theme_default','theme','error','Exception: '.$e->getMessage()); }

// Modules
try {
    $mm = ModuleManager::getInstance(); $mm->discoverModules(); $modules=$mm->getAllModules(); $installer = new ModuleInstaller();
    foreach ($modules as $moduleName => $info) {
        $targetVersion = $info['version'] ?? null; $fromVersion = $DB->get_plugin_version($moduleName);
        if (!$targetVersion) { $addResult($moduleName,'module','error','Missing version'); continue; }
        if (!$fromVersion) { $ok = $installer->installModule($moduleName); $addResult($moduleName,'module',$ok?'success':'error',$ok?'Installed':'Installation failed','none',$targetVersion); continue; }
        if (version_compare($targetVersion,$fromVersion,'>')) { $ok = $installer->upgradeModule($moduleName,$fromVersion,$targetVersion); $addResult($moduleName,'module',$ok?'success':'error',$ok?'Upgraded':'Upgrade failed',$fromVersion,$targetVersion); }
        else { $addResult($moduleName,'module','skipped','Already up to date',$fromVersion,$targetVersion); }
    }
} catch (Throwable $e) { $addResult('modules','module','error','Module discovery failed: '.$e->getMessage()); }

 // Capability sync (silent on success; only report errors)
try {
    DevFramework\Core\Access\AccessManager::getInstance()->syncAllCapabilities();
    // Success intentionally not added to $results to avoid cluttering upgrade view with core/module capability definitions.
} catch (Throwable $e) {
    $addResult('capabilities','access','error','Capability sync failed: '.$e->getMessage());
}

$successCount = count(array_filter($results, fn($r)=>$r['status']==='success'));
$errorCount   = count(array_filter($results, fn($r)=>$r['status']==='error'));
$skippedCount = count(array_filter($results, fn($r)=>$r['status']==='skipped'));

$pageData = [
  'generated_at' => date('Y-m-d H:i:s',$nowTs),
  'summary' => [ 'success'=>$successCount, 'errors'=>$errorCount, 'skipped'=>$skippedCount, 'total'=>count($results) ],
  'rows' => $results,
  'home_link' => '../index.php'
];

echo $OUTPUT->header(['page_title'=>'Upgrade Utility','site_name'=>'Admin Console','user'=>['username'=>$currentUser->getUsername()]]);
echo $OUTPUT->renderFromTemplate('admin_upgrade', $pageData);
echo $OUTPUT->footer();


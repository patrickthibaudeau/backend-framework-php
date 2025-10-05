<?php
/**
 * Default Theme Install Script
 *
 * Expected external variables when included:
 * - $pdo    (PDO)    Active database connection
 * - $prefix (string) Table prefix (e.g. dev_)
 */

try {
    if (!isset($pdo) || !($pdo instanceof PDO)) {
        throw new Exception('Theme install: Missing or invalid $pdo connection');
    }

    if (!isset($prefix)) {
        $prefix = $_ENV['DB_PREFIX'] ?? 'dev_';
    }

    $pluginsTable = $prefix . 'config_plugins';

    // Ensure config_plugins table exists (should be created by core installer, but double-check)
    $stmt = $pdo->query("SHOW TABLES LIKE '{$pluginsTable}'");
    $exists = $stmt->fetchColumn() !== false;
    if (!$exists) {
        throw new Exception('Theme install: config_plugins table not found; core not installed?');
    }

    // Define maturity constants if not already defined (needed for version.php)
    if (!defined('MATURITY_ALPHA')) define('MATURITY_ALPHA', 'MATURITY_ALPHA');
    if (!defined('MATURITY_BETA')) define('MATURITY_BETA', 'MATURITY_BETA');
    if (!defined('MATURITY_RC')) define('MATURITY_RC', 'MATURITY_RC');
    if (!defined('MATURITY_STABLE')) define('MATURITY_STABLE', 'MATURITY_STABLE');

    // Load version from theme version.php
    $versionFile = dirname(__DIR__) . '/version.php';
    if (!file_exists($versionFile)) {
        throw new Exception('Theme install: version.php not found');
    }

    $PLUGIN = new \stdClass();
    include $versionFile; // Defines $PLUGIN

    if (empty($PLUGIN->version)) {
        throw new Exception('Theme install: version not defined in version.php');
    }

    $pluginName = 'core_theme_default';
    $time = time();

    // Insert or update the version record in config_plugins
    $sql = "INSERT INTO `{$pluginsTable}` (plugin, name, value, timemodified, timecreated)
            VALUES (:plugin, 'version', :value, :timemodified, :timecreated)
            ON DUPLICATE KEY UPDATE value = VALUES(value), timemodified = VALUES(timemodified)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':plugin' => $pluginName,
        ':value' => $PLUGIN->version,
        ':timemodified' => $time,
        ':timecreated' => $time
    ]);

    error_log("Theme Install: Set version {$PLUGIN->version} for {$pluginName}");
    return true;

} catch (Exception $e) {
    error_log('Theme Install Error: ' . $e->getMessage());
    return false;
}

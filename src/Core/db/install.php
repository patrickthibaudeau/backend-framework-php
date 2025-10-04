<?php
/**
 * Core Database Schema - Install Script
 * Contains all core framework tables that must exist by default
 *
 * This file is loaded directly by CoreInstaller and has access to:
 * - $pdo: PDO connection
 * - $prefix: Table prefix (e.g., "dev_")
 */

try {
    // Create config table if it doesn't exist
    $configTable = $prefix . 'config';
    $stmt = $pdo->query("SHOW TABLES LIKE '{$configTable}'");
    $configTableExists = $stmt->fetchColumn() !== false;

    if (!$configTableExists) {
        $sql = "CREATE TABLE `{$configTable}` (
            id int(10) NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL DEFAULT '',
            value longtext,
            timemodified int(10) DEFAULT NULL,
            timecreated int(10) DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY name (name)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

        $pdo->exec($sql);
        error_log("Core Schema: Created config table");
    }

    // Create config_plugins table if it doesn't exist
    $pluginsTable = $prefix . 'config_plugins';
    $stmt = $pdo->query("SHOW TABLES LIKE '{$pluginsTable}'");
    $pluginsTableExists = $stmt->fetchColumn() !== false;

    if (!$pluginsTableExists) {
        $sql = "CREATE TABLE `{$pluginsTable}` (
            id int(11) NOT NULL AUTO_INCREMENT,
            plugin varchar(100) NOT NULL COMMENT 'Plugin/module name',
            name varchar(100) NOT NULL COMMENT 'Configuration name',
            value longtext COMMENT 'Configuration value',
            timecreated int(11) NOT NULL DEFAULT 0 COMMENT 'Time created',
            timemodified int(11) NOT NULL DEFAULT 0 COMMENT 'Time modified',
            PRIMARY KEY (id),
            UNIQUE KEY plugin_name (plugin, name),
            KEY plugin (plugin)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Plugin configuration storage'";

        $pdo->exec($sql);
        error_log("Core Schema: Created config_plugins table");
    }

    error_log("Core Schema: Installation completed successfully");
    return true;

} catch (Exception $e) {
    error_log("Core Schema Install Error: " . $e->getMessage());
    return false;
}

<?php
/**
 * Core Database Schema - Install Script
 * Contains all core framework tables that must exist by default
 *
 * This file is loaded by SchemaLoader and has access to:
 * - $db: Database instance
 * - $connection: PDO connection
 * - $prefix: Table prefix
 * - $getTableName($name): Helper to get prefixed table name
 * - $executeSql($sql): Helper to execute SQL
 * - $tableExists($name): Helper to check if table exists
 */

try {
    // Create config_plugins table if it doesn't exist
    if (!$tableExists('config_plugins')) {
        $tableName = $getTableName('config_plugins');

        $sql = "CREATE TABLE `{$tableName}` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `plugin` varchar(100) NOT NULL COMMENT 'Plugin/module name',
            `name` varchar(100) NOT NULL COMMENT 'Configuration name',
            `value` longtext COMMENT 'Configuration value',
            `timecreated` int(11) NOT NULL DEFAULT 0 COMMENT 'Time created',
            `timemodified` int(11) NOT NULL DEFAULT 0 COMMENT 'Time modified',
            PRIMARY KEY (`id`),
            UNIQUE KEY `plugin_name` (`plugin`, `name`),
            KEY `plugin` (`plugin`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Plugin configuration storage'";

        $executeSql($sql);
        error_log("Core Schema: Created config_plugins table");
    }

    // Mark this schema as version 1
    return true;

} catch (Exception $e) {
    error_log("Core Schema Install Error: " . $e->getMessage());
    return false;
}

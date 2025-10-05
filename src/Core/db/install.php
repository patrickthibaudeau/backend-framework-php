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

    // --- New Access Control Tables ---

    // capabilities table
    $capabilitiesTable = $prefix . 'capabilities';
    $stmt = $pdo->query("SHOW TABLES LIKE '{$capabilitiesTable}'");
    $capabilitiesExists = $stmt->fetchColumn() !== false;
    if (!$capabilitiesExists) {
        $sql = "CREATE TABLE `{$capabilitiesTable}` (
            id INT(11) NOT NULL AUTO_INCREMENT,
            name VARCHAR(191) NOT NULL COMMENT 'Component:capability (unique)',
            captype VARCHAR(50) NOT NULL COMMENT 'Type e.g. read/write',
            component VARCHAR(191) NOT NULL COMMENT 'Component name',
            timecreated INT(11) NOT NULL DEFAULT 0,
            timemodified INT(11) NOT NULL DEFAULT 0,
            PRIMARY KEY (id),
            UNIQUE KEY uk_capability_name (name),
            KEY idx_component (component)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Registered capability definitions'";
        $pdo->exec($sql);
        error_log('Core Schema: Created capabilities table');
    }

    // roles table
    $rolesTable = $prefix . 'roles';
    $stmt = $pdo->query("SHOW TABLES LIKE '{$rolesTable}'");
    $rolesExists = $stmt->fetchColumn() !== false;
    if (!$rolesExists) {
        $sql = "CREATE TABLE `{$rolesTable}` (
            id INT(11) NOT NULL AUTO_INCREMENT,
            name VARCHAR(255) NOT NULL COMMENT 'Role display name',
            shortname VARCHAR(100) NOT NULL COMMENT 'Unique short name',
            description TEXT NULL COMMENT 'Role description',
            sortorder INT(11) NOT NULL DEFAULT 0 COMMENT 'Lower value = higher priority',
            timecreated INT(11) NOT NULL DEFAULT 0,
            timemodified INT(11) NOT NULL DEFAULT 0,
            PRIMARY KEY (id),
            UNIQUE KEY uk_role_shortname (shortname),
            KEY idx_sortorder (sortorder)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Defined system roles'";
        $pdo->exec($sql);
        error_log('Core Schema: Created roles table');
    }

    // role_capabilities table
    $roleCapsTable = $prefix . 'role_capabilities';
    $stmt = $pdo->query("SHOW TABLES LIKE '{$roleCapsTable}'");
    $roleCapsExists = $stmt->fetchColumn() !== false;
    if (!$roleCapsExists) {
        $sql = "CREATE TABLE `{$roleCapsTable}` (
            id INT(11) NOT NULL AUTO_INCREMENT,
            roleid INT(11) NOT NULL COMMENT 'FK to roles.id',
            capability VARCHAR(191) NOT NULL COMMENT 'Capability name',
            permission VARCHAR(20) NOT NULL DEFAULT 'notset' COMMENT 'allow|prevent|prohibit|notset',
            timecreated INT(11) NOT NULL DEFAULT 0,
            timemodified INT(11) NOT NULL DEFAULT 0,
            PRIMARY KEY (id),
            UNIQUE KEY uk_role_capability (roleid, capability),
            KEY idx_capability (capability),
            CONSTRAINT fk_rolecaps_role FOREIGN KEY (roleid) REFERENCES `{$rolesTable}` (id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Role to capability permission mapping'";
        $pdo->exec($sql);
        error_log('Core Schema: Created role_capabilities table');
    }

    // role_assignment table
    $roleAssignTable = $prefix . 'role_assignment';
    $stmt = $pdo->query("SHOW TABLES LIKE '{$roleAssignTable}'");
    $roleAssignExists = $stmt->fetchColumn() !== false;
    if (!$roleAssignExists) {
        $sql = "CREATE TABLE `{$roleAssignTable}` (
            id INT(11) NOT NULL AUTO_INCREMENT,
            userid INT(11) NOT NULL COMMENT 'User ID',
            roleid INT(11) NOT NULL COMMENT 'Role ID',
            component VARCHAR(191) NULL COMMENT 'Component context of assignment',
            timecreated INT(11) NOT NULL DEFAULT 0,
            timemodified INT(11) NOT NULL DEFAULT 0,
            PRIMARY KEY (id),
            UNIQUE KEY uk_user_role_component (userid, roleid, component),
            KEY idx_userid (userid),
            KEY idx_roleid (roleid),
            CONSTRAINT fk_roleassign_role FOREIGN KEY (roleid) REFERENCES `{$rolesTable}` (id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='User role assignments'";
        $pdo->exec($sql);
        error_log('Core Schema: Created role_assignment table');
    }

    // --- End New Access Control Tables ---

    error_log("Core Schema: Installation completed successfully");
    return true;

} catch (Exception $e) {
    error_log("Core Schema Install Error: " . $e->getMessage());
    return false;
}

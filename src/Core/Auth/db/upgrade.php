<?php
/**
 * Auth Database Schema - Upgrade Script
 * Contains upgrade logic for authentication tables
 *
 * This file is loaded by SchemaLoader and has access to:
 * - $db: Database instance
 * - $connection: PDO connection
 * - $prefix: Table prefix
 * - $getTableName($name): Helper to get prefixed table name
 * - $executeSql($sql): Helper to execute SQL
 * - $tableExists($name): Helper to check if table exists
 * - $from_version: Version upgrading from
 * - $to_version: Version upgrading to
 */

try {

    // Upgrade to version 4: Add user preferences table
    if ($from_version < 2 && $to_version >= 2) {
        if (!$tableExists('user_preferences')) {
            $prefsTable = $getTableName('user_preferences');
            $sql = "CREATE TABLE `{$prefsTable}` (
                id INT(11) NOT NULL AUTO_INCREMENT,
                userid INT(11) NOT NULL COMMENT 'User ID',
                name VARCHAR(100) NOT NULL COMMENT 'Preference key',
                value TEXT NULL COMMENT 'Preference value',
                timecreated INT(11) NOT NULL DEFAULT 0,
                timemodified INT(11) NOT NULL DEFAULT 0,
                PRIMARY KEY (id),
                UNIQUE KEY user_pref (userid, name),
                KEY userid (userid),
                FOREIGN KEY (userid) REFERENCES {$getTableName('users')}(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='User preferences storage'";
            $executeSql($sql);
            error_log("Auth Schema: Upgraded to version 4 - Created user_preferences table");
        }
    }

    error_log("Auth Schema: Upgrade completed to version {$to_version}");
    return true;

} catch (Exception $e) {
    error_log("Auth Schema Upgrade Error: " . $e->getMessage());
    return false;
}

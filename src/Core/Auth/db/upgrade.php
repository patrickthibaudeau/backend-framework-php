<?php
/**
 * Auth Component Upgrade Script
 * Upgrades Auth component from any version to the latest
 *
 * Available variables (provided by framework):
 * - $from_version: Version upgrading from (e.g., "1")
 * - $to_version: Version upgrading to (e.g., "2.0")
 * - $pdo: Direct PDO connection
 * - $prefix: Table prefix (e.g., "dev_")
 */

try {
    error_log("Auth Upgrade: Starting upgrade from version {$from_version} to version {$to_version}");

    // Upgrade to version 2.0: Add user preferences table
    if (version_compare($from_version, '2.0', '<') && version_compare($to_version, '2.0', '>=')) {

        // First check if users table exists (required for foreign key)
        $usersTable = $prefix . 'users';
        $stmt = $pdo->query("SHOW TABLES LIKE '{$usersTable}'");
        $usersTableExists = $stmt->fetchColumn() !== false;

        if (!$usersTableExists) {
            error_log("Auth Upgrade: ERROR - users table does not exist, cannot create user_preferences with foreign key");
            throw new Exception("Users table must exist before creating user_preferences table");
        }

        // Check if user_preferences table already exists
        $prefsTable = $prefix . 'user_preferences';
        $stmt = $pdo->query("SHOW TABLES LIKE '{$prefsTable}'");
        $tableExists = $stmt->fetchColumn() !== false;

        if (!$tableExists) {
            error_log("Auth Upgrade: Creating user_preferences table");

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
                FOREIGN KEY (userid) REFERENCES `{$usersTable}`(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='User preferences storage'";

            $pdo->exec($sql);
            error_log("Auth Upgrade: user_preferences table created successfully");
        } else {
            error_log("Auth Upgrade: user_preferences table already exists, skipping creation");
        }
    }

    error_log("Auth Upgrade: Completed successfully to version {$to_version}");
    return true;

} catch (Exception $e) {
    error_log("Auth Upgrade Error: " . $e->getMessage());
    error_log("Auth Upgrade Stack Trace: " . $e->getTraceAsString());
    throw $e; // Re-throw to fail the upgrade
}

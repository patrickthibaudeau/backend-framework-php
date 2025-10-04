<?php
/**
 * Auth Database Schema - Install Script
 * Contains authentication-related tables (users, user_sessions)
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
    // Create users table if it doesn't exist
    if (!$tableExists('users')) {
        $tableName = $getTableName('users');
        
        $sql = "CREATE TABLE IF NOT EXISTS `{$tableName}` (
            id INT(11) NOT NULL AUTO_INCREMENT COMMENT 'Unique user ID',
            auth VARCHAR(100) NOT NULL COMMENT 'Authentication type (e.g., manual, ldap, oauth, saml2)',
            username VARCHAR(100) NOT NULL COMMENT 'Unique username',
            email VARCHAR(255) NOT NULL COMMENT 'User email address',
            password VARCHAR(255) NOT NULL COMMENT 'Hashed password',
            firstname VARCHAR(255) NULL COMMENT 'User first name',
            lastname VARCHAR(255) NULL COMMENT 'User last name',
            idnumber VARCHAR(255) NULL COMMENT 'An id number',
            status VARCHAR(20) NOT NULL DEFAULT 'active' COMMENT 'User status: active, inactive, suspended',
            emailverified BOOLEAN NOT NULL DEFAULT 0 COMMENT 'Whether email is verified',
            lastlogin INT(11) NULL DEFAULT NULL COMMENT 'Unix timestamp of last login',
            timecreated INT(11) NOT NULL DEFAULT 0 COMMENT 'Unix timestamp when user was created',
            timemodified INT(11) NOT NULL DEFAULT 0 COMMENT 'Unix timestamp when user was last modified',
            PRIMARY KEY (id),
            INDEX username (username),
            INDEX email (email),
            INDEX status (status),
            INDEX timecreated (timecreated),
            UNIQUE KEY username_unique (username),
            UNIQUE KEY email_unique (email)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $executeSql($sql);
        error_log("Auth Schema: Created users table");
    }
    
    // Create user_sessions table if it doesn't exist
    if (!$tableExists('user_sessions')) {
        $tableName = $getTableName('user_sessions');
        
        $sql = "CREATE TABLE IF NOT EXISTS `{$tableName}` (
            id VARCHAR(128) NOT NULL COMMENT 'Session ID',
            userid INT(11) NULL COMMENT 'User ID (null for anonymous sessions)',
            ip_address VARCHAR(45) NULL COMMENT 'IP address (supports IPv6)',
            user_agent TEXT NULL COMMENT 'User agent string',
            data LONGTEXT NULL COMMENT 'Serialized session data',
            expires_at INT(11) NOT NULL DEFAULT 0 COMMENT 'Unix timestamp when session expires',
            timecreated INT(11) NOT NULL DEFAULT 0,
            timemodified INT(11) NOT NULL DEFAULT 0,
            PRIMARY KEY (id),
            INDEX userid (userid),
            INDEX expires_at (expires_at),
            INDEX ip_address (ip_address)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $executeSql($sql);
        error_log("Auth Schema: Created user_sessions table");
    }
    
    // Create default users if none exist
    $usersTableName = $getTableName('users');
    $stmt = $connection->prepare("SELECT COUNT(*) as count FROM `{$usersTableName}`");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $userCount = (int)$result['count'];
    
    if ($userCount === 0) {
        $currentTime = time();
        
        // Create default admin user
        $hashedPassword = password_hash('admin123', PASSWORD_DEFAULT);
        $adminSql = "INSERT INTO `{$usersTableName}` (auth, username, email, password, firstname, lastname, status, emailverified, timecreated, timemodified) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $connection->prepare($adminSql);
        $stmt->execute([
            'manual',
            'admin',
            'admin@example.com',
            $hashedPassword,
            'System',
            'Administrator',
            'active',
            1,
            $currentTime,
            $currentTime
        ]);
        $adminId = $connection->lastInsertId();
        error_log("Auth Schema: Created admin user (ID: {$adminId})");
        
        // Create test user
        $hashedPassword = password_hash('password123', PASSWORD_DEFAULT);
        $testSql = "INSERT INTO `{$usersTableName}` (auth, username, email, password, firstname, lastname, status, emailverified, timecreated, timemodified) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $connection->prepare($testSql);
        $stmt->execute([
            'manual',
            'testuser',
            'test@example.com',
            $hashedPassword,
            'Test',
            'User',
            'active',
            1,
            $currentTime,
            $currentTime
        ]);
        $testId = $connection->lastInsertId();
        error_log("Auth Schema: Created test user (ID: {$testId})");
        
        error_log("Auth Schema: Default users created successfully");
    }
    
    // Mark this schema as version 1
    return true;
    
} catch (Exception $e) {
    error_log("Auth Schema Install Error: " . $e->getMessage());
    return false;
}

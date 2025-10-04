<?php
/**
 * Auth Database Schema - Install Script
 * Contains authentication-related tables (users, user_sessions)
 * 
 * This file is loaded directly by AuthInstaller and has access to:
 * - $pdo: PDO connection
 * - $prefix: Table prefix (e.g., "dev_")
 */

try {
    // Create users table if it doesn't exist
    $usersTable = $prefix . 'users';
    $stmt = $pdo->query("SHOW TABLES LIKE '{$usersTable}'");
    $usersTableExists = $stmt->fetchColumn() !== false;

    if (!$usersTableExists) {
        $sql = "CREATE TABLE `{$usersTable}` (
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
        
        $pdo->exec($sql);
        error_log("Auth Schema: Created users table");
    }
    
    // Create user_sessions table if it doesn't exist
    $sessionsTable = $prefix . 'user_sessions';
    $stmt = $pdo->query("SHOW TABLES LIKE '{$sessionsTable}'");
    $sessionsTableExists = $stmt->fetchColumn() !== false;

    if (!$sessionsTableExists) {
        $sql = "CREATE TABLE `{$sessionsTable}` (
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
        
        $pdo->exec($sql);
        error_log("Auth Schema: Created user_sessions table");
    }
    
    // Create default users if none exist
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM `{$usersTable}`");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $userCount = (int)$result['count'];
    
    if ($userCount === 0) {
        $currentTime = time();
        
        // Create default admin user
        $hashedPassword = password_hash('admin123', PASSWORD_DEFAULT);
        $adminSql = "INSERT INTO `{$usersTable}` (auth, username, email, password, firstname, lastname, status, emailverified, timecreated, timemodified) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($adminSql);
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
        $adminId = $pdo->lastInsertId();
        error_log("Auth Schema: Created admin user (ID: {$adminId})");
        
        // Create test user
        $hashedPassword = password_hash('password123', PASSWORD_DEFAULT);
        $testSql = "INSERT INTO `{$usersTable}` (auth, username, email, password, firstname, lastname, status, emailverified, timecreated, timemodified) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($testSql);
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
        $testId = $pdo->lastInsertId();
        error_log("Auth Schema: Created test user (ID: {$testId})");
        
        error_log("Auth Schema: Default users created successfully");
    }
    
    error_log("Auth Schema: Installation completed successfully");
    return true;
    
} catch (Exception $e) {
    error_log("Auth Schema Install Error: " . $e->getMessage());
    return false;
}

<?php

namespace DevFramework\Core\Database;

use DevFramework\Core\Config\Configuration;

/**
 * Database factory and initialization
 */
class DatabaseFactory
{
    /**
     * Initialize global database instance
     */
    public static function initialize(): Database
    {
        // Load database constants
        require_once __DIR__ . '/constants.php';

        // Get database instance
        $db = Database::getInstance();

        // Try to connect to database
        try {
            $db->connect();
        } catch (DatabaseException $e) {
            // Log the error but don't fail - allow the app to run without DB
            if (function_exists('error_log')) {
                error_log("Database connection failed: " . $e->getMessage());
            }
            // Don't throw the exception - return the instance anyway
        }

        return $db;
    }

    /**
     * Create global $DB variable
     */
    public static function createGlobal(): void
    {
        global $DB;

        if (!isset($DB)) {
            $DB = self::initialize();
        }
    }

    /**
     * Reset database connection (for testing)
     */
    public static function reset(): void
    {
        global $DB;

        if (isset($DB)) {
            $DB->disconnect();
            $DB = null;
        }
    }
}

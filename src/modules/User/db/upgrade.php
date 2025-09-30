<?php

/**
 * User Module Database Upgrade Script
 * This file handles database upgrades for the user module
 *
 * Available variables:
 * - $DB: Database instance
 * - $schemaBuilder: SchemaBuilder instance
 * - $fromVersion: Current version
 * - $toVersion: Target version
 */

// Example upgrade logic
if (version_compare($fromVersion, '2025100100', '<')) {
    // Upgrade to version 2025100100
    // Add any database modifications here

    // Example: Add new column to users table
    // $DB->execute("ALTER TABLE {$DB->addPrefix('users')} ADD COLUMN new_field VARCHAR(255) DEFAULT NULL");

    // Example: Create new index
    // $DB->execute("CREATE INDEX idx_new_field ON {$DB->addPrefix('users')} (new_field)");
}

// Future upgrade example
if (version_compare($fromVersion, '2025110100', '<')) {
    // Upgrade to version 2025110100
    // Add future upgrade logic here
}

// Log the upgrade
error_log("User module upgraded from {$fromVersion} to {$toVersion}");

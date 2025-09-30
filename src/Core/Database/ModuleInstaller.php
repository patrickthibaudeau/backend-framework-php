<?php

namespace DevFramework\Core\Database;

use DevFramework\Core\Module\ModuleManager;

/**
 * Module database installer
 * Handles installing and upgrading database schemas for modules
 */
class ModuleInstaller
{
    private SchemaBuilder $schemaBuilder;
    private Database $database;

    public function __construct()
    {
        $this->database = Database::getInstance();
        $this->schemaBuilder = new SchemaBuilder($this->database);
    }

    /**
     * Install database tables for a module
     */
    public function installModule(string $moduleName): bool
    {
        try {
            $schemaPath = $this->getModuleSchemaPath($moduleName);

            if (!file_exists($schemaPath)) {
                // No database schema file found - that's okay, not all modules need databases
                return true;
            }

            $schemas = $this->loadSchemaFile($schemaPath);

            foreach ($schemas as $tableName => $schema) {
                $this->schemaBuilder->createTable($tableName, $schema);
            }

            // Update module version in config_plugins if schema was installed
            $version = $this->getModuleVersion($moduleName);
            if ($version) {
                $this->database->set_plugin_version($moduleName, $version);
            }

            return true;
        } catch (\Exception $e) {
            throw new DatabaseException("Failed to install module '{$moduleName}': " . $e->getMessage());
        }
    }

    /**
     * Upgrade module database schema
     */
    public function upgradeModule(string $moduleName, string $fromVersion, string $toVersion): bool
    {
        try {
            // First run the base install to create/update tables
            $this->installModule($moduleName);

            // Then run any upgrade scripts
            $upgradePath = $this->getModuleUpgradePath($moduleName);
            if (file_exists($upgradePath)) {
                $this->runUpgradeScript($moduleName, $upgradePath, $fromVersion, $toVersion);
            }

            // Update version
            $this->database->set_plugin_version($moduleName, $toVersion);

            return true;
        } catch (\Exception $e) {
            throw new DatabaseException("Failed to upgrade module '{$moduleName}': " . $e->getMessage());
        }
    }

    /**
     * Uninstall module (drop its tables)
     */
    public function uninstallModule(string $moduleName): bool
    {
        try {
            $schemaPath = $this->getModuleSchemaPath($moduleName);

            if (!file_exists($schemaPath)) {
                return true; // No schema file, nothing to uninstall
            }

            $schemas = $this->loadSchemaFile($schemaPath);

            // Drop tables in reverse order to handle dependencies
            $tableNames = array_reverse(array_keys($schemas));
            foreach ($tableNames as $tableName) {
                $this->schemaBuilder->dropTable($tableName);
            }

            // Remove module configuration
            $this->database->unset_plugin_configs($moduleName);

            return true;
        } catch (\Exception $e) {
            throw new DatabaseException("Failed to uninstall module '{$moduleName}': " . $e->getMessage());
        }
    }

    /**
     * Install all modules that need database updates
     */
    public function installAllModules(): array
    {
        $results = [];
        $moduleManager = ModuleManager::getInstance();
        $modules = $moduleManager->getAllModules();

        // Check if any modules need upgrades
        $upgradesNeeded = false;
        foreach ($modules as $moduleName => $moduleInfo) {
            $currentVersion = $this->database->get_plugin_version($moduleName);
            $moduleVersion = $moduleInfo['version'] ?? null;

            if ((!$currentVersion && $moduleVersion) ||
                ($currentVersion && $moduleVersion && version_compare($moduleVersion, $currentVersion, '>'))) {
                $upgradesNeeded = true;
                break;
            }
        }

        // Enable maintenance mode if upgrades are needed
        $maintenanceMode = null;
        if ($upgradesNeeded) {
            $maintenanceMode = new \DevFramework\Core\Maintenance\MaintenanceMode();
            $maintenanceMode->enable('Database upgrades in progress', 300); // 5 minutes estimated
        }

        try {
            foreach ($modules as $moduleName => $moduleInfo) {
                try {
                    $currentVersion = $this->database->get_plugin_version($moduleName);
                    $moduleVersion = $moduleInfo['version'] ?? null;

                    if (!$currentVersion && $moduleVersion) {
                        // New module - install it and add version to config_plugins
                        $this->installModule($moduleName);
                        $this->ensureModuleVersionTracked($moduleName, $moduleInfo);
                        $results[$moduleName] = "Installed version {$moduleVersion}";
                    } elseif ($currentVersion && $moduleVersion && version_compare($moduleVersion, $currentVersion, '>')) {
                        // Module needs upgrade
                        $this->upgradeModule($moduleName, $currentVersion, $moduleVersion);
                        $results[$moduleName] = "Upgraded from {$currentVersion} to {$moduleVersion}";
                    } else {
                        // Module is up to date, but ensure it's tracked in config_plugins
                        $this->ensureModuleVersionTracked($moduleName, $moduleInfo);
                        $results[$moduleName] = "Up to date";
                    }
                } catch (\Exception $e) {
                    $results[$moduleName] = "Error: " . $e->getMessage();
                }
            }
        } finally {
            // Always disable maintenance mode when done
            if ($maintenanceMode) {
                $maintenanceMode->disable();
            }
        }

        return $results;
    }

    /**
     * Ensure module version is tracked in config_plugins table
     */
    private function ensureModuleVersionTracked(string $moduleName, array $moduleInfo): void
    {
        $moduleVersion = $moduleInfo['version'] ?? null;

        if (!$moduleVersion) {
            return; // No version to track
        }

        // Check if version is already tracked
        $currentVersion = $this->database->get_plugin_version($moduleName);

        if (!$currentVersion) {
            // Add version tracking for this module
            $currentTime = time();
            $this->database->insert_record('config_plugins', [
                'plugin' => $moduleName,
                'name' => 'version',
                'value' => $moduleVersion,
                'timecreated' => $currentTime,
                'timemodified' => $currentTime
            ]);
        }
    }

    /**
     * Get module schema file path
     */
    private function getModuleSchemaPath(string $moduleName): string
    {
        return app_path("src/modules/{$moduleName}/db/install.php");
    }

    /**
     * Get module upgrade script path
     */
    private function getModuleUpgradePath(string $moduleName): string
    {
        return app_path("src/modules/{$moduleName}/db/upgrade.php");
    }

    /**
     * Load schema definition from file
     */
    private function loadSchemaFile(string $schemaPath): array
    {
        if (!file_exists($schemaPath)) {
            throw new \InvalidArgumentException("Schema file not found: {$schemaPath}");
        }

        // Load the schema file
        $schemas = include $schemaPath;

        if (!is_array($schemas)) {
            throw new \InvalidArgumentException("Schema file must return an array of table definitions");
        }

        return $schemas;
    }

    /**
     * Get module version from its info file
     */
    private function getModuleVersion(string $moduleName): ?string
    {
        $moduleManager = ModuleManager::getInstance();
        $moduleInfo = $moduleManager->getModule($moduleName);

        return $moduleInfo['version'] ?? null;
    }

    /**
     * Run module upgrade script
     */
    private function runUpgradeScript(string $moduleName, string $upgradePath, string $fromVersion, string $toVersion): void
    {
        if (!file_exists($upgradePath)) {
            return;
        }

        // Include the upgrade script with available variables
        $DB = $this->database;
        $schemaBuilder = $this->schemaBuilder;

        include $upgradePath;
    }
}
